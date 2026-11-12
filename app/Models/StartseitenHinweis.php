<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Hinweis auf der Startseite (Verwaltung → Inhalte → Startseiten-Hinweise).
 *
 * @property string $titel
 * @property string $art
 * @property string|null $text
 * @property string|null $link_url
 * @property string|null $link_text
 * @property string $farbe
 * @property bool $aktiv
 * @property Carbon|null $ab
 * @property Carbon|null $bis
 */
class StartseitenHinweis extends Model
{
    protected $table = 'startseiten_hinweise';

    /** Darstellung: auffälliger Kasten oder dezente Zeile. */
    public const ARTEN = ['kasten' => 'Kasten (auffällig)', 'zeile' => 'Zeile (dezent)'];

    /** Akzentfarben (DPSG-Kernfarben). */
    public const FARBEN = ['rot' => 'DPSG-Rot (wichtig)', 'blau' => 'DPSG-Blau', 'beige' => 'Beige (zurückhaltend)'];

    protected $fillable = ['titel', 'art', 'text', 'link_url', 'link_text', 'farbe', 'aktiv', 'ab', 'bis', 'sort'];

    protected function casts(): array
    {
        return ['aktiv' => 'boolean', 'ab' => 'date', 'bis' => 'date', 'sort' => 'integer'];
    }

    /**
     * Heute sichtbar: eingeschaltet und im Zeitraum (ohne Datum: immer).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSichtbar(Builder $query): Builder
    {
        $heute = now()->toDateString();

        return $query->where('aktiv', true)
            ->where(fn (Builder $q) => $q->whereNull('ab')->orWhereDate('ab', '<=', $heute))
            ->where(fn (Builder $q) => $q->whereNull('bis')->orWhereDate('bis', '>=', $heute))
            ->orderBy('sort')->orderBy('id');
    }

    /** Link führt auf eine andere Website (neuer Tab, Pfeil-Symbol). */
    public function istExtern(): bool
    {
        return filled($this->link_url) && ! str_starts_with((string) $this->link_url, '/') && parse_url((string) $this->link_url, PHP_URL_HOST) !== request()->getHost();
    }
}
