<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Kopiert Logo, Favicon und Bilder der aktiven Instanz
 * (instanzen/<schlüssel>/public) nach public/instanz, damit der Webserver
 * sie direkt ausliefert. Läuft beim Deploy und in der CI.
 */
class KluftAssetsCommand extends Command
{
    protected $signature = 'kluft:assets';

    protected $description = 'Bilder der Kluft-Instanz nach public/instanz kopieren';

    public function handle(): int
    {
        $instanz = (string) config('dpsg.instanz');
        $quelle = base_path('instanzen/'.$instanz.'/public');
        $ziel = public_path('instanz');

        File::deleteDirectory($ziel);
        if (! File::isDirectory($quelle)) {
            $this->info("Instanz {$instanz}: keine eigenen Bilder.");

            return self::SUCCESS;
        }

        File::copyDirectory($quelle, $ziel);
        $this->info("Instanz {$instanz}: ".count(File::allFiles($ziel)).' Dateien nach public/instanz kopiert.');

        return self::SUCCESS;
    }
}
