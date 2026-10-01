<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Bezirk oder Stamm (Pflichtenheft 6.11).
 */
class Gruppierung extends Model
{
    protected $table = 'gruppierungen';

    public const CACHE_TAG_VERSION = 'gruppierungen:version';

    protected $fillable = [
        'typ',
        'nummer',
        'parent_id',
        'name',
        'slug',
        'strasse',
        'plz',
        'ort',
        'lat',
        'lng',
        'position_gesetzt_von',
        'kontakt_email',
        'kontakt_telefon',
        'ansprechperson',
        'ansprechperson_einwilligung',
        'website_url',
        'social_url',
        'beschreibung',
        'logo_media_id',
        'active',
        'sort',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'active' => 'boolean',
            'ansprechperson_einwilligung' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }

    /**
     * Macht den GeoJSON-Cache ungültig (Versionszähler statt Cache-Tags,
     * weil der File-Cache keine Tags kann).
     */
    public static function flushCache(): void
    {
        Cache::forever(self::CACHE_TAG_VERSION, static::cacheVersion() + 1);
    }

    public static function cacheVersion(): int
    {
        return (int) Cache::get(self::CACHE_TAG_VERSION, 0);
    }

    /**
     * @return BelongsTo<Gruppierung, $this>
     */
    public function bezirk(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Gruppierung, $this>
     */
    public function staemme(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function logo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_media_id');
    }

    /**
     * Angebotene Stufen in pädagogischer Reihenfolge.
     *
     * @return list<string>
     */
    public function stufen(): array
    {
        $offered = $this->preloadedStufen
            ?? DB::table('gruppierung_stufen')->where('gruppierung_id', $this->id)->pluck('stufe')->all();

        return array_values(array_filter(array_keys(config('dpsg.stages')), fn (string $stage) => in_array($stage, $offered, true)));
    }

    /** @var list<string>|null */
    protected ?array $preloadedStufen = null;

    /**
     * Lädt die Stufen vieler Gruppierungen mit einer einzigen Abfrage.
     *
     * @param  iterable<Gruppierung>  $gruppierungen
     */
    public static function preloadStufen(iterable $gruppierungen): void
    {
        $models = collect($gruppierungen);
        $rows = DB::table('gruppierung_stufen')->whereIn('gruppierung_id', $models->pluck('id'))->get()->groupBy('gruppierung_id');

        $models->each(fn (Gruppierung $g) => $g->preloadedStufen = $rows->get($g->id, collect())->pluck('stufe')->all());
    }

    /**
     * @param  list<string>  $stufen
     */
    public function syncStufen(array $stufen): void
    {
        $valid = array_values(array_intersect(array_keys(config('dpsg.stages')), $stufen));

        DB::transaction(function () use ($valid) {
            DB::table('gruppierung_stufen')->where('gruppierung_id', $this->id)->delete();
            DB::table('gruppierung_stufen')->insert(array_map(
                fn (string $stufe) => ['gruppierung_id' => $this->id, 'stufe' => $stufe],
                $valid
            ));
        });

        $this->preloadedStufen = null;
        static::flushCache();
    }

    /**
     * Bezirke werden ohne Präfix gepflegt ("Saar-Hochwald") und als
     * "Bezirk Saar-Hochwald" angezeigt.
     */
    public function displayName(): string
    {
        return $this->typ === 'bezirk' && ! str_starts_with($this->name, 'Bezirk ') ? "Bezirk {$this->name}" : $this->name;
    }

    /**
     * Nur http(s)-Links ausgeben (kein javascript: o. Ä. aus Freitext).
     */
    public function safeUrl(string $attribute): ?string
    {
        $url = $this->{$attribute};

        return is_string($url) && preg_match('#^https?://#i', $url) ? $url : null;
    }

    /**
     * Importierte Ortsmitte statt eines von der Verwaltung gesetzten
     * genauen Standorts.
     */
    public function hasApproximatePosition(): bool
    {
        return $this->hasPosition() && $this->position_gesetzt_von === null;
    }

    public function hasPosition(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }

    /**
     * Name der Ansprechperson nur, wenn die Einwilligung vorliegt.
     */
    public function publicAnsprechperson(): ?string
    {
        return $this->ansprechperson_einwilligung ? $this->ansprechperson : null;
    }

    /**
     * Nach DPSG-Nummer (Bezirk, dann Stamm); Einträge ohne Nummer zuletzt.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByRaw('nummer is null')->orderBy('nummer')->orderBy('sort')->orderBy('name');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Öffentlicher Filter (Bezirk-Slug und/oder Stufe), gemeinsam genutzt
     * von Listenansicht und GeoJSON-Schnittstelle, damit beide synchron
     * sind (F-MAP-03).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeFiltered(Builder $query, ?string $bezirk, ?string $stufe): Builder
    {
        if (filled($bezirk)) {
            $query->where(fn (Builder $q) => $q
                ->where(fn (Builder $b) => $b->where('typ', 'bezirk')->where('slug', $bezirk))
                ->orWhereHas('bezirk', fn (Builder $b) => $b->where('slug', $bezirk)));
        }

        if (filled($stufe)) {
            $query->whereExists(fn ($sub) => $sub->from('gruppierung_stufen')
                ->whereColumn('gruppierung_stufen.gruppierung_id', 'gruppierungen.id')
                ->where('stufe', $stufe));
        }

        return $query;
    }
}
