<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eine ausgefüllte Umfrage. Anonym ohne Uhrzeit, Name und Mail; die
 * Antworten liegen verschlüsselt in „daten“ ({schluessel: {wert, kommentar, sonstiges}}).
 *
 * @property int $id
 * @property Carbon $eingegangen_am
 * @property ?string $name
 * @property ?string $email
 * @property array<string, array{wert?: mixed, kommentar?: ?string, sonstiges?: ?string}> $daten
 */
class UmfrageAntwort extends Model
{
    public $timestamps = false;

    protected $table = 'umfrage_antworten';

    protected $fillable = ['umfrage_id', 'eingegangen_am', 'event_registration_id', 'name', 'email', 'daten'];

    protected function casts(): array
    {
        return ['eingegangen_am' => 'date', 'daten' => 'encrypted:array'];
    }

    /**
     * @return BelongsTo<Umfrage, $this>
     */
    public function umfrage(): BelongsTo
    {
        return $this->belongsTo(Umfrage::class);
    }

    /**
     * @return BelongsTo<EventRegistration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id');
    }
}
