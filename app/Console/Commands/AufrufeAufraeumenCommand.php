<?php

namespace App\Console\Commands;

use App\Models\Seitenaufruf;
use App\Support\Aufrufe;
use Illuminate\Console\Command;

/**
 * Seitenaufrufe nach 25 Monaten löschen (docs/aufrufe.md); der
 * Vorjahresvergleich bleibt damit möglich.
 */
class AufrufeAufraeumenCommand extends Command
{
    protected $signature = 'aufrufe:aufraeumen';

    protected $description = 'Löscht Zähler der Seitenaufrufe, die älter als 25 Monate sind';

    public function handle(): int
    {
        $grenze = today()->subMonthsNoOverflow(Aufrufe::MONATE)->toDateString();
        $geloescht = Seitenaufruf::query()->where('datum', '<', $grenze)->delete();

        $this->info("Gelöscht: {$geloescht} Zähler vor dem {$grenze}.");

        return self::SUCCESS;
    }
}
