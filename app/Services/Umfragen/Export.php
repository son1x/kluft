<?php

namespace App\Services\Umfragen;

use App\Models\Umfrage;
use App\Models\UmfrageAntwort;
use App\Models\UmfrageFrage;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Auswertung als DIN-A4-PDF (Zusammenfassung mit Balken) und als Excel
 * (eine Zeile je Teilnahme, eine Spalte je Frage bzw. Kommentar).
 */
class Export
{
    public function __construct(private Auswertung $auswertung) {}

    public function pdf(Umfrage $umfrage): string
    {
        $html = view('pdf.umfrage-auswertung', [
            'umfrage' => $umfrage,
            'auswertung' => $this->auswertung->zusammenfassung($umfrage),
            'fonts' => resource_path('fonts/pdf'),
        ])->render();

        $cache = storage_path('app/private/dompdf');
        File::ensureDirectoryExists($cache);
        $options = new Options;
        $options->setChroot([base_path()]);
        $options->setFontDir($cache);
        $options->setFontCache($cache);
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('Carlito');
        $options->setDpi(96);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();
        $dompdf->addInfo('Title', 'Auswertung – '.$umfrage->titel);
        $dompdf->addInfo('Author', (string) config('dpsg.site.name'));

        return (string) $dompdf->output();
    }

    public function excel(Umfrage $umfrage): StreamedResponse
    {
        $fragen = $umfrage->fragen()->get()->filter->istAntwortfrage()->values();

        return response()->streamDownload(function () use ($umfrage, $fragen) {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $kopf = ['Nr.', 'Datum', ...($umfrage->anonym ? [] : ['Name', 'E-Mail'])];
            foreach ($fragen as $f) {
                $kopf[] = Str::limit($f->text, 120);
                if ($f->einstellung('sonstiges')) {
                    $kopf[] = 'Sonstiges: '.Str::limit($f->text, 60);
                }
                if ($f->kommentar) {
                    $kopf[] = 'Kommentar: '.Str::limit($f->text, 60);
                }
            }
            $writer->addRow(Row::fromValues($kopf, (new Style)->setFontBold()));

            $umfrage->antworten()->orderBy('id')->lazy()->each(function (UmfrageAntwort $a, int $i) use ($writer, $umfrage, $fragen) {
                $zeile = [$i + 1, $a->eingegangen_am->format('d.m.Y'), ...($umfrage->anonym ? [] : [(string) $a->name, (string) $a->email])];
                foreach ($fragen as $f) {
                    /** @var UmfrageFrage $f */
                    $e = $a->daten[$f->schluessel] ?? [];
                    $wert = $e['wert'] ?? null;
                    $zeile[] = is_int($wert) ? $wert : self::zelle(Auswertung::wertText($wert));
                    if ($f->einstellung('sonstiges')) {
                        $zeile[] = self::zelle((string) ($e['sonstiges'] ?? ''));
                    }
                    if ($f->kommentar) {
                        $zeile[] = self::zelle((string) ($e['kommentar'] ?? ''));
                    }
                }
                $writer->addRow(Row::fromValues($zeile));
            });
            $writer->close();
        }, Str::slug($umfrage->titel).'-antworten.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** Keine Formeln aus Freitext (CSV/Excel-Injection). */
    private static function zelle(string $text): string
    {
        return $text !== '' && in_array($text[0], ['=', '+', '-', '@'], true) ? "'".$text : ($text === '–' ? '' : $text);
    }
}
