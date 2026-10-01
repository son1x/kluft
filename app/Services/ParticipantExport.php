<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
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
        $fields = collect($event->formFields())->map(fn (array $field) => $field['label'])->all();

        return ['Nr.', 'Status', 'Vorname', 'Nachname', 'E-Mail', ...array_values($fields), 'Angemeldet durch (sorgeberechtigt)', 'Bestätigt am'];
    }

    /**
     * @return list<list<string|int|null>>
     */
    public function rows(Event $event): array
    {
        $fields = $event->formFields();

        return $event->registrations()
            ->whereIn('status', ['confirmed', 'waitlist', 'cancelled'])
            ->orderByRaw("case status when 'confirmed' then 0 when 'waitlist' then 1 else 2 end")
            ->orderBy('confirmed_at')
            ->get()
            ->values()
            ->map(function (EventRegistration $r, int $i) use ($fields) {
                $extra = [];
                foreach ($fields as $key => $field) {
                    $value = $key === 'birth_year' ? $r->birth_year : ($r->data[$key] ?? null);
                    $extra[] = $field['type'] === 'select' ? ($field['options'][$value] ?? $value) : $value;
                }

                return array_map(self::mask(...), [
                    $i + 1,
                    $r->statusLabel(),
                    $r->first_name,
                    $r->last_name,
                    $r->email,
                    ...$extra,
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
