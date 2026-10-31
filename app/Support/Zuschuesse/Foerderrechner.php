<?php

namespace App\Support\Zuschuesse;

use App\Models\Event;
use App\Support\Zuschuesse;
use Illuminate\Support\Collection;

/**
 * Förderschätzung je Zuschussgeber (docs/pflichtenheft-zuschuesse.md, ZU-18):
 * zuschussfähige Teilnehmende und Betreuer*innen, Tage, Satz, Betrag, mit
 * Rechenweg. Eine Schätzung ohne Gewähr; die Sätze stehen mit der Fassung
 * der Richtlinie in RICHTLINIEN und lassen sich dort nachziehen.
 *
 * Vorsichtig gerechnet: im Zweifel der niedrigere Satz, die kleinere
 * Personenzahl, nur Bekanntes (z. B. Wohnort außerhalb des Saarlands zählt
 * nicht als Bistum Trier).
 */
class Foerderrechner
{
    public const RICHTLINIEN = [
        'land' => 'Richtlinien zum Kinder- und Jugendförderungsgesetz (Saarland) vom 30.03.2023',
        'kreis_sls' => 'Förderrichtlinien des Landkreises Saarlouis ab 01.01.2021',
        'stadt_sls' => 'Richtlinien der Kreisstadt Saarlouis zur Förderung der außerschulischen Jugendarbeit (1992, Fassung 1998)',
        'bistum_trier' => 'Richtlinien Kirchlicher Kinder- und Jugendplan des Bistums Trier ab 01.01.2023',
    ];

    /**
     * @return array{geber: list<array{key: string, name: string, betrag: int, zeilen: list<string>, hinweise: list<string>, richtlinie: string}>, summe: int, ungedeckt: ?int, hoechstens: int, finanzen: array<string, int>, hinweise: list<string>}
     */
    public static function rechnen(Event $event): array
    {
        $personen = Zuschuesse::personen($event);
        $finanzen = self::finanzen($event);
        $tage = max(1, count($event->days()));
        $art = (string) $event->zuschuss_art;

        $geber = [];
        foreach ((array) ($event->zuschuss_geber ?: array_keys(Zuschuesse::GEBER)) as $key) {
            if (! isset(Zuschuesse::GEBER[$key])) {
                continue;
            }
            $ergebnis = match ($key) {
                'land' => self::land($art, $personen, $tage),
                'kreis_sls' => self::kreisSaarlouis($art, $personen, $tage),
                'stadt_sls' => self::stadtSaarlouis($art, $personen, $tage, (bool) $event->zuschuss_fern, $finanzen['ausgaben']),
                'bistum_trier' => self::bistumTrier($art, $personen, $tage),
            };
            $geber[] = ['key' => $key, 'name' => Zuschuesse::GEBER[$key], 'richtlinie' => self::RICHTLINIEN[$key]] + $ergebnis;
        }

        $summe = (int) array_sum(array_column($geber, 'betrag'));
        $ungedeckt = $finanzen['ausgaben'] > 0 ? $finanzen['ungedeckt'] : null;
        $hinweise = [];
        $fehlend = Zuschuesse::fehlend($event);
        if ($fehlend !== []) {
            $hinweise[] = 'Bei '.count($fehlend).' Person(en) fehlen Angaben (z. B. Geburtsdatum); sie zählen nur, soweit die Angaben reichen.';
        }
        if ($ungedeckt === null) {
            $hinweise[] = 'Ohne Kostenplan (Ausgaben) lässt sich nicht prüfen, ob die Zuschüsse die ungedeckten Kosten übersteigen. Alle Stellen zahlen höchstens bis zur Höhe des Defizits.';
        }

        return [
            'geber' => $geber,
            'summe' => $summe,
            'ungedeckt' => $ungedeckt,
            'hoechstens' => $ungedeckt === null ? $summe : min($summe, $ungedeckt),
            'finanzen' => $finanzen,
            'hinweise' => $hinweise,
        ];
    }

    /** @return array<string, int> */
    public static function finanzen(Event $event): array
    {
        $finanzen = Zuschuesse::finanzen($event);
        // Ohne eigene Angabe: Teilnahmebeiträge der bestätigten Anmeldungen.
        if (blank(((array) $event->zuschuss_finanzen)['teilnahmebeitraege'] ?? null)) {
            $beitraege = (int) $event->registrations()->where('status', 'confirmed')->sum('preis_cent');
            $finanzen['teilnahmebeitraege'] = $beitraege;
            $finanzen['einnahmen'] += $beitraege;
            $finanzen['ungedeckt'] = max(0, $finanzen['ausgaben'] - $finanzen['einnahmen']);
        }

        return $finanzen;
    }

    /** Bezuschussbare Betreuer*innen nach Land/Kreis/Bistum: bis 14 TN zwei, je weitere (angefangene) 7 TN eine weitere. */
    private static function schluessel(int $tn, bool $angefangen = true): int
    {
        if ($tn <= 0) {
            return 0;
        }
        $mehr = max(0, $tn - 14);

        return 2 + ($angefangen ? (int) ceil($mehr / 7) : intdiv($mehr, 7));
    }

    /**
     * Personentage: Teilnehmende mit ihren Tagen, dazu die bezuschussbaren
     * Betreuer*innen mit den meisten Tagen; alles auf die Höchstdauer gekappt.
     *
     * @param  Collection<int, array<string, mixed>>  $tn
     * @param  Collection<int, array<string, mixed>>  $betreuung
     * @return array{tn: int, betreuer: int, tage: float}
     */
    private static function personentage(Collection $tn, Collection $betreuung, int $betreuerMax, float $maxTage, ?callable $tageJePerson = null): array
    {
        $tageJePerson ??= fn (array $p) => (float) count($p['tage']);
        $tage = fn (array $p) => min($maxTage, $tageJePerson($p));
        $betreuer = $betreuung->sortByDesc($tage)->take($betreuerMax);

        return ['tn' => $tn->count(), 'betreuer' => $betreuer->count(), 'tage' => (float) $tn->sum($tage) + (float) $betreuer->sum($tage)];
    }

    private static function euro(int $cent): string
    {
        return number_format($cent / 100, 2, ',', '.').' €';
    }

    private static function zahl(float $wert): string
    {
        return rtrim(rtrim(number_format($wert, 1, ',', '.'), '0'), ',');
    }

    /** @return array{betrag: int, zeilen: list<string>, hinweise: list<string>} */
    private static function nichts(string $grund, array $hinweise = []): array
    {
        return ['betrag' => 0, 'zeilen' => [$grund], 'hinweise' => $hinweise];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $personen
     * @return array{0: Collection<int, array<string, mixed>>, 1: Collection<int, array<string, mixed>>}
     */
    private static function aufteilen(Collection $personen, callable $tnFilter, ?callable $betreuungFilter = null): array
    {
        $tn = $personen->filter(fn (array $p) => $p['funktion'] === 'TN')->filter($tnFilter)->values();
        $betreuung = $personen->filter(fn (array $p) => Zuschuesse::istBetreuung($p['funktion']))
            ->filter($betreuungFilter ?? fn () => true)->values();

        return [$tn, $betreuung];
    }

    /** @return array{betrag: int, zeilen: list<string>, hinweise: list<string>} */
    private static function land(string $art, Collection $personen, int $tage): array
    {
        $regeln = [
            'freizeit' => ['satz' => 700, 'min' => 6, 'max' => 21, 'von' => 6, 'bis' => 26, 'betreuer' => true, 'abschnitt' => 'E (Freizeiten)'],
            'bildung' => ['satz' => 1500, 'min' => 4, 'max' => 10, 'von' => 6, 'bis' => 26, 'betreuer' => true, 'abschnitt' => 'B (Bildung)'],
            'schulung' => ['satz' => 1700, 'min' => 4, 'max' => 10, 'von' => 15, 'bis' => 200, 'betreuer' => false, 'abschnitt' => 'A (Fortbildung Ehrenamtlicher)'],
        ][$art] ?? null;
        if ($regeln === null) {
            return self::nichts('Für diese Art der Maßnahme sieht die Landesrichtlinie keinen Tagessatz vor.');
        }
        if ($art === 'freizeit' && $tage < 2) {
            return self::nichts('Freizeiten werden ab 2 Maßnahmentagen gefördert.');
        }
        [$tn, $betreuung] = self::aufteilen($personen, fn (array $p) => $p['alter'] !== null && $p['alter'] >= $regeln['von'] && $p['alter'] <= $regeln['bis']);
        if ($tn->count() < $regeln['min']) {
            return self::nichts("Zu wenige zuschussfähige Teilnehmende ({$tn->count()}, mindestens {$regeln['min']}).");
        }
        $schluessel = $regeln['betreuer'] ? self::schluessel($tn->count()) : 0;
        $pt = self::personentage($tn, $betreuung, $schluessel, $regeln['max']);
        $betrag = (int) round($pt['tage'] * $regeln['satz']);
        $hinweise = [];
        if ($art !== 'freizeit') {
            $hinweise[] = 'Ohne vom Land geförderte/n Bildungsreferent*in bei Träger oder Dachverband erhöht sich der Satz um 6 € je Tag und Person (hier vorsichtig nicht gerechnet).';
        }
        if (! $regeln['betreuer']) {
            $hinweise[] = 'Betreuer*innen nur, wenn Minderjährige ihren Einsatz erfordern (hier nicht gerechnet).';
        }

        return ['betrag' => $betrag, 'zeilen' => [
            "Abschnitt {$regeln['abschnitt']}: ".self::euro($regeln['satz']).' je Tag und Person, höchstens '.$regeln['max'].' Tage.',
            "Zuschussfähig: {$pt['tn']} Teilnehmende ({$regeln['von']}".($regeln['bis'] < 100 ? ' bis unter '.($regeln['bis'] + 1) : '+').' Jahre)'
                .($regeln['betreuer'] ? " und {$pt['betreuer']} von höchstens {$schluessel} Betreuer*innen" : '').'.',
            self::zahl($pt['tage']).' Personentage × '.self::euro($regeln['satz']).' = '.self::euro($betrag),
        ], 'hinweise' => $hinweise];
    }

    /** @return array{betrag: int, zeilen: list<string>, hinweise: list<string>} */
    private static function kreisSaarlouis(string $art, Collection $personen, int $tage): array
    {
        $regeln = [
            'freizeit' => ['satz' => 250, 'min' => 5, 'max' => 21, 'von' => 6, 'bis' => 21, 'betreuer' => true, 'abschnitt' => '4.1 Freizeitmaßnahmen'],
            'bildung' => ['satz' => 500, 'min' => 5, 'max' => 10, 'von' => 6, 'bis' => 26, 'betreuer' => true, 'abschnitt' => '4.2 Bildungsmaßnahmen'],
            'schulung' => ['satz' => 550, 'min' => 4, 'max' => 31, 'von' => 15, 'bis' => 200, 'betreuer' => false, 'abschnitt' => '4.3 Aus- und Fortbildung'],
        ][$art] ?? null;
        if ($regeln === null) {
            return self::nichts('Für diese Art der Maßnahme sieht die Kreisrichtlinie keinen Tagessatz vor.');
        }
        if ($art === 'freizeit' && $tage < 2) {
            return self::nichts('Freizeiten werden ab einer Übernachtung gefördert.');
        }
        $imKreis = fn (array $p) => $p['kreis'] === 'SLS';
        [$tn, $betreuung] = self::aufteilen($personen,
            fn (array $p) => $imKreis($p) && $p['alter'] !== null && $p['alter'] >= $regeln['von'] && $p['alter'] <= $regeln['bis'],
            fn (array $p) => $imKreis($p) && ($p['alter'] ?? 0) >= 18);
        if ($tn->count() < $regeln['min']) {
            return self::nichts("Zu wenige zuschussfähige Teilnehmende aus dem Landkreis Saarlouis ({$tn->count()}, mindestens {$regeln['min']}).");
        }
        $schluessel = $regeln['betreuer'] ? self::schluessel($tn->count()) : 0;
        $pt = self::personentage($tn, $betreuung, $schluessel, $regeln['max']);
        $betrag = (int) round($pt['tage'] * $regeln['satz']);
        $hinweise = ['Nur Personen mit Wohnort im Landkreis Saarlouis (laut PLZ) gezählt.'];
        if ($art !== 'freizeit') {
            $hinweise[] = 'Bildung und Schulung fördert der Kreis nachrangig zum Land.';
        }
        if ($art === 'freizeit' && $tage >= 5) {
            $hinweise[] = 'Zahlt ihr Ehrenamtlichen ein Taschengeld, gibt der Kreis zusätzlich 50 %, höchstens 11 € je Betreuer*in und Tag (nicht gerechnet).';
        }

        return ['betrag' => $betrag < 1000 ? 0 : $betrag, 'zeilen' => [
            "{$regeln['abschnitt']}: ".self::euro($regeln['satz']).' je Tag und Person'.($art === 'freizeit' ? ' (An- und Abreisetag je ein Tag)' : '').'.',
            "Zuschussfähig: {$pt['tn']} Teilnehmende ({$regeln['von']}".($regeln['bis'] < 100 ? "–{$regeln['bis']}" : '+').' Jahre)'
                .($regeln['betreuer'] ? " und {$pt['betreuer']} von höchstens {$schluessel} Betreuer*innen ab 18" : '').'.',
            self::zahl($pt['tage']).' Personentage × '.self::euro($regeln['satz']).' = '.self::euro($betrag).($betrag < 1000 ? ' (unter 10 € keine Auszahlung)' : ''),
        ], 'hinweise' => $hinweise];
    }

    /** @return array{betrag: int, zeilen: list<string>, hinweise: list<string>} */
    private static function stadtSaarlouis(string $art, Collection $personen, int $tage, bool $fern, int $ausgaben): array
    {
        if (! in_array($art, ['freizeit', 'bildung'], true)) {
            return self::nichts('Die Stadt Saarlouis fördert Bildung, Freizeiten, Jugendbegegnungen und Jugendwerbung, keine Schulungen.');
        }
        $jugendliche = fn (array $p) => $p['alter'] !== null && $p['alter'] >= 5 && $p['alter'] <= 17;
        [$tn, $betreuung] = self::aufteilen($personen, $jugendliche);
        if ($tn->count() < 5) {
            return self::nichts("Zu wenige Jugendliche zwischen 5 und 17 Jahren ({$tn->count()}, mindestens 5).");
        }
        $hinweise = ['Zuschusszeitraum 01.10. bis 30.09.; Antrag bis 2 Monate nach Ende, spätestens 30.09. Reichen die Mittel nicht, kürzt die Stadt anteilig.'];
        if ($art === 'bildung' && $tage < 2) {
            $betrag = min(5000, (int) round($ausgaben * 0.25));

            return ['betrag' => $betrag, 'zeilen' => [
                '§ 9 Abs. 4 a: Tages-/Abendseminar 25 % der Kosten, höchstens 50 €.',
                '25 % von '.self::euro($ausgaben).' = '.self::euro($betrag),
            ], 'hinweise' => $ausgaben === 0 ? [...$hinweise, 'Ohne Ausgaben im Kostenplan kein Betrag.'] : $hinweise];
        }
        $satz = $art === 'freizeit' && $fern ? 250 : 150;
        // Ein Betreuer je fünf Jugendliche, mindestens die Hälfte volljährig.
        $schluessel = intdiv($tn->count(), 5);
        $betreuer = $betreuung->sortByDesc(fn (array $p) => ($p['alter'] ?? 0) >= 18)->take($schluessel)->values();
        $volljaehrig = $betreuer->filter(fn (array $p) => ($p['alter'] ?? 0) >= 18)->count();
        if ($volljaehrig * 2 < $betreuer->count()) {
            $betreuer = $betreuer->take($volljaehrig * 2);
        }
        $pt = (float) $tn->sum(fn (array $p) => count($p['tage'])) + (float) $betreuer->sum(fn (array $p) => count($p['tage']));
        $betrag = (int) round($pt * $satz);

        return ['betrag' => $betrag, 'zeilen' => [
            '§ 9 Abs. 4 '.($art === 'freizeit' ? 'd: Fahrten/Lager '.($fern ? 'über 250 km' : 'bis 250 km') : 'b: Wochenend-/Mehrtagesseminar').', '.self::euro($satz).' je Tag und Person.',
            "Zuschussfähig: {$tn->count()} Jugendliche (5–17 Jahre) und {$betreuer->count()} von höchstens {$schluessel} Betreuer*innen (einer je fünf Jugendliche).",
            self::zahl($pt).' Personentage × '.self::euro($satz).' = '.self::euro($betrag),
        ], 'hinweise' => $hinweise];
    }

    /** @return array{betrag: int, zeilen: list<string>, hinweise: list<string>} */
    private static function bistumTrier(string $art, Collection $personen, int $tage): array
    {
        $juleica = $personen->contains(fn (array $p) => Zuschuesse::istBetreuung($p['funktion']) && $p['juleica']);
        $regeln = [
            'freizeit' => ['satz' => $juleica ? 700 : 600, 'max' => 14, 'von' => 6, 'betreuer' => true, 'abschnitt' => '3.4 Soziale Bildung und Freizeiten'],
            'bildung' => ['satz' => $juleica ? 700 : 600, 'max' => 14, 'von' => 6, 'betreuer' => true, 'abschnitt' => '3.4 Soziale Bildung und Freizeiten'],
            'schulung' => ['satz' => 300, 'max' => 7, 'von' => 14, 'betreuer' => false, 'abschnitt' => '3.3 Qualifizierung und Schulung Ehrenamtlicher'],
        ][$art] ?? null;
        if ($regeln === null) {
            return self::nichts('Für sonstige Maßnahmen gibt es beim KJP nur 3.5 (innovative Maßnahmen, Vorantrag 6 Wochen vorher).');
        }
        // Alter über das laufende Kalenderjahr gerechnet (6 bis 27).
        $jahresalter = fn (array $p) => $p['geburtsdatum'] ? (int) now()->year - $p['geburtsdatum']->year : $p['alter'];
        [$tn, $betreuung] = self::aufteilen($personen,
            fn (array $p) => ($a = $jahresalter($p)) !== null && $a >= $regeln['von'] && ($art === 'schulung' || $a <= 27),
            fn (array $p) => ($p['alter'] ?? 0) >= 18);
        if ($tn->count() < 7) {
            return self::nichts("Zu wenige zuschussfähige Teilnehmende ({$tn->count()}, mindestens 7).");
        }
        $imBistum = $tn->filter(fn (array $p) => ! in_array($p['kreis'], [...Kreise::BISTUM_SPEYER, 'SONST'], true))->count();
        $hinweise = [];
        if ($imBistum * 2 <= $tn->count()) {
            return self::nichts("Die Mehrheit muss im Bistum Trier wohnen: nur {$imBistum} von {$tn->count()} Teilnehmenden sicher (Saarland ohne Saarpfalz-Kreis).",
                ['Wohnorte außerhalb des Saarlands kann Kluft nicht zuordnen; liegen sie im Bistum Trier, bitte selbst prüfen.']);
        }
        // Ein Tag = 6 Std. Programm; An- und Abreisetag zählen vorsichtig je halb.
        $tageJePerson = function (array $p) use ($tage): float {
            $n = count($p['tage']);

            return $tage >= 2 ? max(0.5, $n - (count($p['tage']) === $tage ? 1 : 0.5)) : (float) $n;
        };
        $schluessel = $regeln['betreuer'] ? self::schluessel($tn->count(), false) : 0;
        $pt = self::personentage($tn, $betreuung, $schluessel, $regeln['max'], $tageJePerson);
        $betrag = (int) round($pt['tage'] * $regeln['satz']);
        if ($art === 'freizeit' && ! $juleica) {
            $hinweise[] = 'Mit gültigem Juleica-Nachweis der Leitung 7 statt 6 € je Tag.';
        }
        $hinweise[] = 'Antrag bis 8 Wochen nach Ende, Original-Unterschriftenliste. Der KJP ergänzt öffentliche Förderung.';

        return ['betrag' => $betrag < 2500 ? 0 : $betrag, 'zeilen' => [
            "{$regeln['abschnitt']}: ".self::euro($regeln['satz']).' je Tag und Person'.($art !== 'schulung' && $juleica ? ' (mit Juleica-Nachweis)' : '').', höchstens '.$regeln['max'].' Tage; An- und Abreisetag je halber Tag.',
            "Zuschussfähig: {$pt['tn']} Teilnehmende ({$regeln['von']}".($art === 'schulung' ? '+' : '–27').' Jahre im Kalenderjahr)'
                .($regeln['betreuer'] ? " und {$pt['betreuer']} von höchstens {$schluessel} Betreuer*innen über 18" : '').'.',
            self::zahl($pt['tage']).' Personentage × '.self::euro($regeln['satz']).' = '.self::euro($betrag).($betrag < 2500 ? ' (unter 25 € keine Auszahlung)' : ''),
        ], 'hinweise' => $hinweise];
    }
}
