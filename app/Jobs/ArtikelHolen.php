<?php

namespace App\Jobs;

use App\Support\Sammelbestellung;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Throwable;

/**
 * „Mehrere Artikel holen“ (Sammelbestellung → Artikel): Rüsthaus-Nummern bzw.
 * Links nacheinander einzeln abrufen wie „Artikel holen“ (SB-10, SB-16: eine
 * Anfrage pro Sekunde), je Lauf höchstens drei, damit der Lauf im Zeitfenster
 * des Cronjobs bleibt; danach stellt sich der Job für den Rest wieder ein.
 * Stand und Ergebnis stehen im Cache (ArtikelHolen::stand()).
 */
class ArtikelHolen implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const KEY = 'sammelbestellung:holen';

    public const MAX = 50;

    private const JE_LAUF = 3;

    public int $tries = 1;

    public int $timeout = 300;

    /** @param list<string> $eingaben */
    public function __construct(public array $eingaben, public bool $freigeben = false, public int $ab = 0, public ?string $kategorie = null) {}

    /**
     * Eingabe in einzelne Nummern bzw. Links zerlegen: je Zeile, Komma, Semikolon
     * oder Leerzeichen; doppelte fallen weg.
     *
     * @return list<string>
     */
    public static function zerlegen(string $text): array
    {
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/[\s,;]+/u', $text) ?: []), fn ($e) => $e !== '')));
    }

    /** @return array{laeuft: bool, gesamt: int, erledigt: int, neu: list<string>, fehler: list<string>, am: string}|null */
    public static function stand(): ?array
    {
        $stand = Cache::get(self::KEY);

        return is_array($stand) ? $stand : null;
    }

    /** @param list<string> $eingaben */
    public static function starten(array $eingaben, bool $freigeben, ?string $kategorie = null): void
    {
        Cache::put(self::KEY, ['laeuft' => true, 'gesamt' => count($eingaben), 'erledigt' => 0, 'neu' => [], 'fehler' => [], 'am' => now()->toIso8601String()], now()->addDays(7));
        self::dispatch($eingaben, $freigeben, 0, filled($kategorie) ? mb_substr(trim((string) $kategorie), 0, 80) : null);
    }

    public function handle(): void
    {
        $stand = self::stand() ?? ['laeuft' => true, 'gesamt' => count($this->eingaben), 'erledigt' => 0, 'neu' => [], 'fehler' => [], 'am' => now()->toIso8601String()];
        $teil = array_slice($this->eingaben, $this->ab, self::JE_LAUF);
        foreach ($teil as $eingabe) {
            try {
                $artikel = Sammelbestellung::freigeben($eingabe, $this->freigeben);
                // Kategorie im Shop nur setzen, wenn der Artikel noch keine hat.
                if ($this->kategorie !== null && blank($artikel->kategorie)) {
                    $artikel->update(['kategorie' => $this->kategorie]);
                }
                $stand['neu'][] = $artikel->name.' ('.$artikel->varianten->count().' Größen)';
            } catch (InvalidArgumentException $e) {
                $stand['fehler'][] = $eingabe.': '.$e->getMessage();
            } catch (Throwable $e) {
                report($e);
                $stand['fehler'][] = $eingabe.': Abruf fehlgeschlagen.';
            }
            $stand['erledigt']++;
        }

        $weiter = $this->ab + self::JE_LAUF < count($this->eingaben);
        $stand['laeuft'] = $weiter;
        Cache::put(self::KEY, $stand, now()->addDays(7));
        if ($weiter) {
            self::dispatch($this->eingaben, $this->freigeben, $this->ab + self::JE_LAUF, $this->kategorie);
        }
    }
}
