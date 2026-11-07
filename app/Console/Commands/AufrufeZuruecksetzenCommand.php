<?php

namespace App\Console\Commands;

use App\Models\Seitenaufruf;
use App\Support\AuditLogger;
use Illuminate\Console\Command;

/**
 * Alle Zähler der Seitenaufrufe löschen, z. B. nach dem Kopieren von Test
 * auf Live (docs/livegang.md), damit die Statistik bei null beginnt.
 */
class AufrufeZuruecksetzenCommand extends Command
{
    protected $signature = 'aufrufe:zuruecksetzen {--force : Ohne Rückfrage}';

    protected $description = 'Löscht alle Zähler der Seitenaufrufe (Statistik beginnt bei null)';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Alle Zähler der Seitenaufrufe löschen?')) {
            $this->info('Abgebrochen.');

            return self::SUCCESS;
        }

        $geloescht = Seitenaufruf::query()->delete();
        AuditLogger::log('aufrufe.zuruecksetzen:'.$geloescht);

        $this->info("Gelöscht: {$geloescht} Zähler. Gezählte Aufrufe jetzt: ".(int) Seitenaufruf::query()->sum('aufrufe').'.');

        return self::SUCCESS;
    }
}
