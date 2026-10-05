<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use ZipArchive;

/**
 * Backups nach Pflichtenheft 10.2: Datenbank + Medien + private Dateien
 * (Georgsbote-PDFs, APP_KEY für verschlüsselte Felder) in einem Archiv,
 * mit eigenem Backup-Passwort verschlüsselt, 14 Tage aufbewahrt, optional
 * zusätzlich per WebDAV außerhalb des Webspace abgelegt.
 *
 * Dateiformat (*.dpsgbak): "DPSGBAK1" | Salt (16) | opslimit, memlimit
 * (je uint32) | Secretstream-Header (24) | Blöcke à 64 KiB + 17 Byte.
 */
class BackupService
{
    public const LAST_RUN_KEY = 'backup:last_run';

    public const LAST_VERIFY_KEY = 'backup:last_verify';

    private const MAGIC = 'DPSGBAK1';

    private const CHUNK = 65536;

    public function directory(): string
    {
        return (string) (config('dpsg.backup.path') ?: storage_path('app/private/backups'));
    }

    /**
     * Erstellt ein verschlüsseltes Backup und gibt den Pfad zurück.
     */
    public function create(): string
    {
        $password = $this->password();
        File::ensureDirectoryExists($this->directory(), 0700);

        $stamp = now()->format('Y-m-d-His');
        $work = $this->directory().'/tmp-'.Str::random(8);
        File::ensureDirectoryExists($work, 0700);

        try {
            $dbFile = $this->dumpDatabase($work);
            $zipPath = $work.'/backup.zip';
            $this->buildArchive($zipPath, $dbFile);

            $target = $this->directory()."/backup-{$stamp}.dpsgbak";
            $this->encryptFile($zipPath, $target, $password);
            chmod($target, 0600);
        } finally {
            File::deleteDirectory($work);
        }

        $this->rotateLocal();
        $uploaded = $this->uploadOffsite($target);

        self::remember(self::LAST_RUN_KEY, [
            'at' => now()->toIso8601String(),
            'file' => basename($target),
            'size' => filesize($target),
            'offsite' => $uploaded,
        ]);

        return $target;
    }

    /**
     * Entschlüsselt ein Backup und prüft Archiv und Datenbank
     * (Wiederherstellungstest, Abnahmekriterium 10).
     *
     * @return array{ok: bool, file: string, manifest: array<string, mixed>, checks: list<string>}
     */
    public function verify(?string $file = null): array
    {
        $file ??= $this->latest() ?? throw new RuntimeException('Es gibt noch kein Backup.');
        $work = $this->directory().'/verify-'.Str::random(8);
        File::ensureDirectoryExists($work, 0700);
        $checks = [];

        try {
            $zipPath = $work.'/backup.zip';
            $this->decryptFile($file, $zipPath, $this->password());
            $checks[] = 'Entschlüsselung ok';

            $zip = new ZipArchive;
            if ($zip->open($zipPath) !== true) {
                throw new RuntimeException('Archiv beschädigt.');
            }
            $manifest = json_decode((string) $zip->getFromName('manifest.json'), true) ?: throw new RuntimeException('manifest.json fehlt.');
            $checks[] = 'Archiv ok ('.$zip->numFiles.' Dateien)';

            if ($manifest['driver'] === 'sqlite') {
                $zip->extractTo($work, 'database/database.sqlite');
                $pdo = new PDO('sqlite:'.$work.'/database/database.sqlite');
                $integrity = $pdo->query('PRAGMA integrity_check')->fetchColumn();
                if ($integrity !== 'ok') {
                    throw new RuntimeException('Datenbank-Integritätsprüfung: '.$integrity);
                }
                $users = (int) $pdo->query('select count(*) from users')->fetchColumn();
                $checks[] = "Datenbank ok ({$users} Benutzer)";
            } else {
                $sql = (string) $zip->getFromName('database/database.sql');
                if (! str_contains($sql, 'CREATE TABLE') || ! str_contains($sql, 'Dump completed')) {
                    throw new RuntimeException('SQL-Dump unvollständig.');
                }
                $checks[] = 'SQL-Dump vollständig';
            }
            $zip->close();

            $result = ['ok' => true, 'file' => basename($file), 'manifest' => $manifest, 'checks' => $checks];
        } catch (RuntimeException $e) {
            $result = ['ok' => false, 'file' => basename($file), 'manifest' => [], 'checks' => [...$checks, 'FEHLER: '.$e->getMessage()]];
        } finally {
            File::deleteDirectory($work);
        }

        self::remember(self::LAST_VERIFY_KEY, [...$result, 'at' => now()->toIso8601String()]);

        return $result;
    }

    /**
     * Entpackt ein Backup in ein eigenes Verzeichnis (überschreibt nichts
     * an der laufenden Seite).
     */
    public function restoreTo(string $file, string $target): void
    {
        File::ensureDirectoryExists($target, 0700);
        $zipPath = $target.'/backup.zip';
        $this->decryptFile($file, $zipPath, $this->password());

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true || ! $zip->extractTo($target)) {
            throw new RuntimeException('Archiv konnte nicht entpackt werden.');
        }
        $zip->close();
        File::delete($zipPath);
    }

    /**
     * Letztes Backup bzw. letzte Wiederherstellungsprobe. Steht im Cache und
     * zusätzlich in einer Datei, weil jeder Deploy den Cache leert.
     *
     * @return array<string, mixed>|null
     */
    public static function lastRun(): ?array
    {
        return Cache::get(self::LAST_RUN_KEY) ?? (self::stateFile()[self::LAST_RUN_KEY] ?? null);
    }

    /** @return array<string, mixed>|null */
    public static function lastVerify(): ?array
    {
        return Cache::get(self::LAST_VERIFY_KEY) ?? (self::stateFile()[self::LAST_VERIFY_KEY] ?? null);
    }

    /** @param  array<string, mixed>  $value */
    private static function remember(string $key, array $value): void
    {
        Cache::forever($key, $value);
        $state = [...self::stateFile(), $key => $value];
        File::ensureDirectoryExists(dirname(self::statePath()), 0700);
        File::put(self::statePath(), (string) json_encode($state, JSON_PRETTY_PRINT));
    }

    /** @return array<string, mixed> */
    private static function stateFile(): array
    {
        $path = self::statePath();

        return is_file($path) ? (array) json_decode((string) file_get_contents($path), true) : [];
    }

    private static function statePath(): string
    {
        return (config('dpsg.backup.path') ?: storage_path('app/private/backups')).'/status.json';
    }

    public function latest(): ?string
    {
        $files = glob($this->directory().'/backup-*.dpsgbak') ?: [];
        sort($files);

        return end($files) ?: null;
    }

    private function password(): string
    {
        $password = (string) config('dpsg.backup.password');
        if (mb_strlen($password) < 16) {
            throw new RuntimeException('BACKUP_PASSWORD fehlt oder ist kürzer als 16 Zeichen.');
        }

        return $password;
    }

    private function dumpDatabase(string $work): string
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");
        File::ensureDirectoryExists($work.'/database');

        if ($config['driver'] === 'sqlite') {
            // Konsistente Kopie auch bei laufendem Betrieb.
            $target = $work.'/database/database.sqlite';
            DB::connection($connection)->statement('VACUUM INTO ?', [$target]);

            return $target;
        }

        if (in_array($config['driver'], ['mysql', 'mariadb'], true)) {
            $target = $work.'/database/database.sql';
            $result = Process::env(['MYSQL_PWD' => (string) $config['password']])
                ->timeout(600)
                ->run([
                    'mysqldump', '--single-transaction', '--quick', '--no-tablespaces', '--default-character-set=utf8mb4',
                    '-h', (string) $config['host'], '-P', (string) $config['port'], '-u', (string) $config['username'],
                    '--result-file='.$target, (string) $config['database'],
                ]);
            if ($result->failed()) {
                throw new RuntimeException('mysqldump fehlgeschlagen: '.Str::limit($result->errorOutput(), 300));
            }

            return $target;
        }

        throw new RuntimeException("Datenbanktreiber {$config['driver']} wird nicht unterstützt.");
    }

    private function buildArchive(string $zipPath, string $dbFile): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Archiv konnte nicht angelegt werden.');
        }

        $zip->addFile($dbFile, 'database/'.basename($dbFile));
        $files = 1;

        $sources = [
            'storage/app/private' => ['backups'],
            'public/uploads' => ['media/variants', 'instagram'],
        ];
        foreach ($sources as $dir => $exclude) {
            $base = base_path($dir);
            if (! is_dir($base)) {
                continue;
            }
            foreach (File::allFiles($base, true) as $file) {
                $relative = str_replace('\\', '/', $file->getRelativePathname());
                foreach ($exclude as $prefix) {
                    if (str_starts_with($relative, $prefix.'/')) {
                        continue 2;
                    }
                }
                $zip->addFile($file->getPathname(), $dir.'/'.$relative);
                $files++;
            }
        }

        $zip->addFromString('manifest.json', json_encode([
            'created_at' => now()->toIso8601String(),
            'app_url' => config('app.url'),
            'driver' => config('database.connections.'.config('database.default').'.driver'),
            'files' => $files,
            'counts' => collect(['users', 'pages', 'posts', 'events', 'event_registrations', 'gruppierungen', 'media'])
                ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->count()])->all(),
        ], JSON_PRETTY_PRINT));

        if (! $zip->close()) {
            throw new RuntimeException('Archiv konnte nicht geschrieben werden.');
        }
    }

    public function encryptFile(string $source, string $target, string $password): void
    {
        $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $ops = SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE;
        $mem = SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE;
        $key = sodium_crypto_pwhash(SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES, $password, $salt, $ops, $mem, SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
        sodium_memzero($key);

        $in = fopen($source, 'rb');
        $out = fopen($target, 'wb');
        fwrite($out, self::MAGIC.$salt.pack('NN', $ops, $mem).$header);

        $chunk = fread($in, self::CHUNK);
        do {
            $next = fread($in, self::CHUNK);
            $last = $next === '' || $next === false;
            fwrite($out, sodium_crypto_secretstream_xchacha20poly1305_push(
                $state, (string) $chunk, '',
                $last ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE,
            ));
            $chunk = $next;
        } while (! $last);

        fclose($in);
        fclose($out);
    }

    public function decryptFile(string $source, string $target, string $password): void
    {
        $in = fopen($source, 'rb') ?: throw new RuntimeException('Backup-Datei nicht lesbar.');
        if (fread($in, strlen(self::MAGIC)) !== self::MAGIC) {
            throw new RuntimeException('Keine Backup-Datei dieses Systems.');
        }
        $salt = fread($in, SODIUM_CRYPTO_PWHASH_SALTBYTES);
        ['ops' => $ops, 'mem' => $mem] = unpack('Nops/Nmem', fread($in, 8));
        $header = fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
        $key = sodium_crypto_pwhash(SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES, $password, $salt, $ops, $mem, SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
        $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);
        sodium_memzero($key);

        $out = fopen($target, 'wb');
        $final = false;
        while (! feof($in)) {
            $block = fread($in, self::CHUNK + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES);
            if ($block === '' || $block === false) {
                break;
            }
            $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $block);
            if ($result === false) {
                fclose($in);
                fclose($out);
                @unlink($target);
                throw new RuntimeException('Entschlüsselung fehlgeschlagen (falsches Passwort oder beschädigte Datei).');
            }
            fwrite($out, $result[0]);
            $final = $result[1] === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
        }
        fclose($in);
        fclose($out);

        if (! $final) {
            @unlink($target);
            throw new RuntimeException('Backup-Datei unvollständig.');
        }
    }

    /**
     * Löscht Backups älter als die Aufbewahrungsfrist; die drei neuesten
     * bleiben immer erhalten.
     */
    private function rotateLocal(): void
    {
        $files = glob($this->directory().'/backup-*.dpsgbak') ?: [];
        rsort($files);
        $cutoff = now()->subDays((int) config('dpsg.backup.keep_days', 14))->format('Y-m-d');

        foreach (array_slice($files, 3) as $file) {
            if (preg_match('/backup-(\d{4}-\d{2}-\d{2})-/', basename($file), $m) && $m[1] < $cutoff) {
                File::delete($file);
            }
        }
    }

    /**
     * Kopie außerhalb des Webspace per WebDAV (z. B. Nextcloud-Ordner mit
     * App-Passwort). Fehler dort brechen das lokale Backup nicht ab.
     */
    private function uploadOffsite(string $file): bool
    {
        $url = rtrim((string) config('dpsg.backup.webdav_url'), '/');
        if ($url === '') {
            return false;
        }

        $http = fn () => Http::withBasicAuth((string) config('dpsg.backup.webdav_user'), (string) config('dpsg.backup.webdav_password'))->timeout(900);

        try {
            $stream = fopen($file, 'rb');
            $response = $http()->withBody($stream, 'application/octet-stream')->put($url.'/'.basename($file));
            if (! $response->successful()) {
                throw new RuntimeException('WebDAV-Upload: HTTP '.$response->status());
            }

            // Alte Kopien auf dem WebDAV-Speicher ebenfalls nach 14 Tagen entfernen.
            $listing = $http()->withHeaders(['Depth' => '1'])->send('PROPFIND', $url.'/');
            $cutoff = now()->subDays((int) config('dpsg.backup.keep_days', 14))->format('Y-m-d');
            preg_match_all('/backup-(\d{4}-\d{2}-\d{2})-\d{6}\.dpsgbak/', $listing->body(), $matches, PREG_SET_ORDER);
            foreach (collect($matches)->unique(0) as [$name, $date]) {
                if ($date < $cutoff) {
                    $http()->delete($url.'/'.$name);
                }
            }

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }
}
