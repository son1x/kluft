<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Support\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Löscht als „sensibel“ markierte Angaben (z. B. Gesundheit, Allergien)
 * aus den Anmeldungen, sobald die Veranstaltung `dpsg.sensible_tage` Tage
 * vorbei ist – lange vor der allgemeinen Löschfrist der Anmeldung.
 */
class SensibleAngabenLoeschenCommand extends Command
{
    protected $signature = 'registrations:sensible-loeschen';

    protected $description = 'Löscht sensible Angaben aus Anmeldungen vergangener Veranstaltungen';

    public function handle(): int
    {
        $grenze = now()->subDays(Event::sensibleTage())->startOfDay();
        $gesamt = 0;

        Event::query()
            ->whereNull('sensible_geloescht_at')
            ->where(fn (Builder $q) => $q->where('ends_at', '<', $grenze)
                ->orWhere(fn (Builder $q) => $q->whereNull('ends_at')->where('starts_at', '<', $grenze)))
            ->each(function (Event $event) use (&$gesamt) {
                $felder = $event->sensibleFelder();
                $anzahl = 0;

                if ($felder !== []) {
                    foreach ($event->registrations()->cursor() as $anmeldung) {
                        $data = $anmeldung->data ?? [];
                        $rest = array_diff_key($data, array_flip($felder));
                        if (count($rest) !== count($data)) {
                            $anmeldung->forceFill(['data' => $rest])->saveQuietly();
                            $anzahl++;
                        }
                    }
                }

                $event->forceFill(['sensible_geloescht_at' => now()])->saveQuietly();

                if ($anzahl > 0) {
                    AuditLogger::log("event.sensibel_geloescht:{$anzahl}", null, Event::class, $event->getKey());
                    $gesamt += $anzahl;
                }
            });

        $this->info("Sensible Angaben gelöscht: {$gesamt} Anmeldungen.");

        return self::SUCCESS;
    }
}
