<?php

namespace App\Models\Shop;

use App\Models\Zahlung;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine bestellte Sache für ein Mitglied. Freie Artikel (per Nummer) gelten
 * erst, wenn die Sammelbestellerin sie bestätigt; bezahlt ist eine Position,
 * wenn ihre Zahlung bezahlt ist.
 */
class Position extends Model
{
    protected $table = 'shop_positionen';

    protected $fillable = ['bestellung_id', 'artikel_id', 'nummer', 'bezeichnung', 'groesse', 'menge', 'preis_cent', 'mitglied', 'stufe', 'frei', 'bestaetigt', 'zahlung_id'];

    protected function casts(): array
    {
        return ['menge' => 'integer', 'preis_cent' => 'integer', 'frei' => 'boolean', 'bestaetigt' => 'boolean'];
    }

    /** @return BelongsTo<Bestellung, $this> */
    public function bestellung(): BelongsTo
    {
        return $this->belongsTo(Bestellung::class, 'bestellung_id');
    }

    /** @return BelongsTo<Zahlung, $this> */
    public function zahlung(): BelongsTo
    {
        return $this->belongsTo(Zahlung::class, 'zahlung_id');
    }

    public function summeCent(): int
    {
        return (int) $this->preis_cent * $this->menge;
    }

    public function bezahlt(): bool
    {
        return $this->zahlung?->status === 'bezahlt';
    }
}
