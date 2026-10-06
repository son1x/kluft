<?php

namespace App\Console\Commands;

use App\Services\Mitglieder\MitgliederAbgleich;
use App\Services\Mitglieder\Mitgliederverzeichnis;
use App\Services\Mitglieder\MitgliederverzeichnisException;
use App\Services\Mitglieder\NamiVerzeichnis;
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

        // Persönliche Anmeldung: kein technisches Konto – nur Erreichbarkeit prüfen.
        if (NamiVerzeichnis::persoenlich()) {
            if (! (new NamiVerzeichnis)->erreichbar()) {
                $this->error('NaMi 2: nicht erreichbar.');

                return self::FAILURE;
            }
            $this->info('NaMi 2: erreichbar. Anmeldung persönlich – jede Person prüft mit ihrem eigenen NaMi-Konto.');

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
