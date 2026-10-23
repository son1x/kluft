<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bestellrunde: von Hand geöffnet und geschlossen; das Bestelldatum ist
 * zugleich die Zahlungsfrist.
 */
class Runde extends Model
{
    protected $table = 'shop_runden';

    public const STATUS = ['offen' => 'offen', 'geschlossen' => 'geschlossen', 'bestellt' => 'beim Rüsthaus bestellt', 'geliefert' => 'geliefert', 'erledigt' => 'erledigt'];

    protected $fillable = ['name', 'bestelldatum', 'status', 'geschlossen_am'];

    protected function casts(): array
    {
        return ['bestelldatum' => 'date', 'geschlossen_am' => 'datetime'];
    }

    /** @return HasMany<Bestellung, $this> */
    public function bestellungen(): HasMany
    {
        return $this->hasMany(Bestellung::class, 'runde_id');
    }

    public static function offene(): ?self
    {
        return self::query()->where('status', 'offen')->latest('id')->first();
    }

    public function istOffen(): bool
    {
        return $this->status === 'offen';
    }
}
