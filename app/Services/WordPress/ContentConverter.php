<?php

namespace App\Services\WordPress;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Throwable;

/**
 * Wandelt gerendertes WordPress-HTML (Gutenberg) in schlichtes HTML für das
 * CMS (F-MIG-03): Block-Klassen, Inline-Styles und Skript-Attribute fallen
 * weg, Bilder und Dateien kommen in die Mediathek, interne Links werden
 * umgeschrieben, Einbettungen werden zu Links. Was Nacharbeit braucht,
 * landet in $issues.
 */
class ContentConverter
{
    private const KEEP_ATTRIBUTES = [
        'a' => ['href'],
        'img' => ['src', 'alt', 'width', 'height'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan'],
        'ol' => ['start'],
    ];

    /** Elemente, deren Inhalt bleibt, die selbst aber entfallen. */
    private const UNWRAP = ['div', 'span', 'section', 'article', 'font', 'center'];

    private const DROP = ['script', 'style', 'noscript', 'form', 'input', 'button', 'svg', 'source'];

    /** @var list<string> */
    public array $issues = [];

    /**
     * @param  array<string, string>  $postPaths  alter WP-Pfad → neuer Pfad (importierte Beiträge)
     */
    public function __construct(private readonly MediaFetcher $media, private readonly array $postPaths = []) {}

    public function convert(string $html): string
    {
        $this->issues = [];
        $html = preg_replace('/<!--.*?-->/s', '', $html);
        if (trim(strip_tags($html, '<img><iframe><object>')) === '') {
            return '';
        }

        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="wp-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $root = $doc->getElementById('wp-root');
        $xpath = new DOMXPath($doc);

        foreach (iterator_to_array($xpath->query('.//'.implode('|.//', self::DROP), $root)) as $node) {
            $node->parentNode?->removeChild($node);
        }

        foreach (iterator_to_array($xpath->query('.//iframe', $root)) as $iframe) {
            $this->replaceEmbed($doc, $iframe, (string) $iframe->getAttribute('src'));
        }
        // Gutenberg-Dateiblock: Dateiname-Link plus „Herunterladen“-Knopf
        // auf dieselbe Datei – nur einen Link als eigenen Absatz behalten.
        foreach (iterator_to_array($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " wp-block-file__button ")]', $root)) as $button) {
            $button->parentNode?->removeChild($button);
        }
        foreach (iterator_to_array($xpath->query('.//div[contains(concat(" ", normalize-space(@class), " "), " wp-block-file ")]', $root)) as $block) {
            $p = $doc->createElement('p');
            while ($block->firstChild) {
                $p->appendChild($block->firstChild);
            }
            $block->parentNode?->replaceChild($p, $block);
        }
        foreach (iterator_to_array($xpath->query('.//object', $root)) as $object) {
            // Gutenberg-Dateiblock: PDF-Vorschau; der Download-Link daneben bleibt.
            $object->parentNode?->removeChild($object);
        }

        foreach (iterator_to_array($xpath->query('.//img', $root)) as $img) {
            $this->convertImage($img);
        }
        foreach (iterator_to_array($xpath->query('.//a', $root)) as $a) {
            $this->convertLink($a);
        }

        foreach (iterator_to_array($xpath->query('.//*', $root)) as $el) {
            $this->cleanAttributes($el);
        }
        foreach (iterator_to_array($xpath->query('.//'.implode('|.//', self::UNWRAP), $root)) as $el) {
            $this->unwrap($el);
        }
        // Leere Absätze (oft nur &nbsp;) entfernen.
        foreach (iterator_to_array($xpath->query('.//p', $root)) as $p) {
            if (trim(str_replace("\u{00A0}", ' ', $p->textContent)) === '' && $xpath->query('.//img', $p)->length === 0) {
                $p->parentNode?->removeChild($p);
            }
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        $this->issues = array_values(array_unique($this->issues));

        return trim(preg_replace("/\n{3,}/", "\n\n", $out));
    }

    private function convertImage(DOMElement $img): void
    {
        $src = $img->getAttribute('src') ?: $img->getAttribute('data-src');
        $alt = trim($img->getAttribute('alt'));

        if ($src === '' || ! $this->media->isLocal($src)) {
            $this->issues[] = 'Bild von fremder Adresse entfernt: '.$src;
            $img->parentNode?->removeChild($img);

            return;
        }

        try {
            $media = $this->media->fetch($src, $alt);
            $img->setAttribute('src', $media ? MediaFetcher::path($media) : MediaFetcher::canonical($src));
            $img->removeAttribute('width');
            $img->removeAttribute('height');
        } catch (Throwable $e) {
            $this->issues[] = 'Bild nicht übernommen: '.$e->getMessage();
            $img->parentNode?->removeChild($img);

            return;
        }

        if ($alt === '') {
            $this->issues[] = 'Bild ohne Alternativtext – in der Mediathek ergänzen';
        }
        $this->issues[] = 'Bildrechte und Bildnachweis der übernommenen Bilder prüfen';
        $this->issues[] = 'Bilder auf KI-Erzeugung prüfen und ggf. die KI-Kennzeichnung setzen';
    }

    private function convertLink(DOMElement $a): void
    {
        $href = html_entity_decode(trim($a->getAttribute('href')), ENT_QUOTES | ENT_HTML5);
        if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
            return;
        }

        $href = self::withoutTracking($href);

        if (! $this->media->isLocal($href)) {
            $a->setAttribute('href', $href);

            return;
        }

        $path = (string) parse_url($href, PHP_URL_PATH);
        parse_str((string) parse_url($href, PHP_URL_QUERY), $query);

        // Dateien (Uploads, Download-Manager) in die Mediathek übernehmen.
        if (str_contains($path, '/wp-content/uploads/') || isset($query['wpdmdl'])) {
            try {
                $media = $this->media->fetch($href, 'Datei: '.trim($a->textContent));
                $a->setAttribute('href', $media ? MediaFetcher::path($media) : MediaFetcher::canonical($href));
                if (str_ends_with(mb_strtolower($media?->path ?? $path), '.pdf') || isset($query['wpdmdl'])) {
                    $this->issues[] = 'PDF übernommen – auf Personenbezug und Bildrechte prüfen';
                }
            } catch (Throwable $e) {
                $this->issues[] = 'Datei nicht übernommen: '.$e->getMessage();
            }

            return;
        }

        $key = '/'.trim(mb_strtolower(rawurldecode($path)), '/');
        if (isset($this->postPaths[$key])) {
            $a->setAttribute('href', $this->postPaths[$key]);

            return;
        }

        // Andere Seite der alten Website: relativ verlinken; greift, sobald
        // es dort eine Seite oder Weiterleitung gibt.
        $a->setAttribute('href', $key === '/' ? '/' : $key);
        $this->issues[] = 'Link auf alte Seite '.$key.' – Ziel prüfen';
    }

    private function replaceEmbed(DOMDocument $doc, DOMElement $node, string $src): void
    {
        $url = self::embedToWatchUrl($src);
        $p = $doc->createElement('p');
        $a = $doc->createElement('a', self::linkLabel($url));
        $a->setAttribute('href', $url);
        $p->appendChild($a);
        $node->parentNode?->replaceChild($p, $node);
        $this->issues[] = 'Einbettung als Link übernommen: '.$url;
    }

    private function cleanAttributes(DOMElement $el): void
    {
        $keep = self::KEEP_ATTRIBUTES[$el->tagName] ?? [];
        foreach (iterator_to_array($el->attributes) as $attr) {
            if (! in_array($attr->name, $keep, true)) {
                $el->removeAttribute($attr->name);
            }
        }
    }

    private function unwrap(DOMNode $el): void
    {
        $parent = $el->parentNode;
        if (! $parent) {
            return;
        }
        while ($el->firstChild) {
            $parent->insertBefore($el->firstChild, $el);
        }
        $parent->removeChild($el);
    }

    public static function withoutTracking(string $url): string
    {
        $parts = parse_url($url);
        if (! isset($parts['query'])) {
            return $url;
        }
        parse_str($parts['query'], $query);
        $query = array_filter($query, fn ($k) => ! preg_match('/^(utm_|fbclid|gclid|mc_)/', (string) $k), ARRAY_FILTER_USE_KEY);
        $base = strtok($url, '?');

        return $query ? $base.'?'.http_build_query($query) : $base;
    }

    public static function embedToWatchUrl(string $src): string
    {
        if (preg_match('#youtube(?:-nocookie)?\.com/embed/([\w-]+)#', $src, $m)) {
            return 'https://www.youtube.com/watch?v='.$m[1];
        }
        if (preg_match('#player\.vimeo\.com/video/(\d+)#', $src, $m)) {
            return 'https://vimeo.com/'.$m[1];
        }

        return $src;
    }

    public static function linkLabel(string $url): string
    {
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));

        return match (true) {
            str_contains($host, 'youtube.') || str_contains($host, 'youtu.be') => 'Video auf YouTube ansehen',
            str_contains($host, 'vimeo.') => 'Video auf Vimeo ansehen',
            str_contains($host, 'brevo') || str_contains($host, 'sendibt') || str_contains($host, 'sendinblue') => 'Newsletter online lesen',
            default => 'Weiterlesen auf '.preg_replace('/^www\./', '', $host),
        };
    }
}
