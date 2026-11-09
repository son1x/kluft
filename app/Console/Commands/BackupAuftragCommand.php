<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupEinspielen;
use Illuminate\Console\Command;

/**
 * Führt einen Auftrag aus Verwaltung → Backups aus (Backup jetzt erstellen
 * bzw. einspielen). Läuft jede Minute im Cronjob, wenn ein Auftrag da ist.
 */
class BackupAuftragCommand extends Command
{
    protected $signature = 'backup:auftrag';

    protected $description = 'Führt einen im Backend angeforderten Backup-Auftrag aus';

    public function handle(BackupEinspielen $einspielen): int
    {
        if (! $einspielen->auftragAusfuehren()) {
            $this->line('Kein Auftrag.');
        }

        return self::SUCCESS;
    }
}
