<?php

namespace App\Console\Commands;

use App\Models\Shop\Runde;
use App\Support\Sammelbestellung;
use App\Support\SammelbestellungAblauf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Sammelbestellung täglich (docs/pflichtenheft-sammelbestellung.md): Erinnerung
 * einen Tag vor dem Bestelldatum, Hinweis an die Sammelbestellung am
 * Bestelldatum, Löschfristen. Die Löschfristen laufen auch bei
 * ausgeschalteter Funktion.
 */
class SammelbestellungTaeglichCommand extends Command
{
    protected $signature = 'sammelbestellung:taeglich';

    protected $description = 'Sammelbestellung: Zahlungserinnerung, Hinweis am Bestelldatum, Löschfristen';

    public function handle(): int
    {
        if (! Schema::hasTable('shop_bestellungen')) {
            return self::SUCCESS;
        }
        if (Sammelbestellung::aktiv() && ($runde = Runde::offene())) {
            if ($runde->bestelldatum->isTomorrow()) {
                $this->info('Erinnerungen: '.SammelbestellungAblauf::erinnern($runde));
            }
            if (SammelbestellungAblauf::faelligMelden($runde)) {
                $this->info('Hinweis an die Sammelbestellung verschickt.');
            }
        }
        $ergebnis = SammelbestellungAblauf::aufraeumen();
        $this->info("Gelöscht (unbestätigt): {$ergebnis['unbestaetigt']}, anonymisiert: {$ergebnis['anonymisiert']}.");

        return self::SUCCESS;
    }
}
