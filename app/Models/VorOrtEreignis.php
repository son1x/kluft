<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Ereignis aus „Vor Ort“ (docs/vor-ort.md), z. B. „ist da“ oder
 * „bar bezahlt“. Die Kennung (uuid) vergibt das Gerät; doppelt
 * geschickte Ereignisse werden nur einmal verarbeitet.
 */
class VorOrtEreignis extends Model
{
    protected $table = 'vor_ort_ereignisse';

    public const TYPEN = ['anwesend', 'zahlung', 'angaben', 'zusatz', 'unterschrift', 'nachmeldung'];

    protected $fillable = ['uuid', 'event_id', 'event_registration_id', 'user_id', 'typ', 'daten', 'ergebnis', 'erfasst_am'];

    protected function casts(): array
    {
        return [
            'daten' => 'encrypted:array',
            'erfasst_am' => 'datetime',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<EventRegistration, $this> */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id');
    }
}
