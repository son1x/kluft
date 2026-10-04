<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Die Websites, die dieses CMS ausliefert: die Hauptseite und jede Sektion
 * mit eigener Domain (bolivienpartnerschaft.de, beraten-online.de).
 * Im Backend lassen sich Seiten und Beiträge danach filtern.
 */
class Websites
{
    public const MAIN = 'hauptseite';

    /**
     * Schlüssel => Domain, z. B. ['hauptseite' => 'dpsg-trier.de', 'bolivien' => 'bolivienpartnerschaft.de'].
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        $sites = [self::MAIN => self::mainDomain()];
        foreach (config('dpsg.sections') as $key => $section) {
            if (! empty($section['domains'])) {
                $sites[$key] = $section['domains'][0];
            }
        }

        return $sites;
    }

    /**
     * Domain, unter der ein Inhalt mit dieser Sektion erscheint.
     */
    public static function domainFor(?string $section): string
    {
        return self::all()[$section] ?? self::mainDomain();
    }

    /**
     * Domain der Hauptseite dieser Instanz (config dpsg.site.domain).
     */
    public static function mainDomain(): string
    {
        return (string) config('dpsg.site.domain');
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function scope(Builder $query, string $site): Builder
    {
        if ($site !== self::MAIN) {
            return $query->where('section', $site);
        }

        $own = array_keys(array_diff_key(self::all(), [self::MAIN => true]));

        return $query->where(fn (Builder $q) => $q->whereNull('section')->orWhereNotIn('section', $own));
    }
}
