<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Prüft nur, ob die konfigurierte Datenbank erreichbar ist (für den
 * Deploy). Bewusst ohne db:show – das liest Statustabellen, die auf
 * Shared Hosting gesperrt sein können.
 */
class DbCheckCommand extends Command
{
    protected $signature = 'db:check';

    protected $description = 'Prüft, ob die konfigurierte Datenbank erreichbar ist';

    public function handle(): int
    {
        try {
            $connection = DB::connection();
            $version = $connection->getDriverName() === 'sqlite'
                ? 'SQLite '.$connection->scalar('select sqlite_version()')
                : $connection->scalar('select version()');
        } catch (Throwable $e) {
            // Nur SQLSTATE und Meldung, ohne Host/Benutzer aus dem Verbindungstext.
            $this->error('Datenbank nicht erreichbar: '.Str::before($e->getMessage(), ' (Connection'));

            return self::FAILURE;
        }

        $this->info('Datenbank erreichbar: '.$connection->getDriverName().' ('.$version.')');

        return self::SUCCESS;
    }
}
