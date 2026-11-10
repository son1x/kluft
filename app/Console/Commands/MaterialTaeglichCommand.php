<?php

namespace App\Console\Commands;

use App\Mail\MaterialMail;
use App\Models\MaterialReservierung;
use App\Support\AuditLogger;
use App\Support\Materialverleih;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Materialverleih täglich: Erinnerung an die Rückgabe am Tag vorher;
 * erledigte Reservierungen sechs Monate nach dem Ende löschen.
 */
class MaterialTaeglichCommand extends Command
{
    protected $signature = 'material:taeglich';

    protected $description = 'Rückgabe-Erinnerungen senden und alte Reservierungen löschen';

    public function handle(): int
    {
        $geloescht = MaterialReservierung::query()->whereIn('status', ['zurueck', 'abgelehnt'])
            ->whereDate('bis', '<', today()->subMonths(Materialverleih::LOESCHEN_MONATE))->delete();
        if ($geloescht > 0) {
            AuditLogger::log('material.geloescht:'.$geloescht);
        }

        $erinnert = 0;
        if (Materialverleih::aktiv()) {
            $faellig = MaterialReservierung::query()->with('material')->where('status', 'ausgegeben')
                ->whereDate('bis', today()->addDay())->whereNull('erinnert_am')->whereNotNull('email')->get();
            foreach ($faellig as $r) {
                Mail::to($r->email, $r->name)->send(new MaterialMail($r, 'rueckgabe'));
                $r->update(['erinnert_am' => today()]);
                $erinnert++;
            }
        }

        $this->info("{$erinnert} Erinnerungen, {$geloescht} gelöscht.");

        return self::SUCCESS;
    }
}
