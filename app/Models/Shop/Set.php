<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Artikelset (SB-38), z. B. „Starterset“: mehrere freigegebene Artikel,
 * einzelne optional. Bestellt wird es als normale Positionen mit Verweis.
 */
class Set extends Model
{
    protected $table = 'shop_sets';

    protected $fillable = ['name', 'beschreibung', 'bild_artikel_id', 'freigegeben', 'sort'];

    protected function casts(): array
    {
        return ['freigegeben' => 'boolean', 'sort' => 'integer'];
    }

    /** @return HasMany<SetTeil, $this> */
    public function teile(): HasMany
    {
        return $this->hasMany(SetTeil::class, 'set_id')->orderBy('sort')->orderBy('id');
    }

    /** Artikel, dessen Bild das Set im Angebot zeigt. @return BelongsTo<Artikel, $this> */
    public function bildArtikel(): BelongsTo
    {
        return $this->belongsTo(Artikel::class, 'bild_artikel_id');
    }

    /** @param Builder<Set> $query */
    public function scopeFreigegeben(Builder $query): void
    {
        $query->where('freigegeben', true);
    }

    /** Bestellbar, wenn es Teile hat und alle Pflichtteile freigegeben sind und Größen haben. */
    public function bestellbar(): bool
    {
        return $this->teile->isNotEmpty() && $this->teile->every(fn (SetTeil $t) => $t->optional
            || ($t->artikel?->freigegeben && $t->artikel->varianten->where('aktiv', true)->isNotEmpty()));
    }
}
