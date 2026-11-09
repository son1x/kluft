<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Backup einspielen, z. B. beim Livegang ein Backup von Test auf die frisch
 * deployte Live-Website (docs/livegang.md), oder nach einem Schaden.
 *
 * Ersetzt Datenbank, storage/app/private (mit app.key, dem Schlüssel für
 * verschlüsselte Daten) und public/uploads durch den Stand im Backup.
 * Läuft im Cronjob (backup:auftrag) oder per SSH (backup:einspielen), nie im
 * Web-Request: Datenbank und Anmeldung werden dabei ersetzt. Auftrag und
 * Stand liegen deshalb als Dateien in storage/app/private/einspielen, nicht
 * in der Datenbank; den Stand zeigt /einspielen/stand/{token} auch im
 * Wartungsmodus und ohne Anmeldung.
 */
class BackupEinspielen
{
    /** Beim Kopieren aus dem Backup nicht überschreiben (eigene Backups, laufender Auftrag). */
    public const NICHT_UEBERSCHREIBEN = ['backups', 'einspielen'];

    /** @var list<string> */
    private array $protokoll = [];

    private string $token = '';

    /** .env, in die der Schlüssel aus dem Backup kommt (Tests: eigene Datei). */
    public ?string $envPfad = null;

    public function __construct(private BackupService $backups) {}

    public static function ordner(): string
    {
        return storage_path('app/private/einspielen');
    }

    public static function auftragPfad(): string
    {
        return self::ordner().'/auftrag.json';
    }

    public static function standPfad(): string
    {
        return self::ordner().'/stand.json';
    }

    /**
     * Auftrag für den Cronjob: „backup“ (jetzt sichern), „einspielen“ ($datei
     * = Backup) oder „uebertragen“ ($datei = Ordner der Ziel-Website: frisches
     * Backup erstellen und dort einspielen lassen). Gibt das Token für die
     * Stand-Seite zurück.
     */
    public static function beauftragen(string $art, ?string $datei = null, ?string $von = null): string
    {
        if (self::laeuft()) {
            throw new RuntimeException('Es läuft schon ein Auftrag. Bitte warten, bis er fertig ist.');
        }
        $token = Str::random(40);
        File::ensureDirectoryExists(self::ordner(), 0700);
        File::put(self::auftragPfad(), (string) json_encode(['art' => $art, 'datei' => $datei, 'token' => $token, 'von' => $von, 'am' => now()->toIso8601String()]));
        self::standSchreiben(['token' => $token, 'art' => $art, 'status' => 'wartet', 'zeilen' => ['Auftrag angenommen. Er startet innerhalb einer Minute (Cronjob).'], 'am' => now()->toIso8601String()]);

        return $token;
    }

    /** @return array{art: string, datei: ?string, token: string, von: ?string, am: string}|null */
    public static function auftrag(): ?array
    {
        $d = is_file(self::auftragPfad()) ? json_decode((string) file_get_contents(self::auftragPfad()), true) : null;

        return is_array($d) && in_array($d['art'] ?? null, ['backup', 'einspielen', 'uebertragen'], true) ? $d : null;
    }

    /** @return array{token: string, art: string, status: string, zeilen: list<string>, am: string, ende?: string}|null */
    public static function stand(): ?array
    {
        $d = is_file(self::standPfad()) ? json_decode((string) file_get_contents(self::standPfad()), true) : null;

        return is_array($d) ? $d : null;
    }

    /** Wartet oder läuft gerade (nach zwei Stunden ohne Fortschritt nicht mehr). */
    public static function laeuft(): bool
    {
        $s = self::stand();

        return $s !== null && in_array($s['status'], ['wartet', 'laeuft'], true)
            && filemtime(self::standPfad()) > time() - 7200;
    }

    /** @param  array<string, mixed>  $stand */
    private static function standSchreiben(array $stand): void
    {
        File::ensureDirectoryExists(self::ordner(), 0700);
        File::put(self::standPfad(), (string) json_encode($stand, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * Backups, die sich einspielen lassen: die eigenen und die anderer
     * Kluft-Installationen im selben Webspace (z. B. Test neben Live in Plesk).
     *
     * @return array<string, string> Pfad => Beschriftung, neueste zuerst
     */
    public function quellen(): array
    {
        $muster = [$this->backups->directory().'/backup-*.dpsgbak', dirname(base_path()).'/*/storage/app/private/backups/backup-*.dpsgbak'];
        $dateien = [];
        foreach ($muster as $m) {
            foreach (@glob($m) ?: [] as $datei) {
                $echt = realpath($datei);
                if ($echt !== false && is_readable($echt)) {
                    $dateien[$echt] = filemtime($echt);
                }
            }
        }
        arsort($dateien);
        $eigene = realpath($this->backups->directory()) ?: $this->backups->directory();

        return collect($dateien)->take(40)->mapWithKeys(fn (int $zeit, string $pfad) => [$pfad => (str_starts_with($pfad, $eigene.'/') ? 'Diese Website' : basename(dirname($pfad, 5)))
            .' · '.date('d.m.Y H:i', $zeit).' · '.number_format(filesize($pfad) / 1048576, 1, ',', '.').' MB · '.basename($pfad)])->all();
    }

    /**
     * Andere Kluft-Installationen im selben Webspace, in die sich übertragen
     * lässt (z. B. von Test auf die frisch deployte Live-Website, auf der sich
     * noch niemand anmelden kann).
     *
     * @return array<string, string> Ordner => Beschriftung (Ordner und APP_URL)
     */
    public function ziele(): array
    {
        $selbst = realpath(base_path());
        $ziele = [];
        foreach (@glob(dirname(base_path()).'/*', GLOB_ONLYDIR) ?: [] as $ordner) {
            $echt = realpath($ordner);
            if ($echt === false || $echt === $selbst || ! is_file($echt.'/artisan') || ! is_dir($echt.'/storage/app/private') || ! is_writable($echt.'/storage/app/private')) {
                continue;
            }
            $url = preg_match('/^APP_URL=(.*)$/m', (string) @file_get_contents($echt.'/.env'), $t) ? trim($t[1], " \t\"'") : null;
            $ziele[$echt] = basename($echt).($url ? ' ('.$url.')' : '');
        }

        return $ziele;
    }

    /**
     * Den Auftrag aus der Datei ausführen (Cronjob). Gibt false zurück, wenn es keinen gibt.
     */
    public function auftragAusfuehren(): bool
    {
        $auftrag = self::auftrag();
        if ($auftrag === null) {
            return false;
        }
        $sperre = fopen(self::ordner().'/sperre', 'c');
        if ($sperre === false || ! flock($sperre, LOCK_EX | LOCK_NB)) {
            return true;
        }
        try {
            File::delete(self::auftragPfad());
            $this->token = $auftrag['token'];
            if ($auftrag['art'] === 'backup') {
                $this->sichern();
            } elseif ($auftrag['art'] === 'uebertragen') {
                $this->uebertragen((string) $auftrag['datei']);
            } else {
                $this->einspielen((string) $auftrag['datei']);
            }
        } finally {
            flock($sperre, LOCK_UN);
            fclose($sperre);
        }

        return true;
    }

    private function sichern(): ?string
    {
        $this->melden('laeuft', 'Backup wird erstellt …');
        try {
            $datei = $this->backups->create();
            $this->melden('fertig', 'Fertig: '.basename($datei).' ('.number_format(filesize($datei) / 1048576, 1, ',', '.').' MB).');

            return $datei;
        } catch (Throwable $e) {
            report($e);
            $this->melden('fehler', 'Fehler: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Frisches Backup erstellen und der Ziel-Website als Auftrag übergeben;
     * deren Cronjob spielt es ein (mit demselben BACKUP_PASSWORD).
     */
    private function uebertragen(string $ziel): void
    {
        if (! array_key_exists($ziel, $this->ziele())) {
            $this->melden('fehler', 'Fehler: Ziel-Website nicht gefunden oder nicht beschreibbar: '.basename($ziel));

            return;
        }
        $datei = $this->sichern();
        if ($datei === null) {
            return;
        }
        $token = Str::random(40);
        $ordner = $ziel.'/storage/app/private/einspielen';
        File::ensureDirectoryExists($ordner, 0700);
        File::put($ordner.'/stand.json', (string) json_encode(['token' => $token, 'art' => 'einspielen', 'status' => 'wartet',
            'zeilen' => ['Auftrag von '.(parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'einer anderen Website').' angenommen: '.basename($datei).'. Er startet innerhalb einer Minute (Cronjob dieser Website).'],
            'am' => now()->toIso8601String()], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        File::put($ordner.'/auftrag.json', (string) json_encode(['art' => 'einspielen', 'datei' => $datei, 'token' => $token,
            'von' => config('app.url'), 'am' => now()->toIso8601String()]));
        $url = preg_match('/^APP_URL=(.*)$/m', (string) @file_get_contents($ziel.'/.env'), $t) ? rtrim(trim($t[1], " \t\"'"), '/') : null;
        $this->melden('fertig', 'Übergeben an '.basename($ziel).'. Dort spielt der Cronjob das Backup innerhalb einer Minute ein.'
            .($url ? ' Fortschritt: '.$url.'/einspielen/stand/'.$token : ' Fortschritt unter /einspielen/stand/'.$token.' auf der Ziel-Website.'));
    }

    /**
     * Backup einspielen. Wirft bei Fehlern; die Website ist danach wieder
     * erreichbar (Laravel-Wartungsmodus aus), Kluft-Wartungsmodus wie im Backup.
     *
     * @return array<string, int> Anzahlen aus dem Backup
     */
    public function einspielen(string $datei, ?string $token = null): array
    {
        $this->token = $token ?? ($this->token ?: Str::random(40));
        $arbeit = self::ordner().'/arbeit-'.Str::random(8);

        try {
            $echt = realpath($datei);
            if ($echt === false || ! is_file($echt) || ! str_ends_with($echt, '.dpsgbak')) {
                throw new RuntimeException('Backup-Datei nicht gefunden: '.basename($datei));
            }
            $this->melden('laeuft', 'Backup wird entschlüsselt: '.basename($echt).' …');
            $this->backups->restoreTo($echt, $arbeit);
            $manifest = json_decode((string) @file_get_contents($arbeit.'/manifest.json'), true);
            if (! is_array($manifest)) {
                throw new RuntimeException('Im Backup fehlt manifest.json.');
            }
            $treiber = DB::connection()->getDriverName();
            $quelle = $manifest['driver'] ?? '?';
            if (($treiber === 'sqlite') !== ($quelle === 'sqlite')) {
                throw new RuntimeException("Das Backup ist für {$quelle}, diese Website nutzt {$treiber}.");
            }
            $this->melden('laeuft', 'Backup von '.($manifest['app_url'] ?? '?').', erstellt '.($manifest['created_at'] ?? '?').'.');

            Artisan::call('down', ['--render' => 'errors::503', '--retry' => 60]);
            $this->melden('laeuft', 'Website im Wartungsmodus. Datenbank wird ersetzt …');
            $this->datenbank($arbeit, $treiber);

            $this->melden('laeuft', 'Dateien werden kopiert (storage/app/private, public/uploads) …');
            $this->dateien($arbeit);
            $this->schluessel($arbeit);

            $this->melden('laeuft', 'Migrationen, Zwischenspeicher, Bildvarianten …');
            foreach ([['migrate', '--force'], ['optimize:clear'], ['media:variants', '--all']] as $befehl) {
                $lauf = Process::path(base_path())->timeout(1800)->run([PHP_BINARY, 'artisan', ...$befehl, '--no-interaction']);
                if ($lauf->failed()) {
                    $this->melden('laeuft', 'Hinweis: '.implode(' ', $befehl).' meldet einen Fehler: '.Str::limit(trim($lauf->errorOutput() ?: $lauf->output()), 300));
                }
            }

            $anzahlen = (array) ($manifest['counts'] ?? []);
            $this->melden('fertig', 'Fertig. Im Backup: '.collect($anzahlen)->map(fn ($n, $t) => "{$t} {$n}")->implode(', ').'. Bitte neu anmelden; wenn der Wartungsmodus im Backup an war, ist er es auch hier.');

            return $anzahlen;
        } catch (Throwable $e) {
            report($e);
            $this->melden('fehler', 'Fehler: '.$e->getMessage());

            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage(), 0, $e);
        } finally {
            File::deleteDirectory($arbeit);
            Artisan::call('up');
        }
    }

    /** Alle Tabellen löschen und den Stand aus dem Backup einspielen. */
    private function datenbank(string $arbeit, string $treiber): void
    {
        if ($treiber === 'sqlite') {
            $quelle = $arbeit.'/database/database.sqlite';
            if (! is_file($quelle)) {
                throw new RuntimeException('Im Backup fehlt die Datenbank.');
            }
            Schema::dropAllTables();
            // Über ATTACH statt Datei kopieren: klappt auch bei offener Verbindung.
            DB::statement('ATTACH DATABASE ? AS quelle', [$quelle]);
            // Erst alle Tabellen, dann die Daten (ohne Fremdschlüsselprüfung), zuletzt Indizes und Trigger.
            DB::statement('PRAGMA foreign_keys = OFF');
            try {
                $objekte = collect(DB::select("select type, name, sql from quelle.sqlite_master where sql is not null and name not like 'sqlite_%'"));
                $tabellen = $objekte->where('type', 'table');
                $tabellen->each(fn ($o) => DB::statement($o->sql));
                $tabellen->each(fn ($o) => DB::statement('insert into main."'.str_replace('"', '""', $o->name).'" select * from quelle."'.str_replace('"', '""', $o->name).'"'));
                $objekte->where('type', '!=', 'table')->each(fn ($o) => DB::statement($o->sql));
            } finally {
                DB::statement('PRAGMA foreign_keys = ON');
                DB::statement('DETACH DATABASE quelle');
            }

            return;
        }

        $dump = $arbeit.'/database/database.sql';
        if (! is_file($dump)) {
            throw new RuntimeException('Im Backup fehlt der Datenbank-Dump.');
        }
        Schema::dropAllTables();
        $config = DB::connection()->getConfig();
        foreach (['mariadb', 'mysql'] as $client) {
            try {
                $lauf = Process::env(['MYSQL_PWD' => (string) ($config['password'] ?? '')])->timeout(1800)->input(fopen($dump, 'rb'))
                    ->run([$client, '-h', (string) $config['host'], '-P', (string) $config['port'], '-u', (string) $config['username'], '--default-character-set=utf8mb4', (string) $config['database']]);
            } catch (Throwable) {
                continue; // Programm nicht vorhanden
            }
            if ($lauf->successful()) {
                return;
            }
            if ($lauf->exitCode() !== 127 && ! str_contains($lauf->errorOutput(), 'not found')) {
                throw new RuntimeException("{$client}: ".Str::limit(trim($lauf->errorOutput()), 300));
            }
        }
        // Ohne Kommandozeilen-Client: über die Datenbankverbindung.
        DB::unprepared((string) file_get_contents($dump));
    }

    private function dateien(string $arbeit): void
    {
        $privat = $arbeit.'/storage/app/private';
        if (is_dir($privat)) {
            foreach (File::directories($privat) as $ordner) {
                if (! in_array(basename($ordner), self::NICHT_UEBERSCHREIBEN, true)) {
                    File::copyDirectory($ordner, storage_path('app/private/'.basename($ordner)));
                }
            }
            foreach (File::files($privat) as $datei) {
                File::copy($datei->getPathname(), storage_path('app/private/'.$datei->getFilename()));
            }
        }
        if (is_dir($arbeit.'/public/uploads')) {
            File::copyDirectory($arbeit.'/public/uploads', public_path('uploads'));
        }
    }

    /**
     * Schlüssel aus dem Backup (storage/app/private/app.key) in die .env
     * übernehmen; sonst wären verschlüsselte Daten unlesbar. Der nächste
     * Deploy nimmt ihn ebenfalls aus app.key.
     */
    private function schluessel(string $arbeit): void
    {
        $neu = trim((string) @file_get_contents($arbeit.'/storage/app/private/app.key'));
        $env = $this->envPfad ?? base_path('.env');
        if (! str_starts_with($neu, 'base64:') || ! is_file($env) || ! is_writable($env)) {
            return;
        }
        $inhalt = (string) file_get_contents($env);
        if (str_contains($inhalt, 'APP_KEY='.$neu)) {
            return;
        }
        $inhalt = preg_match('/^APP_KEY=.*$/m', $inhalt)
            ? (string) preg_replace('/^APP_KEY=.*$/m', 'APP_KEY='.str_replace('$', '\$', $neu), $inhalt)
            : $inhalt."\nAPP_KEY=".$neu."\n";
        File::put($env, $inhalt);
        $this->melden('laeuft', 'Schlüssel (APP_KEY) aus dem Backup übernommen.');
    }

    private function melden(string $status, string $zeile): void
    {
        $this->protokoll[] = now()->format('H:i:s').' '.$zeile;
        $alt = self::stand();
        self::standSchreiben([
            'token' => $this->token,
            'art' => $alt['art'] ?? 'einspielen',
            'status' => $status,
            'zeilen' => [...(($alt['token'] ?? null) === $this->token ? array_slice((array) $alt['zeilen'], 0, 1) : []), ...$this->protokoll],
            'am' => $alt['am'] ?? now()->toIso8601String(),
            ...(in_array($status, ['fertig', 'fehler'], true) ? ['ende' => now()->toIso8601String()] : []),
        ]);
    }
}
