<?php

namespace App\Models;

use App\Support\Site;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
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

    /**
     * Personen einer Gremiumsseite: das Gremium selbst, danach die unter
     * config dpsg.gremien.<gremium>.auch genannten Untergruppen anderer
     * Gremien (z. B. Bildungsreferent*innen bei der Diözesanleitung).
     *
     * @return Collection<int, self>
     */
    public static function fuerGremiumsseite(string $gremium): Collection
    {
        $personen = self::query()->visible()->where('gremium', $gremium)->with('photo')->orderBy('sort')->get();
        foreach ((array) config("dpsg.gremien.{$gremium}.auch", []) as $auch) {
            $personen = $personen->concat(self::query()->visible()->where('gremium', $auch['gremium'] ?? '')
                ->where('subgroup', $auch['subgroup'] ?? '')->with('photo')->orderBy('sort')->get());
        }

        return $personen->unique('id')->values();
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
        // Mitglieder von DAK und FAK haben keine eigene Adresse; Kontakt läuft
        // über die Referent*innen bzw. Ansprechpersonen.
        static::saving(function (Person $person) {
            if (self::ohneEigeneAdresse($person->gremium)) {
                $person->email = null;
            }
        });
    }

    /**
     * Adresse nach dem Schema Initial.Nachname@dpsg-trier.de, z. B.
     * „Silas Weiß“ → s.weiss@dpsg-trier.de. Null, wenn der Name nur aus
     * einem Wort besteht. Mit config dpsg.person_email_schema = 'vorname'
     * (z. B. Roden): „Gian-Luca Blasius“ → gianluca@dpsg-roden.de.
     */
    public static function suggestedEmail(?string $name): ?string
    {
        $parts = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);
        if (config('dpsg.person_email_schema') === 'vorname') {
            $first = $parts === [] ? '' : (string) preg_replace('/[^a-z0-9]/', '', self::ascii($parts[0]));

            return $first !== '' ? $first.'@'.config('dpsg.person_email_domain') : null;
        }
        if (count($parts) < 2) {
            return null;
        }

        $initial = mb_substr(self::ascii($parts[0]), 0, 1);
        $last = preg_replace('/[^a-z0-9-]/', '', self::ascii(end($parts)));

        return $initial !== '' && $last !== '' ? "{$initial}.{$last}@".config('dpsg.person_email_domain') : null;
    }

    private static function ascii(string $s): string
    {
        return Str::lower(Str::ascii(strtr($s, [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss',
        ])));
    }

    /**
     * Mitglieder von DAK und FAK haben keine eigene Adresse. Ausnahme: Stämme
     * mit config dpsg.person_email_leitende, dort hat jede*r im Leitungsteam
     * (Gremium „dak“) eine.
     */
    public static function ohneEigeneAdresse(?string $gremium): bool
    {
        return $gremium === 'fak' || ($gremium === 'dak' && ! config('dpsg.person_email_leitende'));
    }

    /**
     * Öffentlich angezeigte Adresse: eingetragene Adresse, sonst nach
     * Schema; für DAK-Mitglieder keine.
     */
    /** Ehrenamtlich im Arbeitskreis einer Stufe (DAK) oder eines Fachbereichs (FAK). */
    public function inArbeitskreis(): bool
    {
        return in_array($this->gremium, ['dak', 'fak'], true);
    }

    public function publicEmail(): ?string
    {
        if (self::ohneEigeneAdresse($this->gremium)) {
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
        if ($this->gremium === 'fak' && $this->section) {
            return 'FAK '.config("dpsg.sections.{$this->section}.label", $this->section);
        }

        return config("dpsg.gremien.{$this->gremium}.label", $this->gremium);
    }

    public function pageUrl(): string
    {
        $slug = config("dpsg.gremien.{$this->gremium}.page");

        // DAK-Mitglieder stehen auf der Seite ihrer Stufe, FAK-Mitglieder auf
        // der ihres Fachbereichs.
        if ($slug === null && $this->gremium === 'fak' && $this->section) {
            $slug = config("dpsg.sections.{$this->section}.landing");
        } elseif ($slug === null && $this->stage) {
            $slug = Page::query()->published()->where('stage', $this->stage)->value('slug');
        }

        return url('/'.($slug ?? '')).'#'.$this->anchor();
    }
}
