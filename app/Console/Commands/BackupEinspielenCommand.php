<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupEinspielen;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Backup per SSH einspielen (sonst Verwaltung → Backups). Ersetzt Datenbank,
 * storage/app/private und public/uploads durch den Stand im Backup.
 */
class BackupEinspielenCommand extends Command
{
    protected $signature = 'backup:einspielen {datei : Pfad zur .dpsgbak-Datei} {--force : ohne Rückfrage}';

    protected $description = 'Spielt ein Backup ein und ersetzt dabei alle Inhalte dieser Website';

    public function handle(BackupEinspielen $einspielen): int
    {
        if (! $this->option('force') && ! $this->confirm('Alle Inhalte dieser Website ('.config('app.url').') werden durch das Backup ersetzt. Weiter?')) {
            return self::FAILURE;
        }

        try {
            $anzahlen = $einspielen->einspielen((string) $this->argument('datei'));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Eingespielt: '.collect($anzahlen)->map(fn ($n, $t) => "{$t} {$n}")->implode(', '));

        return self::SUCCESS;
    }
}
