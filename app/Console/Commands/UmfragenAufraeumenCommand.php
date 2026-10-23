<?php

namespace App\Console\Commands;

use App\Models\Umfrage;
use Illuminate\Console\Command;

/**
 * Löschfristen der Umfragen (docs/umfragen.md): Antworten nach
 * „aufbewahrung_monate“ ab Ende bzw. letzter Änderung, Gewinnspiel-Einträge
 * drei Monate nach dem Ende. Die Umfrage selbst (Fragen) bleibt.
 */
class UmfragenAufraeumenCommand extends Command
{
    protected $signature = 'umfragen:aufraeumen';

    protected $description = 'Löscht Umfrage-Antworten und Gewinnspiel-Einträge nach Ablauf der Frist';

    public function handle(): int
    {
        $antworten = 0;
        $gewinnspiel = 0;
        foreach (Umfrage::query()->umfragen()->get() as $umfrage) {
            $ende = $umfrage->endet_am ?? $umfrage->updated_at;
            if ($ende !== null && $ende->copy()->addMonths($umfrage->aufbewahrung_monate)->isPast()) {
                $antworten += $umfrage->antworten()->delete();
            }
            if ($ende !== null && $ende->copy()->addMonths(3)->isPast()) {
                $gewinnspiel += $umfrage->gewinnspielEintraege()->delete();
            }
        }
        $this->info("{$antworten} Antworten, {$gewinnspiel} Gewinnspiel-Einträge gelöscht.");

        return self::SUCCESS;
    }
}
