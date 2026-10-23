<?php

namespace App\Services\Umfragen;

use App\Models\Umfrage;
use App\Models\UmfrageAntwort;
use App\Models\UmfrageFrage;
use App\Support\Umfragen;
use Illuminate\Support\Collection;

/**
 * Zusammenfassende Auswertung einer Umfrage (docs/umfragen.md): je Frage
 * Verteilung, Mittelwert, Freitexte und Kommentare. Dieselben Daten nutzen
 * Backend, PDF und Excel.
 */
class Auswertung
{
    /**
     * @return array{teilnahmen: int, tage: array<string, int>, fragen: list<array<string, mixed>>}
     */
    public function zusammenfassung(Umfrage $umfrage): array
    {
        $fragen = $umfrage->fragen()->get();
        $antworten = $umfrage->antworten()->orderBy('id')->get();

        return [
            'teilnahmen' => $antworten->count(),
            'tage' => $antworten->groupBy(fn (UmfrageAntwort $a) => $a->eingegangen_am->toDateString())->map->count()->sortKeys()->all(),
            'fragen' => $fragen->map(fn (UmfrageFrage $f) => $this->frage($f, $antworten))->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, UmfrageAntwort>  $antworten
     * @return array<string, mixed>
     */
    private function frage(UmfrageFrage $frage, Collection $antworten): array
    {
        $eintraege = $antworten->map(fn (UmfrageAntwort $a) => $a->daten[$frage->schluessel] ?? null)->filter();
        $werte = $eintraege->map(fn (array $e) => $e['wert'] ?? null)->filter(fn ($w) => $w !== null && $w !== []);
        $ergebnis = [
            'frage' => $frage,
            'beantwortet' => $werte->count(),
            'gezeigt' => $eintraege->count(),
            'verteilung' => null,
            'mittel' => null,
            'mittel_hinweis' => null,
            'texte' => [],
            'kommentare' => $eintraege->pluck('kommentar')->filter()->values()->all(),
            'sonstiges' => $eintraege->pluck('sonstiges')->filter()->values()->all(),
        ];

        if (in_array($frage->typ, ['einfach', 'mehrfach', 'bewertung', 'janein'], true)) {
            $verteilung = array_fill_keys(Umfragen::moeglichkeiten($frage), 0);
            foreach ($werte as $w) {
                foreach ((array) $w as $einzeln) {
                    if (array_key_exists($einzeln, $verteilung)) {
                        $verteilung[$einzeln]++;
                    }
                }
            }
            $ergebnis['verteilung'] = $verteilung;
            if ($frage->typ === 'bewertung') {
                $stufen = $frage->optionenListe() ?: Umfragen::BEWERTUNG_STANDARD;
                $punkte = $werte->map(fn ($w) => array_search($w, $stufen, true))->filter(fn ($i) => $i !== false);
                if ($punkte->isNotEmpty()) {
                    $ergebnis['mittel'] = round($punkte->avg() + 1, 1);
                    $ergebnis['mittel_hinweis'] = '1 = '.$stufen[0].', '.count($stufen).' = '.end($stufen);
                }
            }
        } elseif ($frage->typ === 'skala') {
            [$von, $bis] = Umfragen::skala($frage);
            $verteilung = array_fill_keys(range($von, $bis), 0);
            foreach ($werte as $w) {
                if (isset($verteilung[(int) $w])) {
                    $verteilung[(int) $w]++;
                }
            }
            $ergebnis['verteilung'] = $verteilung;
            if ($werte->isNotEmpty()) {
                $ergebnis['mittel'] = round($werte->avg(), 1);
                $ergebnis['mittel_hinweis'] = $von.' = '.($frage->einstellung('label_von') ?: 'niedrig').', '.$bis.' = '.($frage->einstellung('label_bis') ?: 'hoch');
            }
        } elseif (in_array($frage->typ, ['kurztext', 'langtext'], true)) {
            $ergebnis['texte'] = $werte->map(fn ($w) => (string) $w)->values()->all();
        }

        return $ergebnis;
    }

    /** Lesbare Antwort einer Person auf eine Frage. */
    public static function wertText(mixed $wert): string
    {
        return match (true) {
            $wert === null, $wert === [] => '–',
            is_array($wert) => implode(', ', $wert),
            default => (string) $wert,
        };
    }
}
