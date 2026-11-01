<?php

namespace App\Services;

use App\Models\DatenabgleichZeile;
use App\Services\Zuschuss\ZuschussPdf;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/** Unterschiedsliste des NaMi-Datenabgleichs als PDF und Excel (ND-06). */
class DatenabgleichExport
{
    /** Offene Unterschiede zuerst, dann nicht Gefundene, dann Erledigte. */
    private function zeilen()
    {
        return DatenabgleichZeile::query()->where('status', '!=', 'stimmt')->with('erlediger')->get()
            ->sortBy(fn (DatenabgleichZeile $z) => ($z->erledigt_am ? '1' : '0').($z->status === 'abweichung' ? '0' : '1').mb_strtolower((string) ($z->ergebnis['name'] ?? '')))
            ->values();
    }

    public function pdf(): string
    {
        return app(ZuschussPdf::class)->render('pdf.datenabgleich', [
            'zeilen' => $this->zeilen(),
            'stimmt' => DatenabgleichZeile::query()->where('status', 'stimmt')->count(),
        ], 'landscape', 'NaMi-Datenabgleich '.now()->format('d.m.Y'));
    }

    public function xlsx(): void
    {
        $writer = new Writer;
        $writer->openToFile('php://output');
        $writer->addRow(Row::fromValues(['Person', 'Mitgliedsnr.', 'Ergebnis', 'Feld', 'laut Meldung', 'laut NaMi', 'Quelle', 'Datum', 'korrigiert am'], (new Style)->setFontBold()));
        foreach ($this->zeilen() as $z) {
            $felder = $z->ergebnis['felder'] ?? [];
            foreach ($felder ?: [['feld' => '', 'meldung' => '', 'nami' => '']] as $f) {
                $writer->addRow(Row::fromValues([
                    (string) ($z->ergebnis['name'] ?? ''), (string) ($z->ergebnis['nummer'] ?? ''), DatenabgleichZeile::STATUS[$z->status] ?? $z->status,
                    $f['feld'], $f['meldung'], $f['nami'], $z->quelle === 'meldung' ? 'Meldung' : 'Anmeldung', (string) ($z->ergebnis['datum'] ?? ''), $z->erledigt_am?->format('d.m.Y') ?? '',
                ]));
            }
        }
        $writer->close();
    }
}
