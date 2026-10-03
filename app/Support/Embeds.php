<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Zwei-Klick-Einbettungen (Pflichtenheft 6.8, F-CON): Steht in einem Text
 * ein Link auf ein Video oder eine Karte allein in einem Absatz, wird daraus
 * ein Platzhalter. Erst nach einem Klick (oder mit Einwilligung „Externe
 * Inhalte“) lädt der Browser den fremden Inhalt – vorher geht keine Anfrage
 * an YouTube, Vimeo, OpenStreetMap oder Google.
 *
 * Die Einbett-Adressen werden aus geprüften IDs und Koordinaten neu
 * zusammengesetzt, nie aus dem eingegebenen Link übernommen.
 */
class Embeds
{
    public const PROVIDERS = [
        'youtube' => ['label' => 'YouTube', 'owner' => 'Google', 'kind' => 'video', 'frame' => 'https://www.youtube-nocookie.com'],
        'vimeo' => ['label' => 'Vimeo', 'owner' => 'Vimeo', 'kind' => 'video', 'frame' => 'https://player.vimeo.com'],
        'osm' => ['label' => 'OpenStreetMap', 'owner' => 'die OpenStreetMap Foundation', 'kind' => 'map', 'frame' => 'https://www.openstreetmap.org'],
        'google-maps' => ['label' => 'Google Maps', 'owner' => 'Google', 'kind' => 'map', 'frame' => 'https://maps.google.com'],
    ];

    /**
     * Herkunft aller möglichen Einbettungen für die CSP (frame-src).
     *
     * @return list<string>
     */
    public static function frameSources(): array
    {
        return array_values(array_unique(array_column(self::PROVIDERS, 'frame')));
    }

    /**
     * @return array{provider: string, embed: string, url: string}|null
     */
    public static function match(string $url): ?array
    {
        $url = html_entity_decode(trim($url));
        $parts = parse_url($url);
        $host = strtolower(preg_replace('/^(www\.|m\.)/', '', (string) ($parts['host'] ?? '')));
        $path = (string) ($parts['path'] ?? '');
        parse_str((string) ($parts['query'] ?? ''), $query);
        if (! in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
            return null;
        }

        $youtubeId = match (true) {
            $host === 'youtu.be' => ltrim($path, '/'),
            in_array($host, ['youtube.com', 'youtube-nocookie.com'], true) && $path === '/watch' => (string) ($query['v'] ?? ''),
            in_array($host, ['youtube.com', 'youtube-nocookie.com'], true) && preg_match('#^/(embed|shorts|live)/([^/]+)#', $path, $m) === 1 => $m[2],
            default => null,
        };
        if ($youtubeId !== null && preg_match('/^[A-Za-z0-9_-]{11}$/', $youtubeId)) {
            return ['provider' => 'youtube', 'embed' => 'https://www.youtube-nocookie.com/embed/'.$youtubeId.'?rel=0', 'url' => 'https://www.youtube.com/watch?v='.$youtubeId];
        }

        if (in_array($host, ['vimeo.com', 'player.vimeo.com'], true) && preg_match('#^/(?:video/)?(\d{5,12})(?:/|$)#', $path, $m)) {
            return ['provider' => 'vimeo', 'embed' => 'https://player.vimeo.com/video/'.$m[1].'?dnt=1', 'url' => 'https://vimeo.com/'.$m[1]];
        }

        if ($host === 'openstreetmap.org' && preg_match('#map=(\d{1,2})/(-?\d{1,2}(?:\.\d+)?)/(-?\d{1,3}(?:\.\d+)?)#', (string) ($parts['fragment'] ?? ''), $m)) {
            [$zoom, $lat, $lon] = [(int) $m[1], (float) $m[2], (float) $m[3]];
            $d = 0.02 * 2 ** max(0, 15 - $zoom);
            $bbox = implode(',', array_map(fn (float $v) => round($v, 5), [$lon - $d * 1.5, $lat - $d, $lon + $d * 1.5, $lat + $d]));

            return ['provider' => 'osm', 'embed' => 'https://www.openstreetmap.org/export/embed.html?bbox='.$bbox.'&layer=mapnik&marker='.round($lat, 5).','.round($lon, 5), 'url' => 'https://www.openstreetmap.org/#map='.$zoom.'/'.$lat.'/'.$lon];
        }

        if (in_array($host, ['google.com', 'google.de', 'maps.google.com', 'maps.google.de'], true) && str_starts_with($path, '/maps')) {
            $q = null;
            if (preg_match('#/@(-?\d{1,2}\.\d+),(-?\d{1,3}\.\d+)#', $path, $m)) {
                $q = $m[1].','.$m[2];
            } elseif (preg_match('#^/maps/place/([^/@]+)#', $path, $m)) {
                $q = urldecode(str_replace('+', ' ', $m[1]));
            } elseif (filled($query['q'] ?? null) && is_string($query['q'])) {
                $q = $query['q'];
            }
            if ($q !== null && mb_strlen($q) <= 200) {
                return ['provider' => 'google-maps', 'embed' => 'https://maps.google.com/maps?q='.rawurlencode($q).'&output=embed', 'url' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($q)];
            }
        }

        return null;
    }

    /**
     * Ersetzt Absätze, die nur aus einem passenden Link bestehen, durch den
     * Zwei-Klick-Platzhalter. Bereits bereinigtes HTML erwartet.
     */
    public static function render(string $html): string
    {
        if (stripos($html, '<a') === false) {
            return $html;
        }

        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="embeds-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($doc);
        $changed = false;
        foreach (iterator_to_array($xpath->query('//p[count(*) = 1 and a]')) as $p) {
            /** @var DOMElement $p */
            /** @var DOMElement $a */
            $a = $xpath->query('a', $p)->item(0);
            if (trim(str_replace("\u{00A0}", ' ', $p->textContent)) !== trim(str_replace("\u{00A0}", ' ', $a->textContent))) {
                continue;
            }
            $match = self::match($a->getAttribute('href'));
            if (! $match) {
                continue;
            }

            $title = trim($a->textContent);
            $markup = view('partials.embed', [
                ...$match,
                'title' => $title !== '' && ! str_starts_with($title, 'http') ? $title : null,
                'provider' => self::PROVIDERS[$match['provider']] + ['key' => $match['provider']],
            ])->render();

            $fragment = new DOMDocument;
            libxml_use_internal_errors(true);
            $fragment->loadHTML('<?xml encoding="UTF-8"><div>'.$markup.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();
            $node = $doc->importNode($fragment->documentElement->firstElementChild, true);
            $p->parentNode->replaceChild($node, $p);
            $changed = true;
        }

        if (! $changed) {
            return $html;
        }

        $root = $doc->getElementById('embeds-root');
        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }
}
