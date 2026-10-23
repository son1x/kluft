<?php

namespace App\Models\Shop;

use App\Models\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rüsthaus-Artikel im eigenen Shop der Sammelbestellung, mit lokal
 * zwischengespeichertem Namen, Beschreibung, Bild und Größen (Varianten).
 */
class Artikel extends Model
{
    protected $table = 'shop_artikel';

    protected $fillable = ['nummer', 'name', 'beschreibung', 'url', 'rh_kategorie', 'kategorie', 'bild_media_id', 'preis_cent', 'freigegeben', 'sort', 'abgerufen_am'];

    protected function casts(): array
    {
        return ['preis_cent' => 'integer', 'freigegeben' => 'boolean', 'sort' => 'integer', 'abgerufen_am' => 'datetime'];
    }

    /** @return HasMany<Variante, $this> */
    public function varianten(): HasMany
    {
        return $this->hasMany(Variante::class, 'artikel_id')->orderBy('sort')->orderBy('id');
    }

    /** @return BelongsTo<Media, $this> */
    public function bild(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'bild_media_id');
    }

    /** @param Builder<Artikel> $query */
    public function scopeFreigegeben(Builder $query): void
    {
        $query->where('freigegeben', true);
    }
}
