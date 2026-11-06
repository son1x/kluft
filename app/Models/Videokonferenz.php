<?php

namespace App\Models;

use App\Services\Jitsi\Jitsi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Videokonferenz auf der Jitsi-Installation des DV (docs/videokonferenzen.md).
 *
 * @property int $id
 * @property string $titel
 * @property Carbon $beginn
 * @property int $dauer_minuten
 * @property list<string>|null $moderation_emails
 * @property list<string>|null $teilnehmende_emails
 * @property Carbon|null $eingeladen_am
 * @property string $raum
 * @property int|null $user_id
 */
class Videokonferenz extends Model
{
    protected $table = 'videokonferenzen';

    protected $fillable = ['titel', 'beginn', 'dauer_minuten', 'raum', 'user_id', 'moderation_emails', 'teilnehmende_emails', 'eingeladen_am'];

    // Adressen nur intern (Versand), nie in Exporten oder Antworten.
    protected $hidden = ['moderation_emails', 'teilnehmende_emails'];

    protected function casts(): array
    {
        return [
            'beginn' => 'datetime',
            'eingeladen_am' => 'datetime',
            'dauer_minuten' => 'integer',
            'moderation_emails' => 'encrypted:array',
            'teilnehmende_emails' => 'encrypted:array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Videokonferenz $k) {
            if (blank($k->raum)) {
                do {
                    $k->raum = Jitsi::raumName($k->titel);
                } while (self::query()->where('raum', $k->raum)->exists());
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function teilnahmeLink(): string
    {
        return app(Jitsi::class)->teilnahmeLink($this->raum);
    }

    /** Ende des Tages nach der Konferenz, 23:59:59 Uhr Ortszeit. */
    public function moderatorAblauf(): Carbon
    {
        return Carbon::createFromTimestamp(Jitsi::ablauf($this->beginn), Jitsi::ZEITZONE);
    }

    public function moderatorGueltig(): bool
    {
        return $this->moderatorAblauf()->isFuture();
    }

    /** Null, wenn abgelaufen oder kein Secret eingerichtet ist. */
    public function moderatorLink(): ?string
    {
        return $this->moderatorGueltig() ? app(Jitsi::class)->moderatorLink($this->raum, Jitsi::ablauf($this->beginn)) : null;
    }

    public function ende(): Carbon
    {
        return $this->beginn->copy()->addMinutes(max(15, (int) ($this->dauer_minuten ?: 60)));
    }

    /** Kalendereintrag (iCalendar) mit dem Teilnahme-Link, ohne Moderator-Token. */
    public function ics(): string
    {
        $esc = fn (string $t) => str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $t);
        $utc = fn (Carbon $c) => $c->copy()->utc()->format('Ymd\THis\Z');
        $host = (string) parse_url($this->teilnahmeLink(), PHP_URL_HOST);

        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Kluft//Videokonferenz//DE',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$this->raum.'@'.$host,
            'DTSTAMP:'.$utc(now()),
            'DTSTART:'.$utc($this->beginn),
            'DTEND:'.$utc($this->ende()),
            'SUMMARY:'.$esc($this->titel),
            'LOCATION:'.$esc($this->teilnahmeLink()),
            'URL:'.$this->teilnahmeLink(),
            'DESCRIPTION:'.$esc('Videokonferenz: '.$this->teilnahmeLink()),
            'END:VEVENT',
            'END:VCALENDAR',
            '',
        ]);
    }

    public function einladung(): string
    {
        $b = $this->beginn->copy()->setTimezone(Jitsi::ZEITZONE)->locale('de');

        return implode("\n", [
            'Einladung zur Videokonferenz „'.$this->titel.'“',
            'Wann: '.$b->isoFormat('dddd, DD.MM.YYYY, HH:mm').' Uhr',
            'Link zum Beitritt: '.$this->teilnahmeLink(),
            'Hinweis: Die Konferenz beginnt, sobald der Gastgeber da ist. Bis dahin bitte kurz warten.',
        ]);
    }
}
