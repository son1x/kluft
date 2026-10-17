<?php

namespace App\Services\Mitglieder;

use App\Models\User;
use App\Support\Beitraege;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * NaMi 2 (nami.dpsg.de). Anmeldung wie in der NaMi-App
 * (digital-scouts/dpsg-nami-app): POST auth/manual/sessionStartup mit
 * Mitgliedsnummer und Passwort, danach Session-Cookie. Gesucht wird über
 * search-multi nur nach Vor- und Nachname, damit nicht die ganze
 * Mitgliederliste geladen wird.
 *
 * Zwei Arten der Anmeldung (config mitglieder.nami.auth):
 * - persoenlich: Wer prüft, meldet sich mit dem eigenen Konto an
 *   (anmelden()); weiter geht es mit dem Session-Cookie (mitSession()).
 *   Wer mag, speichert den Zugang verschlüsselt im Profil (mitZugang());
 *   dann entfällt die Eingabe und abgelaufene Sitzungen werden erneuert.
 * - konto: technisches Konto aus NAMI_USER/NAMI_PASSWORD.
 */
class NamiVerzeichnis implements Mitgliederverzeichnis
{
    private ?CookieJar $session = null;

    /**
     * Persönlicher Zugang aus dem Profil: erlaubt Neuanmeldung bei abgelaufener Sitzung.
     *
     * @var array{0: string, 1: string}|null
     */
    private ?array $zugang = null;

    public function name(): string
    {
        return 'NaMi 2';
    }

    /** Persönliche Anmeldung: jede Person mit ihrem eigenen NaMi-Konto. */
    public static function persoenlich(): bool
    {
        return config('mitglieder.driver') === 'nami' && config('mitglieder.nami.auth') === 'persoenlich';
    }

    /**
     * Mit einem persönlichen Konto anmelden. Liefert nur das Session-Cookie
     * (zum verschlüsselten Weiterreichen an die Warteschlange).
     *
     * @return list<array<string, mixed>>
     *
     * @throws MitgliederverzeichnisException
     */
    public function anmelden(string $mitgliedsnummer, string $passwort): array
    {
        $this->session = $this->sessionStartup(trim($mitgliedsnummer), $passwort);

        return array_values($this->session->toArray());
    }

    /**
     * Mit einem bestehenden Session-Cookie weiterarbeiten (ohne Passwort).
     *
     * @param  list<array<string, mixed>>  $cookies
     */
    public function mitSession(array $cookies): static
    {
        $this->session = new CookieJar(false, array_map(fn (array $cookie) => new SetCookie($cookie), $cookies));

        return $this;
    }

    /**
     * Mit dem im Profil gespeicherten Zugang einer Person arbeiten. Meldet
     * sich erst bei der ersten Abfrage an und bei abgelaufener Sitzung neu.
     */
    public function mitZugang(User $user): static
    {
        if ($user->hasNamiZugang()) {
            $this->zugang = [(string) $user->nami_user, (string) $user->nami_password];
        }

        return $this;
    }

    /**
     * Gespeicherten Zugang prüfen und das Ergebnis für die Ampel merken.
     * Liefert das Session-Cookie.
     *
     * @return list<array<string, mixed>>
     *
     * @throws MitgliederverzeichnisException
     */
    public function anmeldenMitProfil(User $user): array
    {
        if (! $user->hasNamiZugang()) {
            throw new MitgliederverzeichnisException('Kein NaMi-Zugang im Profil gespeichert.');
        }
        try {
            $session = $this->anmelden((string) $user->nami_user, (string) $user->nami_password);
        } catch (MitgliederverzeichnisException $e) {
            $user->forceFill(['nami_checked_at' => now(), 'nami_error' => mb_substr($e->getMessage(), 0, 255)])->save();

            throw $e;
        }
        $this->zugang = [(string) $user->nami_user, (string) $user->nami_password];
        $user->forceFill(['nami_checked_at' => now(), 'nami_error' => null])->save();

        return $session;
    }

    public function suche(string $vorname, string $nachname): array
    {
        $values = [
            'vorname' => $vorname,
            'nachname' => $nachname,
            'spitzname' => '', 'mitgliedsNummber' => '', 'mglWohnort' => '', 'alterVon' => '', 'alterBis' => '',
            'mglStatusId' => null, 'mglTypeId' => [], 'organisation' => '', 'tagId' => [], 'bausteinIncludedId' => [],
            'zeitschriftenversand' => false, 'searchName' => '', 'taetigkeitId' => [], 'untergliederungId' => [],
            'mitAllenTaetigkeiten' => false, 'withEndedTaetigkeiten' => false, 'ebeneId' => null, 'grpNummer' => '',
            'grpName' => '', 'gruppierung1Id' => null, 'gruppierung2Id' => null, 'gruppierung3Id' => null,
            'gruppierung4Id' => null, 'gruppierung5Id' => null, 'gruppierung6Id' => null, 'inGrp' => false, 'unterhalbGrp' => false,
        ];

        $body = $this->get('/search-multi/result-list', [
            'searchedValues' => json_encode($values, JSON_UNESCAPED_UNICODE),
            'page' => 1, 'start' => 0, 'limit' => 50,
        ]);

        return array_values(array_map(fn (array $row) => $this->mitglied($row), (array) ($body['data'] ?? [])));
    }

    /**
     * Interne NaMi-ID der Gruppierung mit dieser DPSG-Nummer (z. B. 210601),
     * gesucht im Gruppierungsbaum ab der Bundesebene. Abgestiegen wird nur in
     * Diözese und Bezirk dieser Nummer, damit nicht der ganze Baum geladen wird.
     *
     * @throws MitgliederverzeichnisException
     */
    public function gruppierungId(string $nummer): int
    {
        $nummer = preg_replace('/\D/', '', $nummer) ?? '';
        if (strlen($nummer) !== 6) {
            throw new MitgliederverzeichnisException('Für den Abruf fehlt die DPSG-Nummer des Stamms (sechs Ziffern).');
        }
        $wege = [substr($nummer, 0, 2).'0000', substr($nummer, 0, 4).'00', $nummer];
        $knoten = [1];
        for ($tiefe = 0; $tiefe < 5 && $knoten !== []; $tiefe++) {
            $weiter = [];
            foreach ($knoten as $id) {
                foreach ((array) ($this->get('/gruppierungen/filtered-for-navigation/gruppierung/node/'.$id, [])['data'] ?? []) as $g) {
                    $gid = (int) ($g['id'] ?? 0);
                    preg_match('/(\d{6})\s*$/', (string) ($g['descriptor'] ?? ''), $m);
                    $nr = $m[1] ?? null;
                    if ($nr === $nummer || $gid === (int) $nummer) {
                        return $gid;
                    }
                    if ($nr === null || in_array($nr, $wege, true)) {
                        $weiter[] = $gid;
                    }
                }
            }
            $knoten = array_slice($weiter, 0, 20);
        }

        throw new MitgliederverzeichnisException("Die Gruppierung {$nummer} ist in NaMi für dieses Konto nicht sichtbar.");
    }

    /**
     * Mitgliederliste einer Gruppierung (eine Zeile je Person, ohne Beitragsart).
     *
     * @return list<array<string, mixed>>
     *
     * @throws MitgliederverzeichnisException
     */
    public function mitgliederListe(int $gruppierungId): array
    {
        $body = $this->get('/mitglied/filtered-for-navigation/gruppierung/gruppierung/'.$gruppierungId.'/flist', ['page' => 1, 'start' => 0, 'limit' => 5000]);

        return array_values(array_filter((array) ($body['data'] ?? []), 'is_array'));
    }

    /**
     * Stammdaten eines Mitglieds (u. a. beitragsartId, eintrittsdatum, austrittsDatum).
     *
     * @return array<string, mixed>
     *
     * @throws MitgliederverzeichnisException
     */
    public function mitgliedDetails(int $gruppierungId, int $namiId): array
    {
        $body = $this->get('/mitglied/filtered-for-navigation/gruppierung/gruppierung/'.$gruppierungId.'/'.$namiId, []);

        return (array) ($body['data'] ?? []);
    }

    /**
     * Beitragsarten (ID => Bezeichnung, z. B. „Voller Beitrag – Stiftungseuro“).
     * NaMi liefert „DPSG Bundesverband 000000 (Voller Beitrag - Stiftungseuro
     * - VERBANDSBEITRAG)“; gelesen wird der Text in der Klammer. Ohne Antwort
     * die Arten des Bundesverbands, wie NaMi sie im Oktober 2026 lieferte.
     *
     * @return array<int, string>
     */
    public function beitragsarten(int $gruppierungId): array
    {
        $bekannt = [1 => 'Voller Beitrag', 2 => 'Familienermäßigt', 3 => 'Ermäßigt', 4 => 'Voller Beitrag – Stiftungseuro',
            5 => 'Familienermäßigt – Stiftungseuro', 6 => 'Ermäßigt – Stiftungseuro', 7 => 'Übernahme'];
        try {
            $response = $this->http()->withOptions(['cookies' => $this->login()])
                ->get(rtrim((string) config('mitglieder.nami.url'), '/').'/ica/rest/namiBeitrag/beitragsartmgl/gruppierung/'.$gruppierungId);
            $arten = [];
            foreach ((array) ($response->json('data') ?? []) as $a) {
                $text = (string) ($a['descriptor'] ?? '');
                $text = preg_match('/\(([^()]*)\)\s*$/u', $text, $m) ? (string) preg_replace('/\s*-\s*VERBANDSBEITRAG$/u', '', $m[1]) : $text;
                if (is_numeric($a['id'] ?? null) && trim($text) !== '') {
                    $arten[(int) $a['id']] = Beitraege::artName($text);
                }
            }

            return $arten + $bekannt;
        } catch (\Throwable) {
            return $bekannt;
        }
    }

    /**
     * Rohe Abfrage für die Testabfrage (NamiDiagnose): Antwort ungeprüft,
     * mit Status und Dauer. Pfade mit „/“ relativ zum Service-Pfad, sonst
     * relativ zur NaMi-Adresse (z. B. „ica/rest/namiBeitrag/…“).
     *
     * @param  array<string, mixed>  $query
     * @return array{status: int, body: mixed, ms: int}
     *
     * @throws MitgliederverzeichnisException
     */
    public function rohAbfrage(string $pfad, array $query = []): array
    {
        $url = str_starts_with($pfad, '/') ? $this->base().$pfad : rtrim((string) config('mitglieder.nami.url'), '/').'/'.$pfad;
        $start = microtime(true);
        try {
            $response = $this->http()->withOptions(['cookies' => $this->login()])->get($url, $query);
        } catch (ConnectionException) {
            throw new MitgliederverzeichnisException('NaMi nicht erreichbar.');
        }

        return ['status' => $response->status(), 'body' => $response->json() ?? mb_substr($response->body(), 0, 200), 'ms' => (int) round((microtime(true) - $start) * 1000)];
    }

    public function pruefe(): void
    {
        $this->session = null;
        $this->suche('Kluft-Verbindungstest', 'Kluft-Verbindungstest');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function mitglied(array $row): Mitglied
    {
        $geburt = (string) ($row['entries_geburtsDatum'] ?? '');
        $status = mb_strtolower((string) ($row['entries_status'] ?? ''));
        $typ = mb_strtolower((string) ($row['entries_mglType'] ?? ''));

        return new Mitglied(
            nummer: (string) ($row['entries_mitgliedsNummer'] ?? $row['id'] ?? ''),
            vorname: (string) ($row['entries_vorname'] ?? ''),
            nachname: (string) ($row['entries_nachname'] ?? ''),
            geburtsdatum: preg_match('/^\d{4}-\d{2}-\d{2}/', $geburt) ? Carbon::parse(substr($geburt, 0, 10)) : null,
            email: filled($row['entries_email'] ?? null) ? (string) $row['entries_email'] : null,
            gruppierung: filled($row['entries_gruppierung'] ?? null) ? (string) $row['entries_gruppierung'] : null,
            // NaMi führt auch Nicht-Mitglieder (z. B. Kontakte); die zählen nicht.
            aktiv: $status === 'aktiv' && ! str_contains($typ, 'nicht'),
        );
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query, bool $retry = true): array
    {
        try {
            $response = $this->http()->withOptions(['cookies' => $this->login()])->get($this->base().$path, $query);
        } catch (ConnectionException) {
            throw new MitgliederverzeichnisException('NaMi nicht erreichbar.');
        }
        $body = $response->json();

        // Abgelaufene Session: einmal neu anmelden.
        if ($retry && ($response->status() === 401 || (is_array($body) && ($body['success'] ?? true) === false && str_contains(mb_strtolower((string) ($body['message'] ?? '')), 'session')))) {
            $this->session = null;

            return $this->get($path, $query, false);
        }

        if (! $response->successful() || ! is_array($body) || ($body['success'] ?? false) !== true) {
            throw new MitgliederverzeichnisException('NaMi-Abfrage fehlgeschlagen (HTTP '.$response->status().').');
        }

        return $body;
    }

    private function login(): CookieJar
    {
        if ($this->session) {
            return $this->session;
        }

        if ($this->zugang) {
            return $this->session = $this->sessionStartup($this->zugang[0], $this->zugang[1]);
        }

        if (self::persoenlich()) {
            throw new NamiSitzungAbgelaufen('NaMi-Sitzung abgelaufen. Bitte „Mitgliedschaft prüfen“ erneut starten und mit dem eigenen NaMi-Konto anmelden.');
        }

        $user = (string) config('mitglieder.nami.user');
        $password = (string) config('mitglieder.nami.password');
        if ($user === '' || $password === '') {
            throw new MitgliederverzeichnisException('NaMi-Zugang fehlt (NAMI_USER, NAMI_PASSWORD).');
        }

        return $this->session = $this->sessionStartup($user, $password);
    }

    private function sessionStartup(string $user, string $password): CookieJar
    {
        if ($user === '' || $password === '') {
            throw new MitgliederverzeichnisException('Bitte Mitgliedsnummer und Passwort angeben.');
        }

        $jar = new CookieJar;
        try {
            $response = $this->http()->asForm()->withOptions(['cookies' => $jar])
                ->post($this->base().'/auth/manual/sessionStartup', ['username' => $user, 'password' => $password, 'Login' => 'API']);
        } catch (ConnectionException) {
            throw new MitgliederverzeichnisException('NaMi nicht erreichbar.');
        }

        $body = $response->json();
        if (! $response->successful() || ! is_array($body) || (int) ($body['statusCode'] ?? -1) !== 0) {
            // Kein Antworttext ins Log: er kann die Mitgliedsnummer enthalten.
            throw new MitgliederverzeichnisException('NaMi-Anmeldung fehlgeschlagen (HTTP '.$response->status().'). Zugangsdaten prüfen.');
        }

        return $jar;
    }

    /** Für Ampel und Statusseite: Erreichbarkeit, 5 Minuten zwischengespeichert. */
    public static function erreichbarGemerkt(): bool
    {
        return (bool) Cache::remember('nami:erreichbar', 300, fn () => (new self)->erreichbar());
    }

    /** Ohne Anmeldung: Ist NaMi überhaupt erreichbar? (für die persönliche Anmeldung) */
    public function erreichbar(): bool
    {
        try {
            return $this->http()->get(rtrim((string) config('mitglieder.nami.url'), '/'))->status() < 500;
        } catch (ConnectionException) {
            return false;
        }
    }

    private function http(): PendingRequest
    {
        return Http::timeout((int) config('mitglieder.timeout', 20))->acceptJson();
    }

    private function base(): string
    {
        return rtrim((string) config('mitglieder.nami.url'), '/').'/'.trim((string) config('mitglieder.nami.path'), '/');
    }
}
