<?php

namespace App\Support\Zuschuesse;

use App\Models\Event;
use App\Models\Zuschussgeber;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Katalog der Zuschussgeber dieser Instanz (docs/pflichtenheft-zuschuesse.md,
 * ZU-60 ff.): eingebaute Vorlagen mit eigenen Regeln und Vordrucken, dazu
 * eigene Zuschussgeber, die der Stamm im Backend anlegt. Nur eingeschaltete
 * stehen bei neuen Veranstaltungen zur Wahl.
 */
final class Geber
{
    /** Eingebaute Vorlagen: Schlüssel => [Name, Gebiet]. */
    public const VORLAGEN = [
        'land' => ['Landesjugendamt Saarland', 'Saarland'],
        'kreis_sls' => ['Kreisjugendamt Saarlouis', 'Landkreis Saarlouis'],
        'stadt_sls' => ['Kreisstadt Saarlouis', 'Stadt Saarlouis'],
        'bistum_trier' => ['Bistum Trier (KJP)', 'Bistum Trier (Saarland und Rheinland-Pfalz)'],
    ];

    /** Arten mit eigenem Satz bei eigenen Zuschussgebern. */
    public const ARTEN = ['freizeit', 'bildung', 'schulung', 'sonstige'];

    /** @return Collection<string, Zuschussgeber> alle, nach Reihenfolge */
    public static function alle(): Collection
    {
        if (! Schema::hasTable('zuschussgeber')) {
            return collect(self::VORLAGEN)->map(fn (array $v, string $key) => new Zuschussgeber(['key' => $key, 'name' => $v[0], 'vorlage' => $key, 'aktiv' => true]));
        }

        return Zuschussgeber::query()->orderBy('sort')->orderBy('name')->get()->keyBy('key');
    }

    /** @return array<string, string> eingeschaltete: Schlüssel => Name */
    public static function aktiv(): array
    {
        return self::alle()->filter(fn (Zuschussgeber $g) => $g->aktiv)->map(fn (Zuschussgeber $g) => $g->name)->all();
    }

    /** Auswahl an einer Veranstaltung: eingeschaltete plus schon gewählte. @return array<string, string> */
    public static function auswahl(?Event $event = null): array
    {
        $gewaehlt = (array) ($event?->zuschuss_geber ?? []);

        return self::alle()->filter(fn (Zuschussgeber $g) => $g->aktiv || in_array($g->key, $gewaehlt, true))
            ->map(fn (Zuschussgeber $g) => $g->name.($g->aktiv ? '' : ' (ausgeschaltet)'))->all();
    }

    public static function modell(string $key): ?Zuschussgeber
    {
        return self::alle()->get($key);
    }

    public static function name(string $key): string
    {
        return self::modell($key)?->name ?? (self::VORLAGEN[$key][0] ?? $key);
    }

    /**
     * Gewählte Zuschussgeber einer Veranstaltung; ohne Auswahl alle
     * eingeschalteten. Unbekannte (gelöschte) fallen weg.
     *
     * @return list<string>
     */
    public static function vonEvent(Event $event): array
    {
        $alle = self::alle();
        $gewaehlt = (array) ($event->zuschuss_geber ?: array_keys(self::aktiv()));

        return array_values(array_filter($alle->keys()->all(), fn (string $key) => in_array($key, $gewaehlt, true)));
    }

    /** Vordruck eines Gebers (formular_a, stadt_sls, bistum_trier, allgemein). */
    public static function vordruck(string $key): string
    {
        return self::modell($key)?->vordruck() ?? 'allgemein';
    }

    /** @return array<string, mixed> Regeln eines eigenen Gebers (leer bei Vorlagen). */
    public static function regeln(string $key): array
    {
        $g = self::modell($key);

        return $g && $g->eigen() ? (array) $g->regeln : [];
    }

    /** Saarländische Kreise, deren Jugendamt im Formular A angekreuzt wird (Kürzel aus Kreise::NAMEN). @return list<string> */
    public static function jugendaemterFormularA(Event $event): array
    {
        $kuerzel = ['10041' => 'SB', '10042' => 'MZG', '10043' => 'NK', '10044' => 'SLS', '10045' => 'HOM', '10046' => 'WND'];
        $aemter = [];
        foreach (self::vonEvent($event) as $key) {
            if ($key === 'kreis_sls') {
                $aemter[] = 'SLS';
            } elseif (self::vordruck($key) === 'formular_a' && ($g = self::modell($key)) && $g->eigen()) {
                foreach ((array) ($g->regeln['gebiet'] ?? []) as $gebiet) {
                    if (isset($kuerzel[substr((string) $gebiet, 0, 5)]) && strlen((string) $gebiet) === 5) {
                        $aemter[] = $kuerzel[substr((string) $gebiet, 0, 5)];
                    }
                }
            }
        }

        return array_values(array_unique($aemter));
    }
}
