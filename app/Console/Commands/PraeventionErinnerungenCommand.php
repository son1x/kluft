<?php

namespace App\Console\Commands;

use App\Mail\PraeventionErinnerungMail;
use App\Models\PraeventionNachweis;
use App\Support\AuditLogger;
use App\Support\Features;
use App\Support\Praevention;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Präventions-Nachweise: 90 und 30 Tage vor Ablauf ein Hinweis an die Person
 * und eine Übersicht an den Vorstand; seit einem Jahr abgelaufene Nachweise
 * werden gelöscht. Läuft täglich.
 */
class PraeventionErinnerungenCommand extends Command
{
    protected $signature = 'praevention:erinnerungen';

    protected $description = 'Erinnerungen an ablaufende Präventions-Nachweise senden und alte löschen';

    public function handle(): int
    {
        $geloescht = PraeventionNachweis::query()
            ->whereDate('gueltig_bis', '<', today()->subDays(Praevention::LOESCHEN_NACH_TAGEN))->delete();
        if ($geloescht > 0) {
            AuditLogger::log('praevention.geloescht:'.$geloescht);
        }

        if (! Features::enabled(Features::PRAEVENTION)) {
            return self::SUCCESS;
        }

        $faellig = PraeventionNachweis::query()
            ->whereDate('gueltig_bis', '>=', today())
            ->whereDate('gueltig_bis', '<=', today()->addDays(max(Praevention::ERINNERUNG_TAGE)))
            ->orderBy('gueltig_bis')->get()
            ->filter(function (PraeventionNachweis $n) {
                $stufe = $this->stufe($n);

                return $n->erinnert_stufe === null || $n->erinnert_stufe > $stufe;
            });

        $eintrag = fn (PraeventionNachweis $n) => [
            'name' => $n->name, 'art' => $n->artLabel(), 'bezeichnung' => $n->bezeichnung,
            'bis' => $n->gueltig_bis->format('d.m.Y'), 'tage' => $n->restTage(),
        ];

        foreach ($faellig as $n) {
            if (filled($n->email)) {
                Mail::to($n->email, $n->name)->send(new PraeventionErinnerungMail([$eintrag($n)]));
            }
            $n->forceFill(['erinnert_stufe' => $this->stufe($n)])->saveQuietly();
        }

        if ($faellig->isNotEmpty()) {
            $uebersicht = $faellig->map($eintrag)->values()->all();
            foreach (Praevention::verantwortliche() as $user) {
                Mail::to($user->email, $user->name)->send(new PraeventionErinnerungMail($uebersicht, uebersicht: true));
            }
        }

        $this->info($faellig->count().' Erinnerungen, '.$geloescht.' gelöscht.');

        return self::SUCCESS;
    }

    /** Kleinste Erinnerungsstufe, die schon erreicht ist (90 oder 30). */
    private function stufe(PraeventionNachweis $n): int
    {
        $tage = $n->restTage();

        return (int) collect(Praevention::ERINNERUNG_TAGE)->filter(fn (int $t) => $tage <= $t)->min();
    }
}
