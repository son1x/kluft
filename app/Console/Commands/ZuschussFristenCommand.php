<?php

namespace App\Console\Commands;

use App\Filament\Resources\Events\EventResource;
use App\Mail\ZuschussFristMail;
use App\Models\Event;
use App\Models\User;
use App\Support\Zuschuesse;
use App\Support\Zuschuesse\Antrag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Erinnerung an die Antragsfristen (Phase 4): 14 und 3 Tage vor der Frist,
 * solange beim Zuschussgeber nichts als eingereicht vermerkt ist. An den
 * Kontakt der Veranstaltung, sonst an die Verwaltung. Je Stufe einmal.
 */
class ZuschussFristenCommand extends Command
{
    protected $signature = 'zuschuss:fristen';

    protected $description = 'Zuschussanträge: Erinnerung an die Antragsfristen';

    public function handle(): int
    {
        if (! Zuschuesse::aktiv()) {
            return self::SUCCESS;
        }
        $n = 0;
        $heute = now()->startOfDay();
        foreach (Event::query()->where('zuschuss', true)->where('starts_at', '>=', now()->subYear())->get() as $event) {
            $stand = (array) $event->zuschuss_stand;
            $geaendert = false;
            foreach (Antrag::geber($event) as $geber) {
                if (Antrag::status($event, $geber) !== 'offen') {
                    continue;
                }
                $frist = Antrag::frist($event, $geber);
                $tage = (int) $heute->diffInDays($frist, false);
                $erinnert = (array) ($stand[$geber]['erinnert'] ?? []);
                // Fällige Stufe: die kleinste, deren Tage erreicht sind; verpasste Stufen nicht nachholen.
                $stufe = collect(Antrag::ERINNERUNGEN)->sort()->first(fn (int $s) => $tage >= 0 && $tage <= $s);
                if ($stufe === null || in_array($stufe, $erinnert, true)) {
                    continue;
                }
                $mail = new ZuschussFristMail((string) $event->title, Zuschuesse::GEBER[$geber], $frist->format('d.m.Y'), $tage,
                    EventResource::getUrl('zuschuss', ['record' => $event], panel: 'admin'));
                if (filled($event->contact_email)) {
                    Mail::to($event->contact_email, $event->contact_name)->send($mail);
                } else {
                    User::query()->where('status', 'active')->whereHas('roleAssignments', fn ($q) => $q->where('role', 'verwaltung'))->get()
                        ->each(fn (User $u) => Mail::to($u->email, $u->name)->send(clone $mail));
                }
                // Mit der späteren Stufe gilt auch die frühere als erledigt.
                $stand[$geber]['erinnert'] = array_values(array_unique([...$erinnert, ...array_filter(Antrag::ERINNERUNGEN, fn (int $s) => $s >= $stufe)]));
                $geaendert = true;
                $n++;
            }
            if ($geaendert) {
                $event->forceFill(['zuschuss_stand' => $stand])->saveQuietly();
            }
        }
        $this->info("Fristen-Erinnerungen: {$n}");

        return self::SUCCESS;
    }
}
