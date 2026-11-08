<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    protected $fillable = [
        'parent_id',
        'slug',
        'title',
        'body',
        'cover_media_id',
        'status',
        'published_at',
        'seo_title',
        'seo_description',
        'stage',
        'section',
        'nav_sort',
        'show_in_nav',
        'author_id',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'show_in_nav' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Page, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Unterpunkte im Hauptmenü: veröffentlichte Unterseiten mit
     * „In Hauptnavigation anzeigen“.
     *
     * @return HasMany<Page, $this>
     */
    public function navChildren(): HasMany
    {
        return $this->children()->published()->where('show_in_nav', true)
            ->orderBy('nav_sort')->orderBy('title');
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function coverMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->published_at?->isPast();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->where('published_at', '<=', now());
    }

    /**
     * Vorstandsseiten (Pflichtenheft 5.4): die Seite des Gremiums „leitung“
     * (config dpsg.gremien.leitung.page, z. B. „dioezesanleitung“ oder
     * „stammesvorstand“) und ihre Unterseiten. Die pflegt der Vorstand.
     *
     * @return list<int>
     */
    public static function vorstandsseitenIds(): array
    {
        $slug = config('dpsg.gremien.leitung.page');
        $ids = $slug ? self::query()->where('slug', $slug)->pluck('id')->all() : [];
        $ebene = $ids;
        // Unterseiten, höchstens fünf Ebenen tief.
        for ($i = 0; $i < 5 && $ebene !== []; $i++) {
            $ebene = array_values(array_diff(self::query()->whereIn('parent_id', $ebene)->pluck('id')->all(), $ids));
            $ids = [...$ids, ...$ebene];
        }

        return array_map('intval', $ids);
    }

    public function istVorstandsseite(): bool
    {
        return $this->exists && in_array((int) $this->id, self::vorstandsseitenIds(), true);
    }
}
