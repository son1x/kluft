<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Digitale Unterschrift einer teilnehmenden Person für eine Veranstaltung
 * (docs/vor-ort.md, Stufe 2). Das Bild (PNG als Data-URI) liegt
 * verschlüsselt; die Prüfsumme (SHA-256 der PNG-Daten) belegt, dass es
 * nicht verändert wurde. Nach dem Festschreiben der Teilnahmeliste wird
 * die Einzelunterschrift gelöscht (loeschen_am).
 */
class VorOrtUnterschrift extends Model
{
    protected $table = 'vor_ort_unterschriften';

    protected $fillable = ['event_id', 'event_registration_id', 'bild', 'pruefsumme', 'user_id', 'unterschrieben_am', 'loeschen_am'];

    protected $hidden = ['bild'];

    protected function casts(): array
    {
        return [
            'bild' => 'encrypted',
            'unterschrieben_am' => 'datetime',
            'loeschen_am' => 'datetime',
        ];
    }

    /** @return BelongsTo<EventRegistration, $this> */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id');
    }

    /** @return BelongsTo<User, $this> */
    public function erfasser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
