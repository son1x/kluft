<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\Zuschuss\ZuschussPdf;

/**
 * Teilnehmendenliste für unterwegs (PDF): Name, gewählte Felder,
 * Sorgeberechtigte, optional Kästchen für die Anwesenheit je Tag. Für
 * Lager und Fahrten, wenn kein Netz da ist. Vertraulich.
 */
class TeilnehmendeListePdf
{
    /** Felder, die standardmäßig in die Liste kommen (wenn abgefragt). */
    public const STANDARD = ['birth_year', 'stamm', 'phone', 'notfall', 'notfallkontakt', 'ernaehrung', 'allergien'];

    /** Lange Freitexte passen nicht in eine Tabellenzelle. */
    private const OHNE = ['days', 'textarea'];

    /**
     * Wählbare Felder: Schlüssel => Beschriftung.
     *
     * @return array<string, string>
     */
    public static function felder(Event $event): array
    {
        return collect($event->formFields())
            ->reject(fn (array $f) => in_array($f['type'], self::OHNE, true))
            ->map(fn (array $f) => $f['label'])
            ->all();
    }

    /** @return list<string> */
    public static function standardFelder(Event $event): array
    {
        $felder = self::felder($event);
        $standard = array_values(array_filter(self::STANDARD, fn (string $k) => isset($felder[$k])));
        // Telefonfelder immer vorschlagen.
        foreach ($event->formFields() as $key => $f) {
            if ($f['type'] === 'tel' && ! in_array($key, $standard, true)) {
                $standard[] = $key;
            }
        }

        return $standard;
    }

    /**
     * @param  list<string>  $felder
     */
    public function pdf(Event $event, array $felder, bool $anwesenheit, bool $warteliste): string
    {
        $alle = $event->formFields();
        $spalten = array_values(array_intersect_key(self::felder($event), array_flip($felder)));
        $schluessel = array_values(array_filter(array_keys(self::felder($event)), fn (string $k) => in_array($k, $felder, true)));
        $tage = $anwesenheit ? $this->tage($event) : [];

        $personen = $event->registrations()
            ->whereIn('status', $warteliste ? ['confirmed', 'waitlist'] : ['confirmed'])
            ->orderByRaw("case status when 'confirmed' then 0 else 1 end")
            ->orderBy('last_name')->orderBy('first_name')
            ->get()
            ->map(fn (EventRegistration $r) => [
                'name' => $r->last_name.', '.$r->first_name,
                'warteliste' => $r->status === 'waitlist',
                'werte' => array_map(fn (string $k) => $k === 'birth_year'
                    ? (string) $r->birth_year
                    : (string) $event->fieldValueLabel($alle[$k], $r->data[$k] ?? null), $schluessel),
                'sorge' => $r->guardian_name,
            ]);

        return app(ZuschussPdf::class)->render('pdf.teilnehmende-liste', [
            'event' => $event,
            'spalten' => $spalten,
            'tage' => $tage,
            'personen' => $personen,
            'mitSorge' => ! $event->adults_only && $personen->contains(fn (array $p) => filled($p['sorge'])),
        ], count($spalten) + count($tage) > 4 ? 'landscape' : 'portrait', 'Teilnehmende – '.$event->title);
    }

    /**
     * Tage für die Anwesenheit: Tage der Veranstaltung (höchstens 14).
     *
     * @return list<string>
     */
    private function tage(Event $event): array
    {
        $tage = array_values($event->days());

        return $tage === [] ? ['anwesend'] : array_slice($tage, 0, 14);
    }

    public function dateiname(Event $event): string
    {
        return 'Teilnehmende-'.$event->slug.'-'.now()->format('Y-m-d').'.pdf';
    }
}
