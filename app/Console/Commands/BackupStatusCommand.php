<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;

/**
 * Zeigt, ob die Backups laufen können und zuletzt gelaufen sind – ohne
 * Passwörter oder Zugangsdaten auszugeben (Server-Prüfung in Actions).
 */
class BackupStatusCommand extends Command
{
    protected $signature = 'backup:status';

    protected $description = 'Zeigt Voraussetzungen und letzten Stand der Backups';

    public function handle(BackupService $backups): int
    {
        $password = (string) config('dpsg.backup.password');
        $this->line('BACKUP_PASSWORD: '.match (true) {
            $password === '' => 'FEHLT – ohne Passwort läuft kein Backup',
            mb_strlen($password) < 16 => 'zu kurz (mind. 16 Zeichen)',
            default => 'gesetzt',
        });

        $driver = config('database.connections.'.config('database.default').'.driver');
        $this->line('Datenbank: '.$driver);
        if ($driver !== 'sqlite') {
            $dump = Process::run(['mysqldump', '--version']);
            $this->line('mysqldump: '.($dump->successful() ? trim($dump->output()) : 'NICHT VERFÜGBAR – '.trim($dump->errorOutput())));
        }

        $this->line('Sodium (Verschlüsselung): '.(function_exists('sodium_crypto_secretstream_xchacha20poly1305_init_push') ? 'ja' : 'NEIN'));
        $this->line('ZipArchive: '.(class_exists('ZipArchive') ? 'ja' : 'NEIN'));
        $this->line('Kopie extern (WebDAV): '.(config('dpsg.backup.webdav_url') ? 'eingerichtet' : 'nein (empfohlen: Nextcloud-Ordner)'));

        $files = glob($backups->directory().'/backup-*.dpsgbak') ?: [];
        $this->line('Backups auf dem Server: '.count($files).($files ? ', neuestes '.basename((string) end($files)).' ('.round(filesize((string) end($files)) / 1024 / 1024, 1).' MB)' : ''));

        $run = Cache::get(BackupService::LAST_RUN_KEY);
        $verify = Cache::get(BackupService::LAST_VERIFY_KEY);
        $this->line('Letzter Lauf: '.($run['at'] ?? 'noch nie'));
        $this->line('Letzte Wiederherstellungsprobe: '.($verify ? ($verify['at'] ?? '?').' – '.(($verify['ok'] ?? false) ? 'in Ordnung' : 'FEHLER') : 'noch nie'));

        return self::SUCCESS;
    }
}
