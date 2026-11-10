<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Hinweise zur Barrierefreiheit beim Speichern von Seiten und Beiträgen
 * (docs/plan-ehrenamt.md, Nr. 13). Nur Hinweise, kein Zwang: Bilder ohne
 * Alternativtext, Linktexte wie „hier“, Überschriften-Reihenfolge, sehr
 * lange Sätze.
 */
class Barrierefreiheit
{
    /** Ab so vielen Wörtern gilt ein Satz als sehr lang. */
    public const LANGER_SATZ = 30;

    /** Linktexte, die nicht sagen, wohin der Link führt. */
    public const NICHTSSAGEND = ['hier', 'hier klicken', 'klick hier', 'klicken sie hier', 'mehr', 'mehr dazu', 'weiter', 'weiterlesen', 'link', 'dieser link', 'zum link', 'click here', 'here'];

    /**
     * @return list<string>
     */
    public static function pruefen(?string $html): array
    {
        $html = trim((string) $html);
        if ($html === '') {
            return [];
        }

        $dom = new DOMDocument;
        $vorher = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($vorher);
        $xpath = new DOMXPath($dom);
        $hinweise = [];

        // 1. Bilder ohne Alternativtext (außer ausdrücklich dekorativ).
        $ohneAlt = 0;
        foreach ($xpath->query('//img') ?: [] as $img) {
            /** @var DOMElement $img */
            $dekorativ = in_array($img->getAttribute('role'), ['presentation', 'none'], true) || $img->getAttribute('aria-hidden') === 'true';
            if (! $dekorativ && trim($img->getAttribute('alt')) === '') {
                $ohneAlt++;
            }
        }
        if ($ohneAlt > 0) {
            $hinweise[] = ($ohneAlt === 1 ? 'Ein Bild hat' : "{$ohneAlt} Bilder haben").' keinen Alternativtext. Beschreib kurz, was zu sehen ist (Bild anklicken → Alternativtext); Menschen mit Screenreader hören sonst nur „Bild“.';
        }

        // 2. Nichtssagende Linktexte.
        $schlecht = [];
        foreach ($xpath->query('//a') ?: [] as $a) {
            $text = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $a->textContent), " \t\n\r\0\x0B.:!»«„“\""));
            if (in_array($text, self::NICHTSSAGEND, true)) {
                $schlecht[] = '„'.trim($a->textContent).'“';
            }
        }
        if ($schlecht !== []) {
            $hinweise[] = 'Linktext '.implode(', ', array_unique($schlecht)).' sagt nicht, wohin der Link führt. Besser das Ziel nennen, z. B. „Anmeldeformular zum Pfingstlager“.';
        }

        // 3. Überschriften: h1 ist der Titel; keine Ebene überspringen.
        $ebenen = [];
        foreach ($xpath->query('//h1|//h2|//h3|//h4|//h5|//h6') ?: [] as $h) {
            $ebenen[] = (int) substr($h->nodeName, 1);
        }
        if (in_array(1, $ebenen, true)) {
            $hinweise[] = 'Im Text steht eine Überschrift 1. Die gibt es schon (der Titel); bitte mit Überschrift 2 beginnen.';
        }
        $letzte = 1;
        foreach ($ebenen as $ebene) {
            if ($ebene > $letzte + 1) {
                $hinweise[] = "Nach Überschrift {$letzte} folgt Überschrift {$ebene}. Bitte keine Ebene überspringen, sonst verlieren Screenreader-Nutzer*innen die Gliederung.";
                break;
            }
            $letzte = $ebene;
        }

        // 4. Sehr lange Sätze.
        $text = trim((string) preg_replace('/\s+/u', ' ', $dom->textContent));
        $lang = array_values(array_filter(
            preg_split('/(?<=[.!?])\s+/u', $text) ?: [],
            fn (string $satz) => count(preg_split('/\s+/u', trim($satz)) ?: []) > self::LANGER_SATZ,
        ));
        if ($lang !== []) {
            $anfang = implode(' ', array_slice(preg_split('/\s+/u', $lang[0]) ?: [], 0, 8));
            $hinweise[] = (count($lang) === 1 ? 'Ein Satz hat' : count($lang).' Sätze haben').' mehr als '.self::LANGER_SATZ.' Wörter (z. B. „'.$anfang.' …“). Kürzere Sätze sind für alle leichter zu lesen.';
        }

        return $hinweise;
    }
}
