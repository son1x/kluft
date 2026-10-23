<?php

namespace App\Services\Sammelbestellung;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Einzelne Produktseiten des Rüsthaus-Shops (Shopware) abrufen: Name,
 * Beschreibung, Bild, Preis und Größen mit ihren Artikelnummern. Nur auf
 * Anlass (Freigabe, Aktualisieren, freie Eingabe), höchstens eine Anfrage pro
 * Sekunde, mit eigener Kennung; Ergebnisse 24 Stunden zwischengespeichert
 * (Pflichtenheft Sammelbestellung, SB-16). Klappt etwas nicht, gibt es null
 * und es geht mit Handeingabe weiter.
 */
class Ruesthaus
{
    public const HOST = 'www.ruesthaus.de';

    private const MAX_VARIANTEN = 40;

    /**
     * Produkt zu Artikelnummer oder Rüsthaus-Link.
     *
     * @return array{nummer: string, name: string, beschreibung: ?string, url: string, kategorie: ?string, bild: ?string, preis_cent: int, varianten: list<array{nummer: string, bezeichnung: ?string, preis_cent: int}>}|null
     */
    public function produkt(string $eingabe, bool $mitVarianten = true): ?array
    {
        $eingabe = trim($eingabe);
        if ($eingabe === '' || mb_strlen($eingabe) > 300) {
            return null;
        }

        return Cache::remember('ruesthaus3:'.($mitVarianten ? 'v:' : '').sha1(mb_strtolower($eingabe)), now()->addDay(), function () use ($eingabe, $mitVarianten) {
            try {
                $url = self::istLink($eingabe) ? self::sauber($eingabe) : $this->suchen($eingabe);
                if ($url === null) {
                    return null;
                }
                $seite = self::produktseite($this->holen($url), $url);
                if ($seite === null) {
                    return null;
                }
                $varianten = [['nummer' => $seite['nummer'], 'bezeichnung' => $seite['auswahl'][$seite['gewaehlt']] ?? null, 'preis_cent' => $seite['preis_cent']]];
                if ($mitVarianten && $seite['gruppe'] !== null) {
                    $varianten = [];
                    foreach (array_slice($seite['auswahl'], 0, self::MAX_VARIANTEN, true) as $wert => $bezeichnung) {
                        $v = self::produktseite($this->holen($url.'?'.http_build_query([$seite['gruppe'] => $wert])), $url);
                        // Auswahl ohne eigene Nummer (z. B. „Sonderanfertigung“ liefert die
                        // Nummer der ersten Größe): nicht über die Sammelliste bestellbar.
                        if ($v !== null && ! in_array($v['nummer'], array_column($varianten, 'nummer'), true)) {
                            $varianten[] = ['nummer' => $v['nummer'], 'bezeichnung' => $bezeichnung, 'preis_cent' => $v['preis_cent']];
                        }
                    }
                }

                return [
                    'nummer' => $seite['nummer'], 'name' => $seite['name'], 'beschreibung' => $seite['beschreibung'], 'url' => $url,
                    'kategorie' => self::kategorie($url), 'bild' => $seite['bild'], 'preis_cent' => $seite['preis_cent'], 'varianten' => $varianten,
                ];
            } catch (RuntimeException) {
                return null;
            }
        });
    }

    /** Bilddatei eines freigegebenen Artikels laden (für die Mediathek). */
    public function bild(string $url): ?string
    {
        if (parse_url($url, PHP_URL_HOST) !== self::HOST) {
            return null;
        }
        $antwort = Http::withHeaders(['User-Agent' => self::kennung()])->timeout(20)->get($url);

        return $antwort->successful() && str_starts_with((string) $antwort->header('Content-Type'), 'image/') ? $antwort->body() : null;
    }

    /** Suche nach Artikelnummer: erste Produktadresse aus der Trefferliste. */
    private function suchen(string $nummer): ?string
    {
        if (! preg_match('/^[0-9A-Za-z.\-]{3,40}$/', $nummer)) {
            return null;
        }
        $html = $this->holen('https://'.self::HOST.'/search?'.http_build_query(['sSearch' => $nummer]));
        if (preg_match('/class="product--title"[^>]*href="([^"]+)"|href="([^"]+)"[^>]*class="product--title"/', $html, $m)) {
            $url = self::sauber(html_entity_decode($m[1] ?: $m[2]));

            return parse_url($url, PHP_URL_HOST) === self::HOST ? $url : null;
        }

        // Shopware leitet bei genau einem Treffer manchmal direkt auf die Produktseite.
        return self::produktseite($html, '') !== null && preg_match('/<link rel="canonical" href="([^"]+)"/', $html, $c) ? self::sauber($c[1]) : null;
    }

    private function holen(string $url): string
    {
        // Höchstens eine Anfrage pro Sekunde (in Tests 0).
        $pause = (int) config('dpsg.sammelbestellung_pause_ms', 1000);
        $letzte = (float) Cache::get('ruesthaus:letzte', 0);
        $warten = $letzte + $pause / 1000 - microtime(true);
        if ($warten > 0) {
            usleep((int) ($warten * 1_000_000));
        }
        Cache::put('ruesthaus:letzte', microtime(true), 60);

        $antwort = Http::withHeaders(['User-Agent' => self::kennung(), 'Accept-Language' => 'de'])->timeout(20)->get($url);
        if (! $antwort->successful()) {
            throw new RuntimeException('Rüsthaus antwortet mit '.$antwort->status());
        }

        return $antwort->body();
    }

    /**
     * Beschreibung als Text mit Absätzen. Ohne Maßtabelle samt Messanleitung
     * danach (die Größen stehen eigens am Artikel); Umlaute stehen beim
     * Rüsthaus teils ohne Semikolon (&ouml).
     */
    public static function beschreibung(string $html): ?string
    {
        $html = (string) preg_replace('/<table.*?<\/table>.*?(?=<\/p>|$)/si', '', $html);
        $html = (string) preg_replace('/&(auml|ouml|uuml|Auml|Ouml|Uuml|szlig|nbsp|amp|quot)(?!;)/', '&$1;', $html);
        $html = (string) preg_replace('/<br\s*\/?>|<\/p>|<\/li>/i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
        $zeilen = array_map(fn ($z) => trim((string) preg_replace('/[^\S\n]+/u', ' ', $z)), explode("\n", $text));
        $text = trim((string) preg_replace('/\n{3,}/', "\n\n", implode("\n", $zeilen)));

        return $text !== '' ? mb_substr($text, 0, 3000) : null;
    }

    /**
     * Produktseite auslesen; null, wenn es keine ist.
     *
     * @return array{nummer: string, name: string, beschreibung: ?string, bild: ?string, preis_cent: int, gruppe: ?string, auswahl: array<string, string>, gewaehlt: ?string}|null
     */
    public static function produktseite(string $html, string $url): ?array
    {
        if (! preg_match('/itemprop="sku"[^>]*>\s*([^<\s]+)\s*</', $html, $sku)
            || ! preg_match('/itemprop="price" content="([\d.]+)"/', $html, $preis)
            || ! preg_match('/<h1 class="product--title"[^>]*>\s*([^<]+?)\s*</', $html, $titel)) {
            return null;
        }
        $beschreibung = preg_match('/class="product--description"[^>]*>(.*?)<\/div>/s', $html, $b) ? self::beschreibung($b[1]) : null;
        $bild = preg_match('/data-img-large="([^"]+)"/', $html, $i) ? html_entity_decode($i[1]) : null;
        $gruppe = null;
        $auswahl = [];
        $gewaehlt = null;
        if (preg_match('/<select name="(group\[\d+\])"[^>]*>(.*?)<\/select>/s', $html, $s)) {
            $gruppe = $s[1];
            preg_match_all('/<option([^>]*)value="(\d+)"[^>]*>\s*([^<]+?)\s*<\/option>/', $s[2], $optionen, PREG_SET_ORDER);
            foreach ($optionen as $o) {
                $auswahl[$o[2]] = html_entity_decode($o[3]);
                if (str_contains($o[1], 'selected')) {
                    $gewaehlt = $o[2];
                }
            }
        }

        return [
            'nummer' => trim($sku[1]), 'name' => html_entity_decode(trim($titel[1])), 'beschreibung' => $beschreibung, 'bild' => $bild,
            'preis_cent' => (int) round((float) $preis[1] * 100), 'gruppe' => $gruppe, 'auswahl' => $auswahl, 'gewaehlt' => $gewaehlt,
        ];
    }

    /** Erster Teil der Adresse, z. B. „zelte“ oder „dpsg-artikel“ – für die Sperrliste. */
    public static function kategorie(string $url): ?string
    {
        $teile = array_values(array_filter(explode('/', (string) parse_url($url, PHP_URL_PATH))));

        return $teile[0] ?? null;
    }

    public static function istLink(string $eingabe): bool
    {
        return parse_url($eingabe, PHP_URL_HOST) === self::HOST && in_array(parse_url($eingabe, PHP_URL_SCHEME), ['http', 'https'], true);
    }

    /** Ohne Abfrageteil und Anker, immer https. */
    private static function sauber(string $url): string
    {
        return 'https://'.self::HOST.(string) parse_url($url, PHP_URL_PATH);
    }

    private static function kennung(): string
    {
        return 'Kluft/1.0 (+'.rtrim((string) config('app.url'), '/').')';
    }
}
