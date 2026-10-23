<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Umfrage oder Vorlage (docs/umfragen.md).
 *
 * @property int $id
 * @property string $titel
 * @property string $code
 * @property ?string $einleitung
 * @property ?string $danke
 * @property string $status
 * @property ?Carbon $beginnt_am
 * @property ?Carbon $endet_am
 * @property bool $anonym
 * @property bool $mehrfach
 * @property ?string $zugangscode
 * @property bool $ist_vorlage
 * @property ?int $event_id
 * @property bool $gewinnspiel
 * @property ?string $gewinnspiel_text
 * @property int $aufbewahrung_monate
 */
class Umfrage extends Model
{
    public const STATUS = ['entwurf' => 'Entwurf', 'offen' => 'Offen', 'geschlossen' => 'Geschlossen', 'archiviert' => 'Archiviert'];

    protected $table = 'umfragen';

    protected $fillable = [
        'titel', 'code', 'einleitung', 'danke', 'status', 'beginnt_am', 'endet_am', 'anonym', 'mehrfach', 'zugangscode',
        'ist_vorlage', 'event_id', 'gewinnspiel', 'gewinnspiel_text', 'aufbewahrung_monate', 'erstellt_von',
    ];

    protected function casts(): array
    {
        return [
            'beginnt_am' => 'datetime', 'endet_am' => 'datetime', 'anonym' => 'boolean', 'mehrfach' => 'boolean',
            'ist_vorlage' => 'boolean', 'gewinnspiel' => 'boolean', 'aufbewahrung_monate' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $umfrage) {
            $umfrage->code ??= Str::lower(Str::random(20));
        });
    }

    /**
     * @return HasMany<UmfrageFrage, $this>
     */
    public function fragen(): HasMany
    {
        return $this->hasMany(UmfrageFrage::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * @return HasMany<UmfrageAntwort, $this>
     */
    public function antworten(): HasMany
    {
        return $this->hasMany(UmfrageAntwort::class);
    }

    /**
     * @return HasMany<UmfrageEinladung, $this>
     */
    public function einladungen(): HasMany
    {
        return $this->hasMany(UmfrageEinladung::class);
    }

    /**
     * @return HasMany<UmfrageGewinnspiel, $this>
     */
    public function gewinnspielEintraege(): HasMany
    {
        return $this->hasMany(UmfrageGewinnspiel::class);
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeUmfragen(Builder $query): Builder
    {
        return $query->where('ist_vorlage', false);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVorlagen(Builder $query): Builder
    {
        return $query->where('ist_vorlage', true);
    }

    /** Nimmt gerade Antworten an (Status offen und im Zeitraum). */
    public function istOffen(): bool
    {
        return ! $this->ist_vorlage && $this->status === 'offen'
            && ($this->beginnt_am === null || $this->beginnt_am->isPast())
            && ($this->endet_am === null || $this->endet_am->isFuture());
    }

    /** Nur über den persönlichen Link aus der Einladung (Nachfrage an Teilnehmende). */
    public function nurEingeladene(): bool
    {
        return $this->event_id !== null;
    }

    public function url(): string
    {
        return route('umfragen.zeigen', $this->code);
    }

    /**
     * Kopie mit allen Fragen, ohne Antworten. Schlüssel werden neu vergeben,
     * Bedingungen zeigen auf die kopierten Fragen.
     *
     * @param  array<string, mixed>  $werte
     */
    public function kopieren(array $werte = []): self
    {
        return DB::transaction(function () use ($werte) {
            $kopie = $this->replicate(['code', 'created_at', 'updated_at']);
            $kopie->fill(['status' => 'entwurf', 'code' => null, ...$werte]);
            $kopie->code = Str::lower(Str::random(20));
            $kopie->save();

            $neu = $this->fragen->mapWithKeys(fn (UmfrageFrage $f) => [$f->schluessel => (string) Str::uuid()]);
            foreach ($this->fragen as $frage) {
                $bedingung = $frage->bedingung;
                if (is_array($bedingung) && isset($neu[$bedingung['frage'] ?? ''])) {
                    $bedingung['frage'] = $neu[$bedingung['frage']];
                } else {
                    $bedingung = null;
                }
                $kopie->fragen()->create([...$frage->only(['sort', 'typ', 'text', 'hilfe', 'pflicht', 'kommentar', 'optionen', 'einstellungen']),
                    'schluessel' => $neu[$frage->schluessel], 'bedingung' => $bedingung]);
            }

            return $kopie;
        });
    }
}
