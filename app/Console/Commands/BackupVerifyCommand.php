<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Wiederherstellungstest: neuestes Backup entschlüsseln und prüfen
 * (Pflichtenheft 10.2, Abnahmekriterium 10).
 */
class BackupVerifyCommand extends Command
{
    protected $signature = 'backup:verify {file? : Pfad zur .dpsgbak-Datei (Standard: neuestes Backup)}';

    protected $description = 'Prüft, ob ein Backup entschlüsselt und gelesen werden kann';

    public function handle(BackupService $backups): int
    {
        try {
            $result = $backups->verify($this->argument('file'));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($result['checks'] as $check) {
            $this->line(' – '.$check);
        }
        $result['ok'] ? $this->info('Backup '.$result['file'].' ist lesbar.') : $this->error('Backup '.$result['file'].' ist NICHT lesbar.');

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
