<?php

namespace App\Models\Shop;

use App\Models\Zahlung;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Bestellung einer Person (Mitglied oder erziehungsberechtigt) in einer
 * Runde, für ein oder mehrere Mitglieder. Zugang für die Person über den
 * Link mit Token aus der Mail.
 */
class Bestellung extends Model
{
    protected $table = 'shop_bestellungen';

    public const ROLLEN = ['mitglied' => 'Ich bin selbst Mitglied', 'erziehungsberechtigt' => 'Ich bin erziehungsberechtigt'];

    protected $fillable = ['runde_id', 'token', 'name', 'email', 'telefon', 'rolle', 'abholung', 'bemerkung', 'bestaetigt_am', 'storniert_am', 'test', 'mitgliedschaft', 'mitgliedschaft_am', 'gehalten', 'erinnert_am', 'anonymisiert_am'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['bestaetigt_am' => 'datetime', 'storniert_am' => 'datetime', 'test' => 'boolean', 'mitgliedschaft_am' => 'datetime', 'gehalten' => 'boolean',
            'erinnert_am' => 'datetime', 'anonymisiert_am' => 'datetime'];
    }

    /** @return BelongsTo<Runde, $this> */
    public function runde(): BelongsTo
    {
        return $this->belongsTo(Runde::class, 'runde_id');
    }

    /** @return HasMany<Position, $this> */
    public function positionen(): HasMany
    {
        return $this->hasMany(Position::class, 'bestellung_id')->orderBy('id');
    }

    /** @return MorphMany<Zahlung, $this> */
    public function zahlungen(): MorphMany
    {
        return $this->morphMany(Zahlung::class, 'zahlbar');
    }

    /**
     * Stand für Liste und Statusseite.
     *
     * @return 'unbestaetigt'|'storniert'|'pruefung'|'zahlung'|'bezahlt'|'bestellt'|'abholbereit'|'ausgegeben'
     */
    public function stand(): string
    {
        if ($this->storniert_am) {
            return 'storniert';
        }
        if (! $this->bestaetigt_am) {
            return 'unbestaetigt';
        }
        $positionen = $this->positionen;
        if ($positionen->contains(fn (Position $p) => ! $p->bestaetigt)) {
            return 'pruefung';
        }
        if ($positionen->isNotEmpty() && $positionen->every(fn (Position $p) => $p->bezahlt())) {
            return match (true) {
                $positionen->every(fn (Position $p) => $p->ausgegeben_am !== null) => 'ausgegeben',
                in_array($this->runde?->status, ['geliefert', 'erledigt'], true) => 'abholbereit',
                $this->runde?->status === 'bestellt' => 'bestellt',
                default => 'bezahlt',
            };
        }

        return 'zahlung';
    }

    public const STAENDE = [
        'unbestaetigt' => 'noch nicht bestätigt',
        'pruefung' => 'wird geprüft',
        'zahlung' => 'Zahlung offen',
        'bezahlt' => 'bezahlt',
        'bestellt' => 'beim Rüsthaus bestellt',
        'abholbereit' => 'abholbereit',
        'ausgegeben' => 'ausgegeben',
        'storniert' => 'storniert',
    ];

    /** Ergebnis der Mitgliedsprüfung (SB-37). */
    public const MITGLIEDSCHAFT = [
        'ja' => 'Mitglied',
        'unklar' => 'unklar',
        'nein' => 'nicht gefunden',
        'bestaetigt' => 'von Hand bestätigt',
    ];

    public function summeCent(): int
    {
        return (int) $this->positionen->sum(fn (Position $p) => $p->summeCent());
    }

    /** Für die Liste „Kasse → Zahlungen“. */
    public function zahlungsLabel(): string
    {
        return ($this->test ? 'TEST: ' : '').$this->name.' – Sammelbestellung '.$this->runde?->name;
    }

    public function zahlungsHinweis(): ?string
    {
        return $this->storniert_am ? 'Bestellung storniert – Erstattung prüfen' : null;
    }
}
