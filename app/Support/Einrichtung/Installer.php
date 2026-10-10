<?php

namespace App\Support\Einrichtung;

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Einrichtungsassistent für eine neue Stammes-Installation
 * (public/einrichtung.php, docs/einrichtung.md). Läuft, solange es keine
 * .env gibt, und ohne gestartetes Laravel: prüft Voraussetzungen und
 * Angaben, legt die Instanz an (instanzen/<schlüssel>/), richtet die
 * Datenbank mit Startinhalt und ersten Zugang ein und schreibt zuletzt die
 * .env. Erst mit der .env ist die Installation „eingerichtet“ – bricht
 * vorher etwas ab, lässt sich der Assistent erneut aufrufen.
 *
 * Schutz vor fremder Einrichtung: Wer einrichtet, muss den Einrichtungscode
 * kennen, der nur per FTP lesbar unter storage/ liegt.
 */
class Installer
{
    public const CODE_DATEI = 'storage/app/private/einrichtungscode.txt';

    public function __construct(private string $basis) {}

    public function eingerichtet(): bool
    {
        return is_file($this->basis.'/.env');
    }

    /**
     * @return list<array{label: string, ok: bool, hinweis: string}>
     */
    public function voraussetzungen(): array
    {
        $liste = [[
            'label' => 'PHP 8.3 oder neuer',
            'ok' => version_compare(PHP_VERSION, '8.3.0', '>='),
            'hinweis' => 'Hier läuft PHP '.PHP_VERSION.'. Im Hosting-Panel die PHP-Version umstellen.',
        ]];
        foreach (['pdo_sqlite', 'mbstring', 'openssl', 'sodium', 'zip', 'fileinfo', 'gd', 'intl'] as $erweiterung) {
            $liste[] = ['label' => 'PHP-Erweiterung „'.$erweiterung.'“', 'ok' => extension_loaded($erweiterung), 'hinweis' => 'Im Hosting-Panel bei den PHP-Einstellungen aktivieren.'];
        }
        foreach (['', 'storage', 'bootstrap/cache', 'database', 'public'] as $ordner) {
            $pfad = $this->basis.($ordner === '' ? '' : '/'.$ordner);
            if (! is_dir($pfad)) {
                @mkdir($pfad, 0775, true);
            }
            $liste[] = ['label' => 'Schreibrechte: '.($ordner === '' ? 'Programmordner' : $ordner.'/'), 'ok' => is_writable($pfad), 'hinweis' => 'Per FTP oder im Dateimanager Schreibrechte für die Website setzen.'];
        }

        return $liste;
    }

    /** Einrichtungscode lesen oder beim ersten Aufruf anlegen. */
    public function code(): string
    {
        $datei = $this->basis.'/'.self::CODE_DATEI;
        if (! is_file($datei)) {
            @mkdir(dirname($datei), 0775, true);
            $zeichen = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            $code = '';
            for ($i = 0; $i < 12; $i++) {
                $code .= $zeichen[random_int(0, strlen($zeichen) - 1)];
            }
            @file_put_contents($datei, $code."\n");
            @chmod($datei, 0640);
        }

        return trim((string) @file_get_contents($datei));
    }

    public function codeStimmt(string $eingabe): bool
    {
        $code = $this->code();

        return $code !== '' && hash_equals($code, strtoupper(preg_replace('/\s+/', '', $eingabe) ?? ''));
    }

    /**
     * Angaben aus dem Formular prüfen.
     *
     * @param  array<string, mixed>  $e
     * @return array{angaben: array<string, mixed>, fehler: array<string, string>}
     */
    public function pruefen(array $e, ?string $host = null): array
    {
        $t = fn (string $k, int $max = 200) => mb_substr(trim((string) ($e[$k] ?? '')), 0, $max);
        $fehler = [];

        $angaben = [
            'stamm' => preg_replace('/^stamm\s+/iu', '', $t('stamm', 80)),
            'ort' => $t('ort', 80),
            'nummer' => preg_replace('/\D/', '', $t('nummer', 20)),
            'bezirk' => $t('bezirk', 80),
            'dioezese' => $t('dioezese', 80),
            'domain' => mb_strtolower(preg_replace('#^https?://|/.*$#i', '', $t('domain', 120) ?: (string) $host) ?? ''),
            'email' => mb_strtolower($t('email', 120)),
            'telefon' => $t('telefon', 40),
            'strasse' => $t('strasse', 120),
            'plz_ort' => $t('plz_ort', 120),
            'vorstand' => $t('vorstand', 200),
            'eltern_url' => $t('eltern_url', 200),
            'stufen' => array_values(array_intersect(['biber', 'woelflinge', 'jupfi', 'pfadi', 'rover'], (array) ($e['stufen'] ?? []))),
            'eingerichtet' => date('Y-m-d'),
        ];

        if ($angaben['stamm'] === '') {
            $fehler['stamm'] = 'Bitte den Namen des Stamms angeben.';
        }
        if (! filter_var($angaben['email'], FILTER_VALIDATE_EMAIL)) {
            $fehler['email'] = 'Bitte eine gültige Kontaktadresse angeben.';
        }
        if (! preg_match('/^(?=.{3,120}$)([a-z0-9-]+\.)+[a-z]{2,}$/', $angaben['domain']) && $angaben['domain'] !== 'localhost') {
            $fehler['domain'] = 'Bitte die Adresse der Website angeben, z. B. stamm-beispiel.de.';
        }
        if ($angaben['nummer'] !== '' && strlen($angaben['nummer']) !== 6) {
            $fehler['nummer'] = 'Die DPSG-Nummer hat sechs Ziffern, z. B. 210208.';
        }
        if ($angaben['eltern_url'] !== '') {
            if (! preg_match('#^https?://#i', $angaben['eltern_url'])) {
                $angaben['eltern_url'] = 'https://'.$angaben['eltern_url'];
            }
            if (! filter_var($angaben['eltern_url'], FILTER_VALIDATE_URL)) {
                $fehler['eltern_url'] = 'Bitte eine gültige Adresse angeben, z. B. https://www.dpsg-trier.de.';
            }
        }
        // Gehört zum Bezirk, wenn einer angegeben ist, sonst zum Diözesanverband.
        $angaben[$angaben['bezirk'] !== '' ? 'bezirk_url' : 'dioezese_url'] = $angaben['eltern_url'];
        unset($angaben['eltern_url']);
        if ($angaben['stufen'] === []) {
            $fehler['stufen'] = 'Bitte mindestens eine Stufe auswählen.';
        }

        // Datenbank
        $db = ['art' => ($e['db_art'] ?? 'sqlite') === 'mariadb' ? 'mariadb' : 'sqlite'];
        if ($db['art'] === 'mariadb') {
            $db += ['host' => $t('db_host'), 'port' => $t('db_port', 6) ?: '3306', 'name' => $t('db_name', 64), 'user' => $t('db_user', 64), 'pass' => (string) ($e['db_pass'] ?? '')];
            if ($db['host'] === '' || $db['name'] === '' || $db['user'] === '') {
                $fehler['db'] = 'Für MariaDB bitte Server, Datenbank und Benutzer angeben.';
            }
        }

        // Mailversand (für den Anmeldecode zwingend)
        $mail = ['host' => $t('mail_host'), 'port' => $t('mail_port', 5) ?: '587', 'user' => $t('mail_user', 120), 'pass' => (string) ($e['mail_pass'] ?? ''), 'from' => mb_strtolower($t('mail_from', 120))];
        if ($mail['host'] === '' || ! filter_var($mail['from'], FILTER_VALIDATE_EMAIL)) {
            $fehler['mail'] = 'Bitte SMTP-Server und Absenderadresse angeben – ohne Mailversand kommt kein Anmeldecode an.';
        }

        // Erster Zugang
        $zugang = ['name' => $t('admin_name', 120), 'email' => mb_strtolower($t('admin_email', 120)), 'kind' => ($e['admin_kind'] ?? 'rolle') === 'person' ? 'person' : 'rolle'];
        if ($zugang['name'] === '' || ! filter_var($zugang['email'], FILTER_VALIDATE_EMAIL)) {
            $fehler['admin'] = 'Bitte Name und E-Mail-Adresse für den ersten Zugang angeben.';
        }

        foreach (['db' => $db, 'mail' => $mail] as $bereich => $werte) {
            foreach ($werte as $wert) {
                if (str_contains((string) $wert, "\n") || (str_contains((string) $wert, "'") && str_contains((string) $wert, '${'))) {
                    $fehler[$bereich] = 'Ein Wert enthält Zeichen, die nicht in die Konfiguration passen. Bitte ein anderes Passwort wählen.';
                }
            }
        }

        return ['angaben' => $angaben + ['_db' => $db, '_mail' => $mail, '_zugang' => $zugang], 'fehler' => $fehler];
    }

    public static function schluessel(string $stamm): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', strtr($stamm, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue', 'ß' => 'ss'])), '-'));

        return 'stamm-'.(substr($slug, 0, 40) ?: 'neu');
    }

    /**
     * Alles einrichten. Liefert das Backup-Passwort (einmalig anzeigen).
     *
     * @param  array<string, mixed>  $angaben  Ergebnis von pruefen()
     * @param  array{tmp_name: string, size: int}|null  $logo
     *
     * @throws RuntimeException
     */
    public function einrichten(array $angaben, ?array $logo = null): string
    {
        if ($this->eingerichtet()) {
            throw new RuntimeException('Diese Installation ist bereits eingerichtet.');
        }
        ['_db' => $db, '_mail' => $mail, '_zugang' => $zugang] = $angaben;
        unset($angaben['_db'], $angaben['_mail'], $angaben['_zugang']);

        $schluessel = self::schluessel((string) $angaben['stamm']);
        $angaben += $this->logo($schluessel, (string) $angaben['stamm'], $logo);
        $this->instanzAnlegen($schluessel, $angaben);
        foreach (['storage/app/public', 'storage/app/private', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs'] as $ordner) {
            @mkdir($this->basis.'/'.$ordner, 0775, true);
        }

        if ($db['art'] === 'mariadb') {
            $this->mariadbPruefen($db);
        } else {
            @unlink($this->basis.'/database/database.sqlite');
            touch($this->basis.'/database/database.sqlite');
        }

        $backupPasswort = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        $env = $this->env($schluessel, $angaben, $db, $mail, 'base64:'.base64_encode(random_bytes(32)), $backupPasswort);

        // Laravel mit den neuen Werten starten, ohne die .env schon zu schreiben.
        foreach ($this->parseEnv($env) as $k => $v) {
            putenv("{$k}={$v}");
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }
        try {
            $app = require $this->basis.'/bootstrap/app.php';
            $kernel = $app->make(Kernel::class);
            $kernel->bootstrap();
            foreach ([['migrate', ['--force' => true]], ['db:seed', ['--force' => true]], ['kluft:assets', []]] as [$befehl, $optionen]) {
                if ($kernel->call($befehl, $optionen) !== 0) {
                    throw new RuntimeException('„'.$befehl.'“ ist fehlgeschlagen: '.trim($kernel->output()));
                }
            }
            $user = User::query()->create([
                'name' => $zugang['name'], 'email' => $zugang['email'], 'status' => 'active',
                'login' => 'email', 'kind' => $zugang['kind'],
            ]);
            $user->roleAssignments()->create(['role' => 'admin', 'scope_type' => 'global', 'scope_key' => null]);
        } catch (Throwable $e) {
            throw new RuntimeException('Einrichtung abgebrochen: '.$e->getMessage(), previous: $e);
        }

        // Zuletzt die .env: ab jetzt gilt die Installation als eingerichtet.
        file_put_contents($this->basis.'/.env.neu', $env);
        @chmod($this->basis.'/.env.neu', 0640);
        rename($this->basis.'/.env.neu', $this->basis.'/.env');
        @unlink($this->basis.'/'.self::CODE_DATEI);

        return $backupPasswort;
    }

    /**
     * @param  array<string, mixed>  $angaben
     * @param  array<string, string>  $db
     * @param  array<string, string>  $mail
     */
    public function env(string $schluessel, array $angaben, array $db, array $mail, string $appKey, string $backupPasswort): string
    {
        $updates = $this->updateVorgaben();
        $werte = [
            'KLUFT_INSTANZ' => $schluessel,
            'APP_NAME' => 'DPSG Stamm '.$angaben['stamm'],
            'APP_ENV' => 'production',
            'APP_KEY' => $appKey,
            'APP_DEBUG' => 'false',
            'APP_URL' => 'https://'.$angaben['domain'],
            'APP_LOCALE' => 'de',
            'APP_FALLBACK_LOCALE' => 'de',
            'LOG_CHANNEL' => 'stack',
            'LOG_STACK' => 'single',
            'LOG_LEVEL' => 'error',
            'SESSION_DRIVER' => 'file',
            'SESSION_LIFETIME' => '480',
            'CACHE_STORE' => 'file',
            'QUEUE_CONNECTION' => 'database',
            'MAIL_MAILER' => 'smtp',
            'MAIL_SCHEME' => $mail['port'] === '465' ? 'smtps' : 'smtp',
            'MAIL_HOST' => $mail['host'],
            'MAIL_PORT' => $mail['port'],
            'MAIL_USERNAME' => $mail['user'],
            'MAIL_PASSWORD' => $mail['pass'],
            'MAIL_FROM_ADDRESS' => $mail['from'],
            'MAIL_FROM_NAME' => 'DPSG Stamm '.$angaben['stamm'],
            'BACKUP_PASSWORD' => $backupPasswort,
            'KLUFT_UPDATES' => $updates ? 'manual' : 'off',
            'KLUFT_UPDATE_URL' => $updates['manifest_url'] ?? '',
            'KLUFT_UPDATE_PUBLIC_KEY' => $updates['public_key'] ?? '',
        ];
        $werte += $db['art'] === 'mariadb'
            ? ['DB_CONNECTION' => 'mariadb', 'DB_HOST' => $db['host'], 'DB_PORT' => $db['port'], 'DB_DATABASE' => $db['name'], 'DB_USERNAME' => $db['user'], 'DB_PASSWORD' => $db['pass']]
            : ['DB_CONNECTION' => 'sqlite'];

        $zeilen = ['# Angelegt vom Kluft-Einrichtungsassistenten am '.date('d.m.Y H:i').'. Nicht weitergeben.'];
        foreach ($werte as $k => $v) {
            $zeilen[] = $k.'='.self::wert((string) $v);
        }

        return implode("\n", $zeilen)."\n";
    }

    /** Wert für die .env: ohne Sonderzeichen roh, sonst in Anführungszeichen. */
    public static function wert(string $v): string
    {
        if ($v === '' || preg_match('/^[A-Za-z0-9_.:\/@+=-]+$/', $v)) {
            return $v;
        }
        if (! str_contains($v, "'")) {
            return "'".$v."'";
        }

        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $v).'"';
    }

    /**
     * @return array<string, string>
     */
    private function parseEnv(string $env): array
    {
        $werte = [];
        foreach (explode("\n", $env) as $zeile) {
            if ($zeile === '' || str_starts_with($zeile, '#') || ! str_contains($zeile, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $zeile, 2);
            if (strlen($v) >= 2 && $v[0] === "'" && str_ends_with($v, "'")) {
                $v = substr($v, 1, -1);
            } elseif (strlen($v) >= 2 && $v[0] === '"' && str_ends_with($v, '"')) {
                $v = str_replace(['\\"', '\\\\'], ['"', '\\'], substr($v, 1, -1));
            }
            $werte[$k] = $v;
        }

        return $werte;
    }

    /** @return array{manifest_url: string, public_key: string}|null */
    private function updateVorgaben(): ?array
    {
        $datei = $this->basis.'/resources/einrichtung/updates.json';
        $werte = is_file($datei) ? json_decode((string) file_get_contents($datei), true) : null;

        return is_array($werte) && filled($werte['manifest_url'] ?? null) && filled($werte['public_key'] ?? null)
            ? ['manifest_url' => (string) $werte['manifest_url'], 'public_key' => (string) $werte['public_key']]
            : null;
    }

    /**
     * @param  array<string, mixed>  $angaben
     */
    private function instanzAnlegen(string $schluessel, array $angaben): void
    {
        $ordner = $this->basis.'/instanzen/'.$schluessel;
        @mkdir($ordner.'/public/images', 0775, true);
        if (! copy($this->basis.'/resources/einrichtung/instanz-config.php', $ordner.'/config.php')) {
            throw new RuntimeException('Instanz-Ordner nicht beschreibbar: '.$ordner);
        }
        file_put_contents($ordner.'/angaben.json', json_encode($angaben, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Hochgeladenes Logo (PNG, JPG, WebP bis 2 MB) übernehmen, sonst ein
     * schlichtes Schriftlogo erzeugen.
     *
     * @param  array{tmp_name: string, size: int}|null  $datei
     * @return array{logo: string, logo_size: array{0: int, 1: int}}
     */
    private function logo(string $schluessel, string $stamm, ?array $datei): array
    {
        $ziel = $this->basis.'/instanzen/'.$schluessel.'/public/images';
        @mkdir($ziel, 0775, true);

        if ($datei && is_uploaded_file($datei['tmp_name']) && $datei['size'] <= 2 * 1024 * 1024) {
            $info = @getimagesize($datei['tmp_name']);
            $endung = match ($info[2] ?? null) {
                IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp', default => null,
            };
            if ($endung && move_uploaded_file($datei['tmp_name'], $ziel.'/logo.'.$endung)) {
                return ['logo' => 'instanz/images/logo.'.$endung, 'logo_size' => [(int) $info[0], (int) $info[1]]];
            }
        }

        $name = htmlspecialchars($stamm, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        file_put_contents($ziel.'/logo.svg', <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 200" role="img" aria-label="DPSG Stamm {$name}">
              <rect width="600" height="200" fill="#ffffff"/>
              <text x="30" y="88" font-family="Arial, Helvetica, sans-serif" font-size="64" font-weight="700" fill="#003056">dpsg</text>
              <text x="30" y="150" font-family="Arial, Helvetica, sans-serif" font-size="40" fill="#003056">Stamm {$name}</text>
              <path d="M30 172 Q300 158 570 170" stroke="#810a1a" stroke-width="10" fill="none" stroke-linecap="round"/>
            </svg>
            SVG);

        return ['logo' => 'instanz/images/logo.svg', 'logo_size' => [600, 200]];
    }

    /**
     * @param  array<string, string>  $db
     */
    private function mariadbPruefen(array $db): void
    {
        try {
            $pdo = new PDO("mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4", $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10]);
            $abfrage = $pdo->query('SHOW TABLES');
            $tabellen = $abfrage === false ? [] : $abfrage->fetchAll();
        } catch (Throwable $e) {
            throw new RuntimeException('Keine Verbindung zur Datenbank: '.$e->getMessage());
        }
        if ($tabellen !== []) {
            throw new RuntimeException('Die Datenbank ist nicht leer. Bitte eine neue, leere Datenbank anlegen – bestehende Daten werden nie überschrieben.');
        }
    }
}
