<?php

namespace App\Models;

use App\Support\Auslagen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Eine Auslage oder Fahrt zum Erstatten (docs/auslagen.md).
 *
 * @property Carbon $datum
 * @property Carbon|null $ausgezahlt_am
 * @property array{inhaber: string, iban: string}|null $konto
 * @property list<string>|null $belege
 */
class Auslage extends Model
{
    protected $table = 'auslagen';

    public const ARTEN = ['betrag' => 'Auslage', 'fahrt' => 'Fahrtkosten'];

    public const STATUS = [
        'eingereicht' => 'Eingereicht',
        'freigegeben' => 'Freigegeben',
        'abgelehnt' => 'Abgelehnt',
        'ausgezahlt' => 'Ausgezahlt',
    ];

    public const FARBEN = ['eingereicht' => 'info', 'freigegeben' => 'warning', 'abgelehnt' => 'danger', 'ausgezahlt' => 'success'];

    public const DISK = 'local';

    protected $fillable = [
        'user_id', 'name', 'email', 'anlass', 'datum', 'art', 'betrag_cent', 'km', 'km_satz_cent', 'strecke',
        'summe_cent', 'belege', 'konto', 'status', 'grund', 'geprueft_von', 'geprueft_am', 'ausgezahlt_am',
    ];

    protected $hidden = ['konto'];

    protected function casts(): array
    {
        return [
            'datum' => 'date', 'ausgezahlt_am' => 'date', 'geprueft_am' => 'datetime',
            'betrag_cent' => 'integer', 'km' => 'float', 'km_satz_cent' => 'integer', 'summe_cent' => 'integer',
            'belege' => 'array', 'konto' => 'encrypted:array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Auslage $a) {
            $a->summe_cent = $a->art === 'fahrt'
                ? (int) round(((float) $a->km) * ($a->km_satz_cent ?? Auslagen::kmSatzCent()))
                : (int) $a->betrag_cent;
            // Bankverbindung nur so lange wie nötig.
            if (in_array($a->status, ['ausgezahlt', 'abgelehnt'], true)) {
                $a->konto = null;
            }
        });
        static::deleted(function (Auslage $a) {
            Storage::disk(self::DISK)->delete($a->belege ?? []);
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function prueferin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'geprueft_von');
    }

    public function bearbeitbar(): bool
    {
        return $this->status === 'eingereicht';
    }

    /** Kurzbeschreibung für Mails und Verwendungszweck. */
    public function beschreibung(): string
    {
        return $this->art === 'fahrt'
            ? 'Fahrt '.($this->strecke ? $this->strecke.', ' : '').str_replace('.', ',', (string) $this->km).' km'
            : (self::ARTEN[$this->art] ?? $this->art);
    }
}
