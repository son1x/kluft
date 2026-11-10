<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Ein Präventions-Nachweis einer leitenden Person: erweitertes
 * Führungszeugnis (eingesehen am), Präventionsschulung oder Juleica, je mit
 * „gültig bis“. Nur Daten, keine Dokumente.
 *
 * @property Carbon|null $datum
 * @property Carbon $gueltig_bis
 */
class PraeventionNachweis extends Model
{
    protected $table = 'praevention_nachweise';

    public const ARTEN = [
        'efz' => 'Führungszeugnis',
        'schulung' => 'Präventionsschulung',
        'juleica' => 'Juleica',
    ];

    /** Bezeichnung des Datumsfelds je Art. */
    public const DATUM_LABEL = [
        'efz' => 'Eingesehen am',
        'schulung' => 'Teilgenommen am',
        'juleica' => 'Ausgestellt am',
    ];

    /** Ab so vielen Tagen vor Ablauf gilt ein Nachweis als „läuft bald ab“. */
    public const BALD_TAGE = 90;

    protected $fillable = ['person_id', 'name', 'email', 'art', 'bezeichnung', 'datum', 'gueltig_bis', 'erinnert_stufe', 'erfasst_von'];

    protected function casts(): array
    {
        return ['datum' => 'date', 'gueltig_bis' => 'date', 'erinnert_stufe' => 'integer'];
    }

    protected static function booted(): void
    {
        // Neues Ablaufdatum: Erinnerungen wieder von vorn.
        static::saving(function (PraeventionNachweis $n) {
            if ($n->isDirty('gueltig_bis')) {
                $n->erinnert_stufe = null;
            }
            $n->email = filled($n->email) ? mb_strtolower(trim((string) $n->email)) : null;
        });
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /** „abgelaufen“, „bald“ (≤ 90 Tage) oder „gueltig“. */
    public function ampel(): string
    {
        $tage = $this->restTage();

        return $tage < 0 ? 'abgelaufen' : ($tage <= self::BALD_TAGE ? 'bald' : 'gueltig');
    }

    public function restTage(): int
    {
        return (int) today()->diffInDays($this->gueltig_bis, false);
    }

    public function artLabel(): string
    {
        return self::ARTEN[$this->art] ?? $this->art;
    }
}
