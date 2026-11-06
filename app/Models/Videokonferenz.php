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
 * @property string $raum
 * @property int|null $user_id
 */
class Videokonferenz extends Model
{
    protected $table = 'videokonferenzen';

    protected $fillable = ['titel', 'beginn', 'raum', 'user_id'];

    protected function casts(): array
    {
        return ['beginn' => 'datetime'];
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
