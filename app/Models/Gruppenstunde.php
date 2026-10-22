<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Wann und wo sich eine Gruppe trifft. Eine Stufe kann mehrere Gruppen haben
 * (Name unterscheidet sie). Angezeigt auf der Startseite und der Stufenseite.
 */
class Gruppenstunde extends Model
{
    protected $table = 'gruppenstunden';

    public const WOCHENTAGE = [1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag'];

    protected $fillable = ['stage', 'name', 'wochentag', 'beginn', 'ende', 'ort', 'hinweis', 'sort', 'active'];

    protected function casts(): array
    {
        return ['wochentag' => 'integer', 'sort' => 'integer', 'active' => 'boolean'];
    }

    /** @param Builder<Gruppenstunde> $query */
    public function scopeAktiv(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /**
     * Aktive Gruppenstunden in Stufenreihenfolge, dann nach Reihenfolge und Tag.
     *
     * @return Collection<int, Gruppenstunde>
     */
    public static function angezeigt(?string $stage = null): Collection
    {
        $stufen = array_keys((array) config('dpsg.stages'));

        return self::query()->aktiv()
            ->whereIn('stage', $stage ? [$stage] : $stufen)
            ->orderBy('sort')->orderBy('wochentag')->orderBy('beginn')
            ->get()
            ->sortBy(fn (Gruppenstunde $g) => array_search($g->stage, $stufen, true))
            ->values();
    }

    /** „dienstags, 16:00–17:00 Uhr“; ohne Tag nur die Uhrzeit bzw. leer. */
    public function zeitText(): string
    {
        $tag = self::WOCHENTAGE[$this->wochentag] ?? null;
        $von = $this->beginn ? substr((string) $this->beginn, 0, 5) : null;
        $bis = $this->ende ? substr((string) $this->ende, 0, 5) : null;
        $uhr = $von ? $von.($bis ? '–'.$bis : '').' Uhr' : '';

        return trim(($tag ? mb_strtolower($tag).'s' : '').($tag && $uhr ? ', ' : '').$uhr);
    }

    /** Eigener Ort, sonst die Adresse des Stamms. */
    public function ortText(): string
    {
        if (filled($this->ort)) {
            return (string) $this->ort;
        }

        return implode(', ', array_filter([config('dpsg.contact.street'), config('dpsg.contact.city')]));
    }

    /**
     * Freitext wie „dienstags, 16:00–17:00 Uhr“ (Einrichtungsassistent,
     * angaben.json) in Tag und Uhrzeit zerlegen. Was nicht passt, landet im Hinweis.
     *
     * @return array{wochentag: ?int, beginn: ?string, ende: ?string, hinweis: ?string}
     */
    public static function ausText(string $text): array
    {
        $text = trim($text);
        $tag = null;
        foreach (self::WOCHENTAGE as $nr => $name) {
            if (preg_match('/\b'.mb_substr(mb_strtolower($name), 0, 2).'/u', mb_strtolower($text))) {
                $tag = $nr;
                break;
            }
        }
        $beginn = $ende = null;
        if (preg_match('/(\d{1,2})[:.](\d{2})\s*(?:[–-]|bis)\s*(\d{1,2})[:.](\d{2})/u', $text, $m)) {
            $beginn = sprintf('%02d:%s', $m[1], $m[2]);
            $ende = sprintf('%02d:%s', $m[3], $m[4]);
        } elseif (preg_match('/(\d{1,2})[:.](\d{2})/', $text, $m)) {
            $beginn = sprintf('%02d:%s', $m[1], $m[2]);
        } elseif (preg_match('/(\d{1,2})\s*Uhr/u', $text, $m)) {
            $beginn = sprintf('%02d:00', $m[1]);
        }

        // Nur ganz erkannt übernehmen, sonst bleibt der Text als Hinweis stehen.
        return $tag !== null && $beginn !== null
            ? ['wochentag' => $tag, 'beginn' => $beginn, 'ende' => $ende, 'hinweis' => null]
            : ['wochentag' => null, 'beginn' => null, 'ende' => null, 'hinweis' => mb_substr($text, 0, 160)];
    }
}
