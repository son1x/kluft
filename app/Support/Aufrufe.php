<?php

namespace App\Support;

use App\Models\Event;
use App\Models\Page;
use App\Models\Post;
use App\Models\Seitenaufruf;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Seitenaufrufe ohne Personenbezug (docs/aufrufe.md): Pfad normalisieren,
 * Zähler erhöhen, Auswertung fürs Backend.
 */
class Aufrufe
{
    /** Zähler werden nach so vielen Monaten gelöscht (Vorjahresvergleich bleibt möglich). */
    public const MONATE = 25;

    public const ZEITRAEUME = [
        '7' => 'Letzte 7 Tage',
        '30' => 'Letzte 30 Tage',
        'monat' => 'Aktueller Monat',
        'vormonat' => 'Vormonat',
        'jahr' => 'Aktuelles Jahr',
    ];

    /** Seite „Aufrufe“, Widget: Verwaltung, Vorstand und Admins, nur mit Schalter an. */
    public static function zugriff(?User $user): bool
    {
        return Features::enabled(Features::AUFRUFE)
            && $user instanceof User && $user->isActive()
            && $user->darf('aufrufe');
    }

    /**
     * Ohne Query-String und abschließenden „/“, Startseite „/“, nur
     * Kleinbuchstaben, höchstens 255 Zeichen.
     */
    public static function pfad(string $pfad): string
    {
        $pfad = (string) strtok($pfad, '?#');
        $pfad = '/'.trim(rawurldecode($pfad), '/');

        return mb_substr(mb_strtolower($pfad), 0, 255);
    }

    /** Erster gezählter Tag oder null, wenn noch nichts gezählt ist (z. B. nach dem Zurücksetzen). */
    public static function seit(): ?Carbon
    {
        $datum = Seitenaufruf::query()->min('datum');

        return $datum ? Carbon::parse($datum) : null;
    }

    /** Alle gezählten Aufrufe seit Beginn. */
    public static function gesamt(): int
    {
        return (int) Seitenaufruf::query()->sum('aufrufe');
    }

    /** Zähler für Pfad und Tag um eins erhöhen (MariaDB und SQLite). */
    public static function erhoehen(string $pfad, string $datum): void
    {
        DB::table('seitenaufrufe')->upsert(
            [['datum' => $datum, 'pfad' => $pfad, 'aufrufe' => 1]],
            ['datum', 'pfad'],
            ['aufrufe' => DB::raw('aufrufe + 1')],
        );
    }

    /**
     * Erster und letzter Tag eines Zeitraums aus ZEITRAEUME.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function zeitraum(string $schluessel): array
    {
        $heute = today();

        return match ($schluessel) {
            '7' => [$heute->copy()->subDays(6), $heute],
            'monat' => [$heute->copy()->startOfMonth(), $heute],
            'vormonat' => [$heute->copy()->subMonthNoOverflow()->startOfMonth(), $heute->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            'jahr' => [$heute->copy()->startOfYear(), $heute],
            default => [$heute->copy()->subDays(29), $heute],
        };
    }

    /** @return Builder<Seitenaufruf> */
    public static function imZeitraum(Carbon $von, Carbon $bis)
    {
        return Seitenaufruf::query()->whereBetween('datum', [$von->toDateString(), $bis->toDateString()]);
    }

    public static function summe(Carbon $von, Carbon $bis): int
    {
        return (int) self::imZeitraum($von, $bis)->sum('aufrufe');
    }

    /**
     * Aufrufe je Tag (bis 62 Tage) bzw. je Monat, lückenlos.
     *
     * @return array<string, int> Beschriftung => Aufrufe
     */
    public static function verlauf(Carbon $von, Carbon $bis): array
    {
        $jeTag = self::imZeitraum($von, $bis)
            ->selectRaw('datum, sum(aufrufe) as n')->groupBy('datum')->toBase()->get()
            ->mapWithKeys(fn ($zeile) => [Carbon::parse($zeile->datum)->toDateString() => (int) $zeile->n]);

        $monatlich = $von->diffInDays($bis) > 62;
        $verlauf = [];
        for ($tag = $von->copy(); $tag->lte($bis); $tag->addDay()) {
            $schluessel = $monatlich ? $tag->translatedFormat('M Y') : $tag->format('d.m.');
            $verlauf[$schluessel] = ($verlauf[$schluessel] ?? 0) + ($jeTag[$tag->toDateString()] ?? 0);
        }

        return $verlauf;
    }

    /**
     * Meistaufgerufene Pfade im Zeitraum.
     *
     * @return Collection<int, object{pfad: string, aufrufe: int}&\stdClass>
     */
    public static function top(Carbon $von, Carbon $bis, int $anzahl = 5): Collection
    {
        return self::imZeitraum($von, $bis)
            ->selectRaw('pfad, sum(aufrufe) as aufrufe')->groupBy('pfad')
            ->orderByDesc('aufrufe')->orderBy('pfad')->limit($anzahl)->toBase()->get()
            ->map(fn ($zeile) => (object) ['pfad' => (string) $zeile->pfad, 'aufrufe' => (int) $zeile->aufrufe]);
    }

    /**
     * Titel zum Pfad, wo möglich: Beitrag, Veranstaltung, Seite, Startseite.
     * Sonst null (dann bleibt der Pfad stehen).
     *
     * @param  list<string>  $pfade
     * @return array<string, string> Pfad => Titel
     */
    public static function titel(array $pfade): array
    {
        $titel = [];
        $slugs = ['posts' => [], 'events' => [], 'pages' => []];
        foreach ($pfade as $pfad) {
            if ($pfad === '/') {
                $titel['/'] = 'Startseite';
            } elseif (preg_match('#^/aktuelles/([^/]+)$#', $pfad, $m)) {
                $slugs['posts'][$m[1]] = $pfad;
            } elseif (preg_match('#^/veranstaltungen/([^/]+)$#', $pfad, $m)) {
                $slugs['events'][$m[1]] = $pfad;
            } elseif (preg_match('#^/([^/]+)$#', $pfad, $m)) {
                $slugs['pages'][$m[1]] = $pfad;
            }
        }

        foreach (['posts' => Post::class, 'events' => Event::class, 'pages' => Page::class] as $art => $model) {
            if ($slugs[$art] === []) {
                continue;
            }
            $model::query()->whereIn(DB::raw('lower(slug)'), array_keys($slugs[$art]))->get(['slug', 'title'])
                ->each(function ($eintrag) use ($art, $slugs, &$titel) {
                    $pfad = $slugs[$art][mb_strtolower($eintrag->slug)] ?? null;
                    if ($pfad !== null && filled($eintrag->title)) {
                        $titel[$pfad] = $eintrag->title;
                    }
                });
        }

        return $titel;
    }

    /**
     * Pfade von Beiträgen, Veranstaltungen und Seiten, deren Titel den
     * Suchbegriff enthält (Suche in der Tabelle „Aufrufe“).
     *
     * @return list<string>
     */
    public static function pfadeMitTitel(string $suche): array
    {
        $like = '%'.str_replace(['%', '_', '\\'], '', $suche).'%';
        $pfade = [];
        foreach (['/aktuelles/' => Post::class, '/veranstaltungen/' => Event::class, '/' => Page::class] as $vorne => $model) {
            foreach ($model::query()->where('title', 'like', $like)->limit(200)->pluck('slug') as $slug) {
                $pfade[] = self::pfad($vorne.$slug);
            }
        }
        if (str_contains(mb_strtolower('Startseite'), mb_strtolower($suche))) {
            $pfade[] = '/';
        }

        return array_values(array_unique($pfade));
    }

    /**
     * Für „Beim Bearbeiten“: Aufrufe der letzten 30 Tage und insgesamt.
     *
     * @return array{0: int, 1: int}
     */
    public static function fuerPfad(string $pfad): array
    {
        [$von, $bis] = self::zeitraum('30');
        $pfad = self::pfad($pfad);

        return [
            (int) self::imZeitraum($von, $bis)->where('pfad', $pfad)->sum('aufrufe'),
            (int) Seitenaufruf::query()->where('pfad', $pfad)->sum('aufrufe'),
        ];
    }

    /** Text „Aufrufe: 30 Tage n, gesamt m“ oder null, wenn der Schalter aus ist. */
    public static function angabe(string $pfad): ?string
    {
        if (! Features::enabled(Features::AUFRUFE)) {
            return null;
        }
        [$dreissig, $gesamt] = self::fuerPfad($pfad);

        return 'Aufrufe: 30 Tage '.number_format($dreissig, 0, ',', '.').', gesamt '.number_format($gesamt, 0, ',', '.');
    }
}
