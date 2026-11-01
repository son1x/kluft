<?php

namespace App\Console\Commands;

use App\Support\Datenabgleich;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Löschfristen des NaMi-Datenabgleichs (ND-08), auch bei ausgeschalteter Funktion. */
class DatenabgleichAufraeumenCommand extends Command
{
    protected $signature = 'datenabgleich:aufraeumen';

    protected $description = 'NaMi-Datenabgleich: Meldungen und Ergebnisse nach Frist löschen';

    public function handle(): int
    {
        if (! Schema::hasTable('datenmeldungen')) {
            return self::SUCCESS;
        }
        $n = Datenabgleich::aufraeumen();
        $this->info("Gelöscht: {$n['meldungen']} Meldung(en), {$n['zeilen']} Abgleich-Zeile(n).");

        return self::SUCCESS;
    }
}
