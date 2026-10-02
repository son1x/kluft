<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Entpackt ein Backup in ein eigenes Verzeichnis. Die laufende Seite wird
 * bewusst nicht automatisch überschrieben – die Schritte zum Einspielen
 * stehen in der Ausgabe.
 */
class BackupRestoreCommand extends Command
{
    protected $signature = 'backup:restore {file : Pfad zur .dpsgbak-Datei} {--to= : Zielverzeichnis}';

    protected $description = 'Entschlüsselt und entpackt ein Backup in ein separates Verzeichnis';

    public function handle(BackupService $backups): int
    {
        $target = $this->option('to') ?: storage_path('app/private/restore-'.now()->format('Y-m-d-His'));

        try {
            $backups->restoreTo((string) $this->argument('file'), $target);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Backup entpackt nach: {$target}");
        $this->line('Zum Einspielen: Seite in den Wartungsmodus setzen (php artisan down), database/, storage/app/private/ und public/uploads/ aus diesem Verzeichnis an die Originalorte kopieren, dann php artisan media:variants --all und php artisan up.');

        return self::SUCCESS;
    }
}
