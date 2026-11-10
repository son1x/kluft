<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Material zum Ausleihen (docs/materialverleih.md).
 */
class Material extends Model
{
    protected $table = 'materialien';

    protected $fillable = ['name', 'kategorie', 'beschreibung', 'bild', 'menge', 'ort', 'hinweise', 'aktiv'];

    protected function casts(): array
    {
        return ['menge' => 'integer', 'aktiv' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::deleted(function (Material $m) {
            if ($m->bild) {
                Storage::disk('public')->delete($m->bild);
            }
        });
    }

    /**
     * @return HasMany<MaterialReservierung, $this>
     */
    public function reservierungen(): HasMany
    {
        return $this->hasMany(MaterialReservierung::class);
    }

    public function bildUrl(): ?string
    {
        return $this->bild ? Storage::disk('public')->url($this->bild) : null;
    }

    /**
     * Wie viele Stück im Zeitraum fest vergeben sind (zugesagt oder
     * ausgegeben), am vollsten Tag; ohne die genannte Reservierung.
     */
    public function belegt(Carbon|string $von, Carbon|string $bis, ?int $ohne = null): int
    {
        $von = Carbon::parse($von)->startOfDay();
        $bis = Carbon::parse($bis)->startOfDay();
        $reservierungen = $this->reservierungen()->fest()->ueberschneidet($von, $bis)
            ->when($ohne, fn (Builder $q) => $q->whereKeyNot($ohne))->get();

        $max = 0;
        for ($tag = $von->copy(); $tag->lte($bis); $tag->addDay()) {
            $max = max($max, (int) $reservierungen->filter(fn (MaterialReservierung $r) => $r->von->lte($tag) && $r->bis->gte($tag))->sum('menge'));
            if ($tag->diffInDays($von) > 366) {
                break;
            }
        }

        return $max;
    }

    public function frei(Carbon|string $von, Carbon|string $bis, ?int $ohne = null): int
    {
        return max(0, $this->menge - $this->belegt($von, $bis, $ohne));
    }
}
