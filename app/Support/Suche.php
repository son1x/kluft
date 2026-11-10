<?php

namespace App\Support;

use App\Models\Download;
use App\Models\Event;
use App\Models\GeorgsboteIssue;
use App\Models\GlossaryTerm;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Suche auf der Website (docs/plan-ehrenamt.md Nr. 3) über veröffentlichte
 * Seiten, Beiträge, Veranstaltungen, Downloads, Zeitschriften und Glossar.
 * Einfache Datenbanksuche: alle Wörter müssen vorkommen, Treffer im Titel
 * zählen mehr. Suchbegriffe werden nicht gespeichert.
 */
class Suche
{
    public const MAX = 50;

    /**
     * @return Collection<int, array{art: string, titel: string, url: ?string, auszug: string, datum: ?string, punkte: int}>
     */
    public static function finden(string $eingabe): Collection
    {
        $woerter = self::woerter($eingabe);
        if ($woerter === []) {
            return collect();
        }

        $treffer = collect();
        $quellen = [
            ['Seite', Page::query()->published(), ['title', 'body', 'seo_description'],
                fn (Page $p) => Ausbau::seiteSichtbar($p->slug) ? [$p->title, url('/'.$p->slug), $p->body, null] : null],
            ['Beitrag', Post::query()->published(), ['title', 'teaser', 'body'],
                fn (Post $p) => Ausbau::termine() ? [$p->title, route('posts.show', $p->slug), trim($p->teaser.' '.$p->body), $p->published_at?->format('d.m.Y')] : null],
            ['Veranstaltung', Event::query()->visible(), ['title', 'teaser', 'description', 'location'],
                fn (Event $e) => Ausbau::termine() ? [$e->title, route('events.show', $e->slug), trim($e->teaser.' '.$e->description), $e->dateLabel()] : null],
        ];
        if (Features::enabled(Features::DOWNLOADS) && Route::has('downloads.file')) {
            $quellen[] = ['Download', Download::query()->published(), ['title', 'description'],
                fn (Download $d) => [$d->title, $d->url(), (string) $d->description, null]];
        }
        if (Features::enabled(Features::GEORGSBOTE) && Route::has('georgsbote.pdf')) {
            $quellen[] = ['Zeitschrift', GeorgsboteIssue::query()->published(), ['title', 'inhalt'],
                fn (GeorgsboteIssue $g) => [$g->title, $g->downloadUrl(), (string) $g->inhalt, null]];
        }
        $quellen[] = ['Begriff', GlossaryTerm::query(), ['term', 'explanation'],
            fn (GlossaryTerm $g) => [$g->term, null, (string) $g->explanation, null]];

        foreach ($quellen as [$art, $query, $felder, $abbilden]) {
            foreach ($woerter as $wort) {
                $muster = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $wort).'%';
                $query->where(fn (Builder $q) => collect($felder)->each(fn (string $f) => $q->orWhere($f, 'like', $muster)));
            }
            foreach ($query->limit(100)->get() as $modell) {
                $daten = $abbilden($modell);
                if ($daten === null) {
                    continue;
                }
                [$titel, $url, $text, $datum] = $daten;
                $klartext = self::klartext((string) $text);
                $punkte = collect($woerter)->sum(fn (string $w) => (Str::contains($titel, $w, true) ? 10 : 0) + min(5, substr_count(mb_strtolower($klartext), mb_strtolower($w))));
                $treffer->push(['art' => $art, 'titel' => $titel, 'url' => $url, 'auszug' => self::auszug($klartext, $woerter), 'datum' => $datum, 'punkte' => $punkte]);
            }
        }

        return $treffer->sortByDesc('punkte')->take(self::MAX)->values();
    }

    /** @return list<string> */
    public static function woerter(string $eingabe): array
    {
        $woerter = preg_split('/\s+/u', trim(mb_substr($eingabe, 0, 100)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_slice(array_unique(array_filter($woerter, fn (string $w) => mb_strlen($w) >= 2)), 0, 5));
    }

    public static function klartext(string $html): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['<br', '</p>', '</li>', '</h'], [' <br', ' </p>', ' </li>', ' </h'], $html)), ENT_QUOTES | ENT_HTML5)));
    }

    /**
     * Ausschnitt um den ersten Treffer, HTML-sicher, Suchwörter in <mark>.
     *
     * @param  list<string>  $woerter
     */
    public static function auszug(string $klartext, array $woerter, int $laenge = 180): string
    {
        $pos = collect($woerter)->map(fn (string $w) => mb_stripos($klartext, $w))->filter(fn ($p) => $p !== false)->min() ?? 0;
        $start = max(0, $pos - 60);
        $teil = mb_substr($klartext, $start, $laenge);
        $teil = ($start > 0 ? '… ' : '').$teil.(mb_strlen($klartext) > $start + $laenge ? ' …' : '');
        $sicher = e($teil);
        foreach ($woerter as $w) {
            $sicher = (string) preg_replace('/('.preg_quote(e($w), '/').')/iu', '<mark>$1</mark>', $sicher);
        }

        return $sicher;
    }
}
