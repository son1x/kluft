<?php

namespace App\Services\Teilnahmelisten;

use App\Models\Event;
use App\Support\Teilnahmelisten;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Füllt die Excel-Vorlage eines Modells (resources/vorlagen) mit den Personen:
 * Kopfzeile, Formatierung und Auswahllisten der Vorlage bleiben unverändert,
 * die Daten stehen ab Zeile 2 in der Reihenfolge der Spalten des Modells.
 */
class ExcelVorlage
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const BLATT = 'xl/worksheets/sheet1.xml';

    /** Zeilen, die die Vorlage schon formatiert mitbringt. */
    private const VORLAGE_ZEILEN = 100;

    /** @return string Inhalt der .xlsx-Datei */
    public function erzeugen(Event $event, array $modell): string
    {
        if (blank($modell['excel'] ?? null)) {
            throw new RuntimeException('Dieses Modell hat keine Excel-Vorlage.');
        }
        $quelle = resource_path('vorlagen/'.$modell['excel']);
        $temp = tempnam(sys_get_temp_dir(), 'tnl');
        copy($quelle, $temp);

        try {
            $zip = new ZipArchive;
            if ($zip->open($temp) !== true) {
                throw new RuntimeException('Die Excel-Vorlage lässt sich nicht öffnen.');
            }
            $xml = $this->fuellen((string) $zip->getFromName(self::BLATT), Teilnahmelisten::zeilen($event, $modell), $modell['spalten']);
            $zip->addFromString(self::BLATT, $xml);
            $zip->close();

            return (string) file_get_contents($temp);
        } finally {
            @unlink($temp);
        }
    }

    /**
     * @param  list<array{id: int, werte: list<string|int|null>}>  $zeilen
     * @param  list<array{label: string, wert: \Closure, zahl?: bool}>  $spalten
     */
    private function fuellen(string $xml, array $zeilen, array $spalten): string
    {
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = true;
        $dom->loadXML($xml, LIBXML_NONET);
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('m', self::NS);
        $daten = $xp->query('//m:sheetData')->item(0);

        // Formatierte Zeile als Muster für Zeilen über die Vorlage hinaus.
        $muster = $xp->query('//m:row[@r="'.self::VORLAGE_ZEILEN.'"]')->item(0);
        $letzte = max(self::VORLAGE_ZEILEN, count($zeilen) + 1);

        foreach ($zeilen as $i => $zeile) {
            $nr = $i + 2;
            $row = $xp->query('//m:row[@r="'.$nr.'"]')->item(0);
            if (! $row instanceof DOMElement) {
                $row = $muster->cloneNode(true);
                $row->setAttribute('r', (string) $nr);
                foreach ($row->childNodes as $c) {
                    if ($c instanceof DOMElement) {
                        $c->setAttribute('r', preg_replace('/\d+$/', (string) $nr, $c->getAttribute('r')));
                    }
                }
                $daten->appendChild($row);
            }
            foreach ($zeile['werte'] as $k => $wert) {
                $zelle = $xp->query('m:c[@r="'.chr(65 + $k).$nr.'"]', $row)->item(0);
                if ($zelle instanceof DOMElement) {
                    $this->setzen($dom, $zelle, $wert, (bool) ($spalten[$k]['zahl'] ?? false));
                }
            }
        }

        $dim = $xp->query('//m:dimension')->item(0);
        if ($dim instanceof DOMElement) {
            $dim->setAttribute('ref', 'A1:'.chr(64 + count($spalten)).$letzte);
        }

        return (string) $dom->saveXML();
    }

    private function setzen(DOMDocument $dom, DOMElement $zelle, string|int|null $wert, bool $zahl): void
    {
        while ($zelle->firstChild) {
            $zelle->removeChild($zelle->firstChild);
        }
        $zelle->removeAttribute('t');
        if ($wert === null || $wert === '') {
            return;
        }
        if ($zahl && is_numeric($wert)) {
            $zelle->appendChild($dom->createElementNS(self::NS, 'v', (string) (int) $wert));

            return;
        }
        $zelle->setAttribute('t', 'inlineStr');
        $is = $dom->createElementNS(self::NS, 'is');
        $t = $dom->createElementNS(self::NS, 't');
        $t->setAttribute('xml:space', 'preserve');
        $t->appendChild($dom->createTextNode((string) $wert));
        $is->appendChild($t);
        $zelle->appendChild($is);
    }
}
