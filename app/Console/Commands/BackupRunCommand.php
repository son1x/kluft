<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Tägliches, verschlüsseltes Backup (Pflichtenheft 10.2, 11.4).
 */
class BackupRunCommand extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Erstellt ein verschlüsseltes Backup von Datenbank und Dateien';

    public function handle(BackupService $backups): int
    {
        try {
            $file = $backups->create();
        } catch (RuntimeException $e) {
            $this->error('Backup fehlgeschlagen: '.$e->getMessage());
            report($e);

            return self::FAILURE;
        }

        $this->info('Backup erstellt: '.basename($file).' ('.round(filesize($file) / 1024 / 1024, 1).' MB)');

        return self::SUCCESS;
    }
}
