<?php

namespace App\Console\Commands;

use App\Support\AuditLogger;
use App\Support\Unterschriften;
use Illuminate\Console\Command;

/**
 * Vor Ort, Stufe 2 (docs/vor-ort.md): Einzelunterschriften löschen, sobald
 * die Frist nach dem Festschreiben der Teilnahmeliste abgelaufen ist.
 */
class UnterschriftenLoeschenCommand extends Command
{
    protected $signature = 'vor-ort:unterschriften-loeschen';

    protected $description = 'Löscht Einzelunterschriften nach dem Festschreiben der Teilnahmeliste (Frist)';

    public function handle(): int
    {
        $anzahl = Unterschriften::faelligeLoeschen();
        if ($anzahl > 0) {
            AuditLogger::log("vor_ort.unterschriften_geloescht:{$anzahl}");
        }
        $this->info("Gelöscht: {$anzahl} Unterschriften.");

        return self::SUCCESS;
    }
}
