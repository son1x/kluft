<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Eigenes Instagram-Konto, dessen neueste Beiträge auf der Website
 * erscheinen (docs/instagram.md). Das Token wird verschlüsselt gespeichert
 * und nie an den Browser ausgeliefert.
 *
 * @property int $id
 * @property string $name
 * @property ?string $benutzername
 * @property string $access_token
 * @property ?Carbon $token_ablauf
 * @property ?Carbon $token_erneuert
 * @property list<string>|null $orte
 * @property int $anzahl
 * @property bool $aktiv
 * @property ?Carbon $abgerufen_am
 * @property ?string $fehler
 */
class InstagramKonto extends Model
{
    protected $table = 'instagram_konten';

    protected $fillable = ['name', 'benutzername', 'ig_user_id', 'access_token', 'token_ablauf', 'token_erneuert', 'orte', 'anzahl', 'aktiv', 'abgerufen_am', 'fehler'];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'token_ablauf' => 'datetime',
            'token_erneuert' => 'datetime',
            'orte' => 'array',
            'anzahl' => 'integer',
            'aktiv' => 'boolean',
            'abgerufen_am' => 'datetime',
        ];
    }

    /**
     * @return HasMany<InstagramBeitrag, $this>
     */
    public function beitraege(): HasMany
    {
        return $this->hasMany(InstagramBeitrag::class);
    }

    /**
     * Aktive Konten für einen Ort („startseite“ oder „section:<key>“).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeFuer(Builder $query, string $ort): Builder
    {
        return $query->where('aktiv', true)->whereJsonContains('orte', $ort);
    }

    protected static function booted(): void
    {
        // Über die Models löschen, damit auch die Bilddateien verschwinden.
        static::deleting(fn (self $konto) => $konto->beitraege()->get()->each->delete());
    }

    public function profilUrl(): ?string
    {
        return $this->benutzername ? 'https://www.instagram.com/'.rawurlencode($this->benutzername).'/' : null;
    }

    /**
     * Orte zur Auswahl: Startseite und die Fachbereiche bzw. Sektionen.
     *
     * @return array<string, string>
     */
    public static function orte(): array
    {
        $orte = ['startseite' => 'Startseite'];
        foreach ((array) config('dpsg.sections', []) as $key => $section) {
            $orte['section:'.$key] = (string) ($section['label'] ?? $key);
        }

        return $orte;
    }
}
