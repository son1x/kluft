<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Nachricht aus dem Kontaktformular oder Frage zu einer Veranstaltung im
 * Nachrichten-Eingang (docs/nachrichten.md). Zugestellt wird weiterhin per
 * Mail; hier sieht die Verwaltung alle Nachrichten zentral.
 *
 * @property int $id
 * @property string $art
 * @property string $thema
 * @property string $name
 * @property string $email
 * @property ?string $betreff
 * @property string $nachricht
 * @property list<string> $empfaenger
 * @property bool $zugestellt
 * @property string $status
 * @property ?Carbon $bearbeitet_am
 * @property ?string $notiz
 * @property Carbon $created_at
 */
class ContactMessage extends Model
{
    public const STATUSES = ['neu' => 'neu', 'in_bearbeitung' => 'in Bearbeitung', 'erledigt' => 'erledigt'];

    public const ARTEN = ['kontakt' => 'Kontaktformular', 'veranstaltung' => 'Frage zur Veranstaltung', 'direkt' => 'Nachricht an eine Adresse'];

    protected $fillable = ['art', 'contact_topic_id', 'event_id', 'thema', 'name', 'email', 'betreff', 'nachricht', 'empfaenger', 'zugestellt',
        'status', 'bearbeitet_von', 'bearbeitet_am', 'notiz'];

    protected function casts(): array
    {
        return ['nachricht' => 'encrypted', 'empfaenger' => 'array', 'zugestellt' => 'boolean', 'bearbeitet_am' => 'datetime'];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function bearbeiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bearbeitet_von');
    }

    /** Wie lange Nachrichten aufbewahrt werden. */
    public static function aufbewahrungMonate(): int
    {
        return max(1, (int) config('dpsg.nachrichten.aufbewahrung_monate', 6));
    }
}
