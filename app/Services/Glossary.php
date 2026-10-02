<?php

namespace App\Services;

use App\Models\GlossaryTerm;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Begriffs-Hover: markiert im redaktionellen HTML das jeweils erste
 * Vorkommen eines Glossar-Begriffs (z. B. „DAK“) mit einer Erklärung, die
 * beim Darüberfahren, Antippen oder per Tastatur erscheint und von
 * Screenreadern mitgelesen wird. Links, Überschriften und Code bleiben
 * unberührt.
 */
class Glossary
{
    private const TERMS_KEY = 'glossar:terms';

    private const VERSION_KEY = 'glossar:version';

    public static function flush(): void
    {
        Cache::forget(self::TERMS_KEY);
        Cache::increment(self::VERSION_KEY) ?: Cache::forever(self::VERSION_KEY, 2);
    }

    /**
     * @return array<string, string> Begriff → Erklärung, längste zuerst
     */
    public function terms(): array
    {
        return Cache::rememberForever(self::TERMS_KEY, function () {
            if (! Schema::hasTable('glossary_terms')) {
                return [];
            }
            $terms = GlossaryTerm::query()->pluck('explanation', 'term')->all();
            uksort($terms, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a) ?: strcmp($a, $b));

            return $terms;
        });
    }

    public function annotate(string $html): string
    {
        $terms = array_filter($this->terms(), fn (string $explanation, string $term) => str_contains($html, $term), ARRAY_FILTER_USE_BOTH);
        if ($terms === [] || trim($html) === '') {
            return $html;
        }

        $key = 'glossar:html:'.(int) Cache::get(self::VERSION_KEY, 1).':'.md5($html);

        return Cache::remember($key, now()->addDay(), fn () => $this->process($html, $terms));
    }

    /**
     * @param  array<string, string>  $terms
     */
    private function process(string $html, array $terms): string
    {
        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="glossar-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        $root = $doc->getElementById('glossar-root');
        if (! $root) {
            return $html;
        }
        $xpath = new DOMXPath($doc);
        $skip = 'ancestor::a or ancestor::abbr or ancestor::h1 or ancestor::h2 or ancestor::h3 or ancestor::h4 or ancestor::code or ancestor::pre or ancestor::button';

        foreach ($terms as $term => $explanation) {
            $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($term, '/').'(?![\p{L}\p{N}])/u';
            foreach ($xpath->query('.//text()[not('.$skip.')]', $root) as $text) {
                if (! preg_match($pattern, $text->nodeValue, $m, PREG_OFFSET_CAPTURE)) {
                    continue;
                }
                $value = $text->nodeValue;
                $before = substr($value, 0, $m[0][1]);
                $after = substr($value, $m[0][1] + strlen($term));

                $abbr = $doc->createElement('abbr');
                $abbr->setAttribute('class', 'glossar');
                $abbr->setAttribute('tabindex', '0');
                $abbr->setAttribute('data-erklaerung', $explanation);
                $abbr->appendChild($doc->createTextNode($term));
                $sr = $doc->createElement('span');
                $sr->setAttribute('class', 'sr-only');
                $sr->appendChild($doc->createTextNode(' ('.$explanation.')'));
                $abbr->appendChild($sr);

                $parent = $text->parentNode;
                $parent->insertBefore($doc->createTextNode($before), $text);
                $parent->insertBefore($abbr, $text);
                $parent->insertBefore($doc->createTextNode($after), $text);
                $parent->removeChild($text);

                break; // nur das erste Vorkommen je Begriff
            }
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }
}
