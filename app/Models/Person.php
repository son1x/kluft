<?php

namespace App\Models;

use App\Support\Site;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Person in Diözesanleitung, Diözesanbüro oder einem Diözesanarbeitskreis
 * (Personenvorstellung).
 */
class Person extends Model
{
    protected $table = 'people';

    protected $fillable = [
        'gremium', 'section', 'subgroup', 'name', 'role', 'stage', 'badge', 'email', 'bio',
        'photo_media_id', 'consent', 'active', 'sort',
    ];

    protected function casts(): array
    {
        return ['consent' => 'boolean', 'active' => 'boolean', 'sort' => 'integer'];
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function photo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'photo_media_id');
    }

    /**
     * Öffentlich: aktiv und Veröffentlichung abgestimmt.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('active', true)->where('consent', true);
    }

    public function initials(): string
    {
        return Str::of($this->name)->squish()->explode(' ')
            ->filter(fn (string $part) => $part !== '' && ! in_array(mb_strtolower($part), ['von', 'van', 'de', 'der', 'zu'], true))
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->pipe(fn ($parts) => $parts->count() > 1 ? $parts->first().$parts->last() : $parts->first())
            ?? '?';
    }

    protected static function booted(): void
    {
        // DAK-Mitglieder haben keine eigene Adresse; Kontakt läuft über die
        // Stufenreferent*innen.
        static::saving(function (Person $person) {
            if ($person->gremium === 'dak') {
                $person->email = null;
            }
        });
    }

    /**
     * Adresse nach dem Schema Initial.Nachname@dpsg-trier.de, z. B.
     * „Silas Weiß“ → s.weiss@dpsg-trier.de. Null, wenn der Name nur aus
     * einem Wort besteht.
     */
    public static function suggestedEmail(?string $name): ?string
    {
        $parts = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);
        if (count($parts) < 2) {
            return null;
        }

        $ascii = fn (string $s) => Str::lower(Str::ascii(strtr($s, [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss',
        ])));
        $initial = mb_substr($ascii($parts[0]), 0, 1);
        $last = preg_replace('/[^a-z0-9-]/', '', $ascii(end($parts)));

        return $initial !== '' && $last !== '' ? "{$initial}.{$last}@".config('dpsg.person_email_domain') : null;
    }

    /**
     * Öffentlich angezeigte Adresse: eingetragene Adresse, sonst nach
     * Schema; für DAK-Mitglieder keine.
     */
    public function publicEmail(): ?string
    {
        if ($this->gremium === 'dak') {
            return null;
        }

        return filled($this->email) ? $this->email : self::suggestedEmail($this->name);
    }

    public function anchor(): string
    {
        return 'person-'.Str::slug($this->name);
    }

    public function gremiumLabel(): string
    {
        if ($this->gremium === 'dak' && $this->stage) {
            return Site::akShort().' '.config("dpsg.stages.{$this->stage}.label", $this->stage);
        }

        return config("dpsg.gremien.{$this->gremium}.label", $this->gremium);
    }

    public function pageUrl(): string
    {
        $slug = config("dpsg.gremien.{$this->gremium}.page");

        // DAK-Mitglieder stehen auf der Seite ihrer Stufe.
        if ($slug === null && $this->stage) {
            $slug = Page::query()->published()->where('stage', $this->stage)->value('slug');
        }

        return url('/'.($slug ?? '')).'#'.$this->anchor();
    }
}
