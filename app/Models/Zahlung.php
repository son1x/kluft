<?php

namespace App\Models;

use App\Events\ZahlungEingegangen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Eine Forderung im Baustein „Zahlungen“ (docs/zahlungen.md).
 *
 * @property int $id
 * @property string $referenz
 * @property string $zweck
 * @property int $betrag_cent
 * @property int|null $bezahlt_cent
 * @property int $erstattet_cent
 * @property string $status
 * @property string $weg
 * @property Carbon|null $faellig_am
 * @property Carbon|null $bezahlt_am
 * @property Carbon|null $erstattet_am
 * @property Carbon|null $aufgefordert_am
 */
class Zahlung extends Model
{
    protected $table = 'zahlungen';

    public const STATUS = [
        'offen' => 'offen',
        'teilweise' => 'teilweise bezahlt',
        'bezahlt' => 'bezahlt',
        'erstattet' => 'erstattet',
        'storniert' => 'storniert',
    ];

    public const WEGE = ['ueberweisung' => 'Überweisung'];

    protected $fillable = [
        'zahlbar_type', 'zahlbar_id', 'referenz', 'zweck', 'betrag_cent', 'bezahlt_cent', 'erstattet_cent', 'status', 'weg',
        'anbieter_ref', 'faellig_am', 'bezahlt_am', 'erstattet_am', 'aufgefordert_am', 'notiz', 'erfasst_von',
    ];

    protected function casts(): array
    {
        return [
            'betrag_cent' => 'integer',
            'bezahlt_cent' => 'integer',
            'erstattet_cent' => 'integer',
            'faellig_am' => 'date',
            'bezahlt_am' => 'date',
            'erstattet_am' => 'date',
            'aufgefordert_am' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function zahlbar(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function erfasser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'erfasst_von');
    }

    /** @param  Builder<Zahlung>  $query */
    public function scopeOffen(Builder $query): void
    {
        $query->whereIn('status', ['offen', 'teilweise']);
    }

    public function offenCent(): int
    {
        return in_array($this->status, ['offen', 'teilweise'], true) ? max(0, $this->betrag_cent - (int) $this->bezahlt_cent) : 0;
    }

    /**
     * Zahlungseingang erfassen (auch Teilzahlung). Ist der Betrag erreicht,
     * meldet ZahlungEingegangen das dem Auslöser.
     */
    public function eingang(int $cent, Carbon|string $am, ?int $userId = null, ?string $notiz = null): void
    {
        $vorher = $this->status;
        $bezahlt = (int) $this->bezahlt_cent + max(0, $cent);
        $this->fill([
            'bezahlt_cent' => $bezahlt,
            'bezahlt_am' => $am,
            'status' => $bezahlt >= $this->betrag_cent ? 'bezahlt' : ($bezahlt > 0 ? 'teilweise' : 'offen'),
            'erfasst_von' => $userId,
            'notiz' => $notiz ?? $this->notiz,
        ])->save();

        if ($this->status === 'bezahlt' && $vorher !== 'bezahlt') {
            event(new ZahlungEingegangen($this));
        }
    }

    /** Erstattung erfassen (z. B. Artikel nicht lieferbar). */
    public function erstattung(int $cent, Carbon|string $am, ?int $userId = null, ?string $notiz = null): void
    {
        $erstattet = min((int) $this->bezahlt_cent, $this->erstattet_cent + max(0, $cent));
        $this->fill([
            'erstattet_cent' => $erstattet,
            'erstattet_am' => $am,
            'status' => $erstattet > 0 && $erstattet >= (int) $this->bezahlt_cent ? 'erstattet' : $this->status,
            'erfasst_von' => $userId,
            'notiz' => $notiz ?? $this->notiz,
        ])->save();
    }

    public function stornieren(?int $userId = null, ?string $notiz = null): void
    {
        $this->fill(['status' => 'storniert', 'erfasst_von' => $userId, 'notiz' => $notiz ?? $this->notiz])->save();
    }
}
