<?php

namespace App\Models;

use App\Mail\ContactMessageMail;
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
 * @property ?list<array{art: string, an: list<string>, ergebnis: string, am: string, von: ?string}> $versand
 * @property string $status
 * @property ?Carbon $bearbeitet_am
 * @property ?string $notiz
 * @property Carbon $created_at
 */
class ContactMessage extends Model
{
    // „spam“: zurückgehalten wegen Spamverdacht, nicht gemailt (App\Support\Spamverdacht).
    public const STATUSES = ['neu' => 'neu', 'in_bearbeitung' => 'in Bearbeitung', 'erledigt' => 'erledigt', 'spam' => 'Spamverdacht'];

    public const ARTEN = ['kontakt' => 'Kontaktformular', 'veranstaltung' => 'Frage zur Veranstaltung', 'direkt' => 'Nachricht an eine Adresse', 'gremium' => 'Nachricht an ein Gremium'];

    /** Ergebnis eines Versands: verschickt, nur ins Server-Protokoll, fehlgeschlagen. */
    public const ERGEBNISSE = ['mail' => 'verschickt', 'log' => 'nur ins Server-Protokoll geschrieben, nicht verschickt', 'fehler' => 'fehlgeschlagen', 'zurueckgehalten' => 'zurückgehalten (Spamverdacht), nicht verschickt'];

    public const VERSAND_ARTEN = ['eingang' => 'Eingang', 'erneut' => 'Erneut gesendet', 'weitergeleitet' => 'Weitergeleitet', 'kein_spam' => 'Kein Spam – zugestellt'];

    protected $fillable = ['art', 'contact_topic_id', 'event_id', 'thema', 'name', 'email', 'betreff', 'nachricht', 'empfaenger', 'zugestellt',
        'versand', 'status', 'bearbeitet_von', 'bearbeitet_am', 'notiz'];

    protected function casts(): array
    {
        return ['nachricht' => 'encrypted', 'empfaenger' => 'array', 'zugestellt' => 'boolean', 'versand' => 'array', 'bearbeitet_am' => 'datetime'];
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

    /**
     * Mail wie beim Absenden (Antwort geht an die Absender*in), z. B. zum
     * erneuten Senden oder Weiterleiten; optional mit Hinweis obenan.
     */
    public function mail(?string $hinweis = null): ContactMessageMail
    {
        $kontext = array_filter([
            $hinweis,
            $this->event ? $this->event->title.' ('.$this->event->dateLabel().') – '.route('events.show', $this->event->slug) : null,
        ]);

        return new ContactMessageMail(
            topic: $this->art === 'veranstaltung' ? 'Frage zur Veranstaltung' : $this->thema,
            senderName: $this->name,
            senderEmail: $this->email,
            subjectLine: $this->betreff,
            body: $this->nachricht,
            context: $kontext ? implode("\n", $kontext) : null,
        );
    }

    /**
     * Versand im Verlauf festhalten.
     *
     * @param  list<string>  $an
     */
    public function versandEintragen(string $art, array $an, string $ergebnis, ?string $von = null): void
    {
        $this->versand = [...$this->versand ?? [], [
            'art' => $art, 'an' => $an, 'ergebnis' => $ergebnis, 'am' => now()->toIso8601String(), 'von' => $von,
        ]];
    }

    /** Ergebnis des letzten Versands („mail“, „log“, „fehler“) oder null bei älteren Nachrichten. */
    public function letztesErgebnis(): ?string
    {
        $versand = $this->versand ?? [];

        return $versand === [] ? null : (string) end($versand)['ergebnis'];
    }

    /** Wie lange Nachrichten aufbewahrt werden. */
    public static function aufbewahrungMonate(): int
    {
        return max(1, (int) config('dpsg.nachrichten.aufbewahrung_monate', 6));
    }
}
