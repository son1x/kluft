<?php

namespace App\Console\Commands;

use App\Support\TeilnehmendeNachrichten;
use Illuminate\Console\Command;

/** Erinnerungen vor Veranstaltungen verschicken (täglich, docs/veranstaltungen.md). */
class ErinnerungenCommand extends Command
{
    protected $signature = 'veranstaltungen:erinnerungen';

    protected $description = 'Verschickt fällige Erinnerungen an die Teilnehmenden von Veranstaltungen';

    public function handle(): int
    {
        $this->info(TeilnehmendeNachrichten::erinnerungenSenden().' Veranstaltung(en) erinnert.');

        return self::SUCCESS;
    }
}
