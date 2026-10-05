<?php

namespace App\Console\Commands;

use App\Services\Mitglieder\MitgliederAbgleich;
use App\Services\Mitglieder\Mitgliederverzeichnis;
use App\Services\Mitglieder\MitgliederverzeichnisException;
use Illuminate\Console\Command;

/**
 * Prüft die Verbindung zur Mitgliederverwaltung (Anmeldung und eine Suche
 * nach einem Namen, den es nicht gibt) – gibt keine Mitgliederdaten aus.
 */
class MitgliederCheckCommand extends Command
{
    protected $signature = 'mitglieder:check';

    protected $description = 'Verbindung zur Mitgliederverwaltung (NaMi/hitobito) prüfen';

    public function handle(Mitgliederverzeichnis $verzeichnis): int
    {
        if (! MitgliederAbgleich::eingerichtet()) {
            $this->warn('Mitgliederabgleich aus (MITGLIEDER_DRIVER='.config('mitglieder.driver').').');

            return self::SUCCESS;
        }

        try {
            $verzeichnis->pruefe();
        } catch (MitgliederverzeichnisException $e) {
            $this->error($verzeichnis->name().': '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info($verzeichnis->name().': Verbindung in Ordnung.');

        return self::SUCCESS;
    }
}
