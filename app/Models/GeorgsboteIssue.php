<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class GeorgsboteIssue extends Model
{
    protected $fillable = [
        'year',
        'issue_no',
        'title',
        'pdf_path',
        'cover_path',
        'published',
        'published_at',
        'privacy_checked',
        'privacy_checked_by',
        'privacy_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'issue_no' => 'integer',
            'published' => 'boolean',
            'published_at' => 'datetime',
            'privacy_checked' => 'boolean',
            'privacy_checked_at' => 'datetime',
        ];
    }

    /**
     * Öffentlich nur, wenn freigegeben, Personenbezug geprüft und das
     * Veröffentlichungsdatum erreicht ist.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('published', true)
            ->where('privacy_checked', true)
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public function downloadUrl(): string
    {
        return route('georgsbote.pdf', ['jahr' => $this->year, 'ausgabe' => $this->issue_no]);
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }

    /**
     * Jahrgang und Ausgabe aus einem Dateinamen raten, z. B.
     * „Georgsbote_2019_2.pdf“, „GB 3-2018.pdf“ oder „2017-04 Georgsbote.pdf“.
     * Null, wenn kein vierstelliger Jahrgang oder keine Ausgabennummer
     * erkennbar ist.
     *
     * @return array{year: int, issue_no: int}|null
     */
    public static function guessFromFilename(string $filename): ?array
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        if (! preg_match('/(?<!\d)(19[5-9]\d|20\d\d)(?!\d)/', $name, $year, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $rest = substr_replace($name, ' ', $year[0][1], 4);
        if (! preg_match('/(?<!\d)0?([1-9]\d?)(?!\d)/', $rest, $issue)) {
            return null;
        }

        return ['year' => (int) $year[1][0], 'issue_no' => (int) $issue[1]];
    }

    public function label(): string
    {
        return "Ausgabe {$this->issue_no}/{$this->year}";
    }
}
