<?php

namespace App\Console\Commands;

use App\Mail\JuleicaErinnerungMail;
use App\Models\Person;
use App\Models\User;
use App\Support\Zuschuesse;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Erinnerung zwei Monate vor Ablauf der Juleica (ZU-06): an die Person,
 * ohne Adresse an die Verwaltung. Je Ablaufdatum einmal; vermerkt in den
 * (verschlüsselten) Zuschuss-Angaben der Person.
 */
class ZuschussJuleicaCommand extends Command
{
    protected $signature = 'zuschuss:juleica';

    protected $description = 'Juleica-Erinnerung zwei Monate vor Ablauf';

    public function handle(): int
    {
        if (! Zuschuesse::aktiv()) {
            return self::SUCCESS;
        }
        $n = 0;
        foreach (Person::query()->where('active', true)->whereNotNull('zuschuss_daten')->get() as $person) {
            $daten = (array) $person->zuschuss_daten;
            if (! ($daten['juleica'] ?? false) || blank($daten['juleica_bis'] ?? null) || ($daten['juleica_erinnert'] ?? null) === $daten['juleica_bis']) {
                continue;
            }
            $bis = Carbon::parse((string) $daten['juleica_bis']);
            if ($bis->gt(now()->addMonths(2))) {
                continue;
            }
            $mail = new JuleicaErinnerungMail((string) $person->name, $bis->format('d.m.Y'), $bis->isPast(), blank($person->email));
            if (filled($person->email)) {
                Mail::to($person->email, $person->name)->send($mail);
            } else {
                $verwaltung = User::query()->where('status', 'active')->whereHas('roleAssignments', fn ($q) => $q->where('role', 'verwaltung'))->get();
                foreach ($verwaltung as $user) {
                    Mail::to($user->email, $user->name)->send(clone $mail);
                }
            }
            $person->update(['zuschuss_daten' => [...$daten, 'juleica_erinnert' => $daten['juleica_bis']]]);
            $n++;
        }
        $this->info("Juleica-Erinnerungen: {$n}");

        return self::SUCCESS;
    }
}
