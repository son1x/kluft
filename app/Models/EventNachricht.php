<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nachricht an die Teilnehmenden einer Veranstaltung (Rundmail oder
 * Erinnerung). Gespeichert wird der Text und die Zahl der Empfänger*innen,
 * nicht wer sie bekommen hat.
 *
 * @property string $art
 * @property string $betreff
 * @property string $text
 * @property array<int, string>|null $an
 * @property int $empfaenger
 */
class EventNachricht extends Model
{
    protected $table = 'event_nachrichten';

    protected $fillable = ['event_id', 'art', 'betreff', 'text', 'an', 'empfaenger', 'user_id'];

    protected function casts(): array
    {
        return ['an' => 'array', 'empfaenger' => 'integer'];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
