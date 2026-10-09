<?php

namespace App\Services\Update;

use App\Models\User;
use App\Notifications\UpdateNotification;
use App\Services\Backup\BackupService;
use App\Support\AuditLogger;
use App\Support\KluftVersion;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Updates für eigenständige Installationen (docs/updates.md): prüfen, ob
 * ein neueres signiertes Release da ist, und es installieren – mit Backup
 * vorher, Wartungsmodus, Tausch der Programmdateien durch ein eigenes
 * Skript (resources/update/austausch.php) und Rücknahme, wenn danach etwas
 * schiefgeht. Läuft im Cronjob (kluft:update), nie im Web-Request.
 */
class Updater
{
    public const CACHE_VERFUEGBAR = 'kluft.update.verfuegbar';

    public const CACHE_ANGEFORDERT = 'kluft.update.angefordert';

    private const CACHE_GEMELDET = 'kluft.update.gemeldet';

    /** @var list<string> */
    private array $protokoll = [];

    public function __construct(private UpdateQuelle $quelle) {}

    public static function modus(): string
    {
        $modus = (string) config('kluft.updates.mode');

        return in_array($modus, ['manual', 'auto'], true) ? $modus : 'off';
    }

    public static function aktiv(): bool
    {
        return self::modus() !== 'off';
    }

    /**
     * Neueste Release-Beschreibung holen und merken; bei einer neuen Version
     * einmalig die Admins benachrichtigen. Liefert das Release nur, wenn es
     * neuer ist als die installierte Version.
     *
     * @throws UpdateFehler
     */
    public function pruefen(): ?Release
    {
        $release = $this->quelle->neuestes();
        Cache::forever(self::CACHE_VERFUEGBAR, ['release' => $release->toArray(), 'geprueft' => now()->timestamp]);

        if (! self::istNeuer($release)) {
            return null;
        }
        if (Cache::get(self::CACHE_GEMELDET) !== $release->version) {
            Cache::forever(self::CACHE_GEMELDET, $release->version);
            $this->admins('verfuegbar', $release->version, KluftVersion::version(), $release->aenderungen);
        }

        return $release;
    }

    /** Zuletzt geprüftes Release (ohne Netz), sofern neuer als installiert. */
    public static function bekannt(): ?Release
    {
        $eintrag = Cache::get(self::CACHE_VERFUEGBAR);
        if (! is_array($eintrag) || ! is_array($eintrag['release'] ?? null)) {
            return null;
        }
        $release = Release::fromArray($eintrag['release']);

        return self::istNeuer($release) ? $release : null;
    }

    public static function zuletztGeprueft(): ?int
    {
        $eintrag = Cache::get(self::CACHE_VERFUEGBAR);

        return is_array($eintrag) ? ($eintrag['geprueft'] ?? null) : null;
    }

    public static function istNeuer(Release $release): bool
    {
        return version_compare($release->version, KluftVersion::version() ?? '0.0.0', '>');
    }

    /**
     * Installiert das Release. Wirft bei jedem Fehler; die Installation ist
     * danach entweder auf dem neuen Stand oder wieder auf dem alten.
     *
     * @return list<string> Protokoll
     *
     * @throws UpdateFehler
     */
    public function installieren(Release $release, ?User $ausgeloestVon = null): array
    {
        $this->protokoll = [];
        $alt = KluftVersion::version() ?? '0.0.0';

        $lock = Cache::lock('kluft-update', 3600);
        if (! $lock->get()) {
            throw new UpdateFehler('Es läuft bereits ein Update.');
        }

        try {
            $this->installierenOhneSperre($release, $alt);
            $this->schreibeLetztenLauf(true, $release->version, $alt, $ausgeloestVon);
            // Protokoll und Mails im neuen Programmstand: Dieser Prozess hat
            // noch die alten Klassen geladen, neue kämen schon aus dem neuen Code.
            $this->artisan(base_path(), ['kluft:update', '--abschluss']);
        } catch (Throwable $e) {
            $this->log('Fehler: '.$e->getMessage());
            $this->schreibeLetztenLauf(false, $release->version, $alt, $ausgeloestVon);
            $this->abschliessen($release);

            throw $e instanceof UpdateFehler ? $e : new UpdateFehler($e->getMessage(), previous: $e);
        } finally {
            $lock->release();
        }

        return $this->protokoll;
    }

    /**
     * Protokolleintrag und Mail an die Admins zum letzten Lauf – einmal je
     * Lauf. Nach einem erfolgreichen Update ruft der neue Programmstand das
     * über „kluft:update --abschluss“ auf.
     */
    public function abschliessen(?Release $release = null): void
    {
        $lauf = self::letzterLauf();
        if ($lauf === null || ($lauf['gemeldet'] ?? false)) {
            return;
        }
        $lauf['gemeldet'] = true;
        File::put(self::pfad('letzter-lauf.json'), (string) json_encode($lauf, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $von = isset($lauf['von']) ? User::query()->find($lauf['von']) : null;
        AuditLogger::log($lauf['ok'] ? 'update.installiert' : 'update.fehlgeschlagen', $von, request: null);
        $fehler = $lauf['ok'] ? null : (string) collect($lauf['protokoll'])->last();
        $this->admins($lauf['ok'] ? 'installiert' : 'fehlgeschlagen', $lauf['version'], $lauf['vorher'], $release->aenderungen ?? [], $fehler);
    }

    /** @return list<string> */
    public function protokoll(): array
    {
        return $this->protokoll;
    }

    /** @return array{ok: bool, version: string, vorher: string, zeit: int, von?: int|null, gemeldet?: bool, protokoll: list<string>}|null */
    public static function letzterLauf(): ?array
    {
        $datei = self::pfad('letzter-lauf.json');
        $daten = is_file($datei) ? json_decode((string) file_get_contents($datei), true) : null;

        return is_array($daten) ? $daten : null;
    }

    public static function pfad(string $datei = ''): string
    {
        return rtrim((string) config('kluft.updates.path'), '/').($datei !== '' ? '/'.$datei : '');
    }

    private function installierenOhneSperre(Release $release, string $alt): void
    {
        if (! self::aktiv()) {
            throw new UpdateFehler('Updates sind für diese Installation abgeschaltet (KLUFT_UPDATES).');
        }
        if (! self::istNeuer($release)) {
            throw new UpdateFehler('Version '.$release->version.' ist nicht neuer als die installierte ('.$alt.').');
        }
        if (version_compare(PHP_VERSION, $release->php, '<')) {
            throw new UpdateFehler('Version '.$release->version.' braucht PHP '.$release->php.', hier läuft '.PHP_VERSION.'.');
        }
        if (! class_exists(ZipArchive::class)) {
            throw new UpdateFehler('Die PHP-Erweiterung „zip“ fehlt.');
        }

        $basis = base_path();
        $arbeit = self::pfad();
        File::ensureDirectoryExists($arbeit);
        $paket = $arbeit.'/kluft-'.$release->version.'.zip';
        $neu = $arbeit.'/neu-'.$release->version;
        $rueck = $arbeit.'/rueck-'.$alt;
        $skript = $arbeit.'/austausch.php';

        $this->log("Update von {$alt} auf {$release->version}.");
        $this->herunterladen($release, $paket);
        $this->entpacken($paket, $neu, $release);

        $this->log('Backup vor dem Update …');
        try {
            $this->log('Backup erstellt: '.basename(app(BackupService::class)->create()));
        } catch (RuntimeException $e) {
            throw new UpdateFehler('Backup fehlgeschlagen – Update abgebrochen, nichts verändert. '.$e->getMessage());
        }

        // Eigene Kopie des Tauschskripts: das in resources/ wird gleich selbst getauscht.
        File::copy(resource_path('update/austausch.php'), $skript);

        $this->artisan($basis, ['down', '--retry=60']);
        $tausch = Process::path($basis)->timeout(600)->run([PHP_BINARY, $skript, 'tauschen', $basis, $neu, $rueck]);
        $this->log(trim($tausch->output()).trim($tausch->errorOutput()));
        if (! $tausch->successful()) {
            $this->artisan($basis, ['up']);
            throw new UpdateFehler('Dateien konnten nicht getauscht werden; alte Version läuft weiter.');
        }

        // Ab hier läuft der neue Stand: Datenbank und Assets im neuen Programm.
        foreach ([['migrate', '--force'], ['kluft:assets'], ['optimize:clear']] as $befehl) {
            $lauf = $this->artisan($basis, $befehl);
            if (! $lauf['ok']) {
                $zurueck = Process::path($basis)->timeout(600)->run([PHP_BINARY, $skript, 'zurueck', $basis, $rueck]);
                $this->log(trim($zurueck->output()));
                $this->artisan($basis, ['optimize:clear']);
                $this->artisan($basis, ['up']);

                throw new UpdateFehler('„'.implode(' ', $befehl).'“ ist fehlgeschlagen; die vorherige Version ist wiederhergestellt. Falls die Datenbank schon teilweise umgestellt war: Backup von eben einspielen (php artisan backup:restore).');
            }
        }
        $this->artisan($basis, ['up']);

        File::delete($paket);
        File::deleteDirectory($neu);
        foreach (File::directories($arbeit) as $ordner) {
            if (str_starts_with(basename($ordner), 'rueck-') && $ordner !== $rueck) {
                File::deleteDirectory($ordner);
            }
        }
        $this->log("Version {$release->version} ist installiert. Rücksicherung der Version {$alt} liegt bis zum nächsten Update bereit.");
    }

    private function herunterladen(Release $release, string $ziel): void
    {
        $this->log('Lade '.$release->url.' …');
        try {
            $antwort = Http::timeout(600)->sink($ziel)->get($release->url);
        } catch (ConnectionException $e) {
            throw new UpdateFehler('Download fehlgeschlagen: '.$e->getMessage());
        }
        if (! $antwort->successful()) {
            File::delete($ziel);
            throw new UpdateFehler('Download fehlgeschlagen (HTTP '.$antwort->status().').');
        }
        if (! hash_equals($release->sha256, (string) hash_file('sha256', $ziel))) {
            File::delete($ziel);
            throw new UpdateFehler('Das heruntergeladene Paket passt nicht zur Signatur (Prüfsumme falsch). Nichts verändert.');
        }
        $this->log('Paket geprüft ('.round(filesize($ziel) / 1024 / 1024, 1).' MB, Prüfsumme stimmt).');
    }

    private function entpacken(string $paket, string $ziel, Release $release): void
    {
        File::deleteDirectory($ziel);
        $zip = new ZipArchive;
        if ($zip->open($paket) !== true) {
            throw new UpdateFehler('Paket lässt sich nicht öffnen.');
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (str_contains($name, '..') || str_starts_with($name, '/') || str_contains($name, '\\')) {
                $zip->close();
                throw new UpdateFehler('Paket enthält einen unzulässigen Pfad: '.$name);
            }
        }
        $zip->extractTo($ziel);
        $zip->close();

        $version = is_file($ziel.'/VERSION') ? trim((string) file_get_contents($ziel.'/VERSION')) : '';
        if ($version !== $release->version || ! is_file($ziel.'/artisan') || ! is_file($ziel.'/vendor/autoload.php')) {
            throw new UpdateFehler('Paketinhalt passt nicht zur Version '.$release->version.'.');
        }
        $this->log('Paket entpackt.');
    }

    /**
     * Artisan als eigener Prozess im (ggf. schon neuen) Programmstand.
     *
     * @param  list<string>  $befehl
     * @return array{ok: bool, ausgabe: string}
     */
    private function artisan(string $basis, array $befehl): array
    {
        $lauf = Process::path($basis)->timeout(900)->run([PHP_BINARY, 'artisan', ...$befehl, '--no-interaction']);
        $ausgabe = trim($lauf->output()."\n".$lauf->errorOutput());
        $this->log('artisan '.implode(' ', $befehl).': '.($lauf->successful() ? 'ok' : 'Fehler').($ausgabe !== '' ? ' – '.mb_substr($ausgabe, 0, 500) : ''));

        return ['ok' => $lauf->successful(), 'ausgabe' => $ausgabe];
    }

    private function log(string $zeile): void
    {
        if ($zeile !== '') {
            $this->protokoll[] = $zeile;
        }
    }

    private function schreibeLetztenLauf(bool $ok, string $version, string $vorher, ?User $von): void
    {
        File::ensureDirectoryExists(self::pfad());
        File::put(self::pfad('letzter-lauf.json'), (string) json_encode([
            'ok' => $ok, 'version' => $version, 'vorher' => $vorher, 'zeit' => now()->timestamp,
            'von' => $von?->getKey(), 'gemeldet' => false, 'protokoll' => $this->protokoll,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * @param  list<string>  $aenderungen
     */
    private function admins(string $art, string $version, ?string $vorher = null, array $aenderungen = [], ?string $fehler = null): void
    {
        try {
            Notification::send(
                User::query()->where('status', 'active')->whereHas('roleAssignments', fn ($q) => $q->whereIn('role', ['admin', 'superadmin']))->get(),
                new UpdateNotification($art, $version, $vorher, $aenderungen, $fehler),
            );
        } catch (Throwable $e) {
            report($e);
        }
    }
}
