<?php

namespace App\Services\Mitglieder;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * NaMi 2 (nami.dpsg.de). Anmeldung wie in der NaMi-App
 * (digital-scouts/dpsg-nami-app): POST auth/manual/sessionStartup mit
 * Mitgliedsnummer und Passwort, danach Session-Cookie. Gesucht wird über
 * search-multi nur nach Vor- und Nachname, damit nicht die ganze
 * Mitgliederliste geladen wird.
 */
class NamiVerzeichnis implements Mitgliederverzeichnis
{
    private ?CookieJar $session = null;

    public function name(): string
    {
        return 'NaMi 2';
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

        $user = (string) config('mitglieder.nami.user');
        $password = (string) config('mitglieder.nami.password');
        if ($user === '' || $password === '') {
            throw new MitgliederverzeichnisException('NaMi-Zugang fehlt (NAMI_USER, NAMI_PASSWORD).');
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

        return $this->session = $jar;
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
