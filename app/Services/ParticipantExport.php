<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Zahlung;
use App\Services\Mitglieder\MitgliederAbgleich;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Teilnehmerliste als .xlsx (F-EV-20..23): Spalten nach Formular-Baukasten
 * der Veranstaltung, direkt in den Ausgabestrom geschrieben (keine Datei
 * auf dem Server), Formel-Anfänge maskiert.
 */
class ParticipantExport
{
    /**
     * @return list<string>
     */
    public function headings(Event $event): array
    {
        // Bei der Tage-Abfrage zusätzlich eine Spalte je Tag (zum Zählen).
        $fields = collect($event->formFields())
            ->flatMap(fn (array $field) => $field['type'] === 'days' ? [$field['label'], ...array_values($event->days())] : [$field['label']])
            ->all();

        $member = $event->checksMembers() ? ['Mitglied', 'Mitgliedsnummer', 'Stamm (Mitgliederverwaltung)', ...($event->nurFuerMitglieder() ? ['Eigene Gruppierung'] : [])] : [];

        $beitrag = $event->hatTeilnahmebeitrag() ? ['Teilnahmebeitrag', 'Betrag', 'Zahlung', 'Verwendungszweck'] : [];

        return ['Nr.', 'Status', 'Vorname', 'Nachname', 'E-Mail', ...$fields, ...$member, ...$beitrag, 'Angemeldet durch (sorgeberechtigt)', 'Bestätigt am'];
    }

    /**
     * @return list<list<string|int|null>>
     */
    public function rows(Event $event): array
    {
        $fields = $event->formFields();
        $checksMembers = $event->checksMembers();

        return $event->registrations()
            ->whereIn('status', ['confirmed', 'waitlist', 'cancelled'])
            ->orderByRaw("case status when 'confirmed' then 0 when 'waitlist' then 1 else 2 end")
            ->orderBy('confirmed_at')
            ->with('zahlungen')
            ->get()
            ->values()
            ->map(function (EventRegistration $r, int $i) use ($fields, $event, $checksMembers) {
                $extra = [];
                foreach ($fields as $key => $field) {
                    $value = $key === 'birth_year' ? $r->birth_year : ($r->data[$key] ?? null);
                    $extra[] = $key === 'birth_year' ? $value : $event->fieldValueLabel($field, $value);
                    if ($field['type'] === 'days') {
                        foreach (array_keys($event->days()) as $day) {
                            $extra[] = in_array($day, (array) $value, true) ? 'x' : '';
                        }
                    }
                }

                return array_map(self::mask(...), [
                    $i + 1,
                    $r->statusLabel(),
                    $r->first_name,
                    $r->last_name,
                    $r->email,
                    ...$extra,
                    ...($checksMembers ? [MitgliederAbgleich::STATUSES[$r->member_status] ?? 'nicht geprüft', $r->member_number, $r->member_group] : []),
                    ...($checksMembers && $event->nurFuerMitglieder() ? [match ($r->member_own_group) {
                        true => 'ja', false => 'nein', default => ''
                    }] : []),
                    ...($event->hatTeilnahmebeitrag() ? [
                        $r->preis_bezeichnung,
                        $r->preis_cent !== null ? number_format($r->preis_cent / 100, 2, ',', '') : null,
                        Zahlung::STATUS[$r->zahlungsstand() ?? ''] ?? '',
                        $r->zahlung()?->referenz,
                    ] : []),
                    $r->guardian_name,
                    $r->confirmed_at?->format('d.m.Y H:i'),
                ]);
            })
            ->all();
    }

    /**
     * Schreibt die Arbeitsmappe nach php://output (für streamDownload).
     */
    public function write(Event $event): void
    {
        $writer = new Writer;
        $writer->openToFile('php://output');
        $writer->getCurrentSheet()->setName('Teilnehmende');
        $writer->addRow(Row::fromValues($this->headings($event), (new Style)->setFontBold()));

        foreach ($this->rows($event) as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();
    }

    public function filename(Event $event): string
    {
        return 'Teilnehmende-'.$event->slug.'-'.now()->format('Y-m-d').'.xlsx';
    }

    /**
     * Excel-Formelinjektion verhindern (F-EV-23): Werte, die mit = + - @
     * (oder Tab/CR) beginnen, bekommen ein führendes Apostroph.
     */
    public static function mask(string|int|null $value): string|int|null
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
