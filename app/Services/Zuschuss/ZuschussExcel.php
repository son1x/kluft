<?php

namespace App\Services\Zuschuss;

use App\Models\Event;
use App\Support\Zuschuesse;
use App\Support\Zuschuesse\Kreise;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/** Teilnahmeliste für Zuschüsse als Excel (ZU-16), z. B. für eigene Auswertungen. */
class ZuschussExcel
{
    public function write(Event $event): void
    {
        $writer = new Writer;
        $writer->openToFile('php://output');
        $writer->addRow(Row::fromValues(['Funktion', 'Nachname', 'Vorname', 'Straße', 'PLZ', 'Wohnort', 'Kreis', 'Geburtsdatum', 'Alter', 'Geschlecht', 'Juleica', 'Tage'], (new Style)->setFontBold()));
        foreach (Zuschuesse::personen($event) as $p) {
            $writer->addRow(Row::fromValues([
                $p['funktion'], $p['nachname'], $p['vorname'], (string) $p['strasse'], (string) $p['plz'], (string) $p['wohnort'],
                Kreise::NAMEN[$p['kreis']] ?? $p['kreis'], $p['geburtsdatum']?->format('d.m.Y') ?? '', $p['alter'] ?? '',
                Zuschuesse::GESCHLECHTER[$p['geschlecht'] ?? ''] ?? '', Zuschuesse::istBetreuung($p['funktion']) ? ($p['juleica'] ? 'ja' : 'nein') : '', count($p['tage']),
            ]));
        }
        $writer->close();
    }

    public static function dateiname(Event $event): string
    {
        return 'zuschuss-teilnehmende-'.str($event->title)->slug().'.xlsx';
    }
}
