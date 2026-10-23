<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Größe bzw. Ausführung eines Artikels mit eigener Rüsthaus-Artikelnummer. */
class Variante extends Model
{
    protected $table = 'shop_varianten';

    protected $fillable = ['artikel_id', 'nummer', 'bezeichnung', 'preis_cent', 'aktiv', 'sort'];

    protected function casts(): array
    {
        return ['preis_cent' => 'integer', 'aktiv' => 'boolean', 'sort' => 'integer'];
    }

    /** @return BelongsTo<Artikel, $this> */
    public function artikel(): BelongsTo
    {
        return $this->belongsTo(Artikel::class, 'artikel_id');
    }
}
