<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\Zuschuss\ZuschussPdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Küchenliste zum Aushängen: Anzahl Essen je Tag und Verpflegung, dazu nur
 * die Personen mit Besonderheiten (Allergien, vegan …) – ohne Kontaktdaten,
 * Geburtsjahr oder andere Angaben (docs/veranstaltungen.md).
 */
class KuechenlistePdf
{
    /** Feldarten, die für die Küche in Frage kommen. */
    private const ARTEN = ['select', 'mehrfach', 'text', 'textarea'];

    /** Beschriftungen, die nach Essen klingen (Vorauswahl). */
    private const MUSTER = '/ern[äa]hr|essen|verpflegung|allerg|unvertr[äa]g|vegetar|vegan|lebensmittel|di[äa]t|k[üu]che|halal|koscher|laktose|gluten|nuss|n[üu]sse/iu';

    /**
     * Wählbare Felder: Schlüssel => Beschriftung.
     *
     * @return array<string, string>
     */
    public static function felder(Event $event): array
    {
        return collect($event->formFields())
            ->filter(fn (array $f) => in_array($f['type'], self::ARTEN, true))
            ->map(fn (array $f) => $f['label'].(($f['sensibel'] ?? false) ? ' (sensibel)' : ''))
            ->all();
    }

    /** @return list<string> */
    public static function standardFelder(Event $event): array
    {
        return array_values(array_filter(array_keys(self::felder($event)),
            fn (string $k) => $k === 'diet' || preg_match(self::MUSTER, $event->formFields()[$k]['label']) === 1));
    }

    /**
     * @param  list<string>  $felder
     * @return array{tage: array<string, int>, verteilung: array<string, array<string, array<string, int>>>, besonderheiten: Collection<int, array{name: string, stamm: ?string, werte: array<string, string>}>, gesamt: int, spalten: array<string, string>}
     */
    public function daten(Event $event, array $felder, bool $vollerName): array
    {
        $alle = $event->formFields();
        $felder = array_values(array_filter($felder, fn (string $k) => isset(self::felder($event)[$k])));
        $tageKey = collect($alle)->search(fn (array $f) => $f['type'] === 'days');
        $tage = $event->days();

        $personen = $event->registrations()->where('status', 'confirmed')
            ->orderBy('last_name')->orderBy('first_name')->get();

        // Tage je Person: ohne Tage-Auswahl zählt die ganze Zeit.
        $tageVon = fn (EventRegistration $r): array => array_values(array_intersect(
            $tageKey !== false && is_array($r->data[$tageKey] ?? null) ? $r->data[$tageKey] : array_keys($tage), array_keys($tage)));

        $proTag = array_fill_keys(array_keys($tage), 0);
        foreach ($personen as $r) {
            foreach ($tageVon($r) as $tag) {
                $proTag[$tag]++;
            }
        }

        // Verteilung der Auswahlfelder je Tag (z. B. Verpflegung: vegan am Samstag 3).
        $verteilung = [];
        foreach ($felder as $k) {
            if (! in_array($alle[$k]['type'], ['select', 'mehrfach'], true)) {
                continue;
            }
            $leer = array_fill_keys(array_keys($tage), 0);
            $zaehler = array_fill_keys(array_values($alle[$k]['options'] ?? []), $leer);
            foreach ($personen as $r) {
                $werte = array_filter((array) ($r->data[$k] ?? []), 'filled');
                $labels = $werte === [] ? ['keine Angabe'] : array_map(fn ($w) => $alle[$k]['options'][$w] ?? (string) $w, $werte);
                foreach ($labels as $label) {
                    $zaehler[$label] ??= $leer;
                    foreach ($tageVon($r) as $tag) {
                        $zaehler[$label][$tag]++;
                    }
                }
            }
            $verteilung[$alle[$k]['label']] = collect($zaehler)
                ->map(fn (array $n) => collect($n)->mapWithKeys(fn (int $z, string $tag) => [$tage[$tag] => $z])->all())->all();
        }

        // Besonderheiten: Freitext ausgefüllt oder nicht die erste (übliche) Antwort.
        $besonderheiten = $personen->map(function (EventRegistration $r) use ($alle, $felder, $vollerName, $event) {
            $werte = [];
            foreach ($felder as $k) {
                $wert = $r->data[$k] ?? null;
                if (blank($wert) || $wert === []) {
                    continue;
                }
                if ($alle[$k]['type'] === 'select' && $wert === array_key_first($alle[$k]['options'] ?? [])) {
                    continue;
                }
                $werte[$k] = (string) $event->fieldValueLabel($alle[$k], $wert);
            }

            return $werte === [] ? null : [
                'name' => $vollerName ? $r->first_name.' '.$r->last_name : $r->first_name.' '.Str::upper(Str::substr($r->last_name, 0, 1)).'.',
                'stamm' => isset($alle['stamm']) ? ($r->data['stamm'] ?? null) : null,
                'werte' => $werte,
            ];
        })->filter()->values();

        return [
            'tage' => collect($proTag)->mapWithKeys(fn (int $n, string $tag) => [$tage[$tag] => $n])->all(),
            'verteilung' => $verteilung,
            'besonderheiten' => $besonderheiten,
            'gesamt' => $personen->count(),
            'spalten' => array_intersect_key(collect($alle)->map(fn (array $f) => $f['label'])->all(), array_flip($felder)),
        ];
    }

    /**
     * @param  list<string>  $felder
     */
    public function pdf(Event $event, array $felder, bool $vollerName): string
    {
        $daten = $this->daten($event, $felder, $vollerName);

        return app(ZuschussPdf::class)->render('pdf.kuechenliste', ['event' => $event, ...$daten],
            count($daten['spalten']) > 2 ? 'landscape' : 'portrait', 'Küchenliste – '.$event->title);
    }

    public function dateiname(Event $event): string
    {
        return 'Kuechenliste-'.$event->slug.'-'.now()->format('Y-m-d').'.pdf';
    }
}
