<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Reservierung von Material: angefragt → zugesagt → ausgegeben → zurück
 * (oder abgelehnt).
 *
 * @property Carbon $von
 * @property Carbon $bis
 */
class MaterialReservierung extends Model
{
    protected $table = 'material_reservierungen';

    public const STATUS = [
        'angefragt' => 'Angefragt',
        'zugesagt' => 'Zugesagt',
        'ausgegeben' => 'Ausgegeben',
        'zurueck' => 'Zurück',
        'abgelehnt' => 'Abgelehnt',
    ];

    public const FARBEN = ['angefragt' => 'info', 'zugesagt' => 'warning', 'ausgegeben' => 'primary', 'zurueck' => 'success', 'abgelehnt' => 'gray'];

    /** Diese Status belegen das Material. */
    public const FEST = ['zugesagt', 'ausgegeben'];

    protected $fillable = ['material_id', 'menge', 'von', 'bis', 'name', 'email', 'telefon', 'gruppierung', 'zweck', 'status', 'maengel', 'notiz', 'erstellt_von', 'erinnert_am'];

    protected function casts(): array
    {
        return ['von' => 'date', 'bis' => 'date', 'menge' => 'integer', 'erinnert_am' => 'date'];
    }

    protected static function booted(): void
    {
        static::saving(function (MaterialReservierung $r) {
            $r->email = filled($r->email) ? mb_strtolower(trim((string) $r->email)) : null;
        });
    }

    /**
     * @return BelongsTo<Material, $this>
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    /**
     * @param  Builder<MaterialReservierung>  $query
     */
    public function scopeFest(Builder $query): void
    {
        $query->whereIn('status', self::FEST);
    }

    /**
     * @param  Builder<MaterialReservierung>  $query
     */
    public function scopeUeberschneidet(Builder $query, Carbon|string $von, Carbon|string $bis): void
    {
        $query->whereDate('von', '<=', Carbon::parse($bis))->whereDate('bis', '>=', Carbon::parse($von));
    }

    public function zeitraum(): string
    {
        return $this->von->equalTo($this->bis) ? $this->von->format('d.m.Y') : $this->von->format('d.m.').'–'.$this->bis->format('d.m.Y');
    }

    /** Fehlermeldung, wenn die Zusage zu viel vergeben würde. */
    public function konflikt(): ?string
    {
        $material = $this->material;
        $frei = $material->frei($this->von, $this->bis, $this->exists ? $this->id : null);

        return $this->menge > $frei
            ? "Im Zeitraum sind nur noch {$frei} von {$material->menge} frei."
            : null;
    }
}
