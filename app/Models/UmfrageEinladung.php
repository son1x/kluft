<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Einladung einer angemeldeten Person zu einer Umfrage (persönlicher Link,
 * nur der Hash ist gespeichert). Bei anonymen Umfragen hält sie nur fest,
 * dass jemand teilgenommen hat, nicht welche Antwort von wem ist.
 *
 * @property int $id
 * @property int $event_registration_id
 * @property Carbon $eingeladen_am
 * @property ?Carbon $teilgenommen_am
 */
class UmfrageEinladung extends Model
{
    public $timestamps = false;

    protected $table = 'umfrage_einladungen';

    protected $fillable = ['umfrage_id', 'event_registration_id', 'token_hash', 'eingeladen_am', 'teilgenommen_am'];

    protected function casts(): array
    {
        return ['eingeladen_am' => 'datetime', 'teilgenommen_am' => 'date'];
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
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
