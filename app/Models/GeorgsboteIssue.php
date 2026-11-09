<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class GeorgsboteIssue extends Model
{
    /** Verbandsheft „Georgsbote“ (issue_no = Ausgabe im Jahrgang) */
    public const BOTE = 'bote';

    /** Newsletter „Georgsbrief“ als PDF (issue_no = Monat) */
    public const BRIEF = 'brief';

    public const MONATE = [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

    protected $attributes = ['art' => self::BOTE];

    protected $fillable = [
        'art',
        'year',
        'issue_no',
        'title',
        'inhalt',
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

    public function istBrief(): bool
    {
        return $this->art === self::BRIEF;
    }

    public function downloadUrl(): string
    {
        return $this->istBrief()
            ? route('georgsbrief.pdf', ['jahr' => $this->year, 'monat' => $this->issue_no])
            : route('georgsbote.pdf', ['jahr' => $this->year, 'ausgabe' => $this->issue_no]);
    }

    /** Vorgabe-Titel nach dem Import, z. B. „Georgsbote 2/2019“ bzw. „Georgsbrief Februar 2019“. */
    public static function standardTitel(string $art, int $year, int $no): string
    {
        return $art === self::BRIEF
            ? 'Georgsbrief '.(self::MONATE[$no] ?? $no).' '.$year
            : "Georgsbote {$no}/{$year}";
    }

    /**
     * Art, Jahrgang und Ausgabe bzw. Monat aus dem Dateinamen: Steht
     * „Georgsbrief“ darin, ist es ein Georgsbrief (Monat als Zahl oder
     * Name, z. B. „Georgsbrief_2019_02.pdf“, „Georgsbrief Februar 2019.pdf“),
     * sonst ein Georgsbote (guessFromFilename).
     *
     * @return array{art: string, year: int, issue_no: int}|null
     */
    public static function erkennen(string $filename): ?array
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        if (! preg_match('/georgsbrief/i', $name)) {
            $guess = self::guessFromFilename($filename);

            return $guess ? ['art' => self::BOTE, ...$guess] : null;
        }

        if (! preg_match('/(?<!\d)(19[5-9]\d|20\d\d)(?!\d)/', $name, $year, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $rest = mb_strtolower(substr_replace($name, ' ', $year[0][1], 4));
        foreach (self::MONATE as $nr => $monat) {
            if (str_contains($rest, mb_strtolower($monat)) || ($nr === 3 && str_contains($rest, 'maerz'))) {
                return ['art' => self::BRIEF, 'year' => (int) $year[1][0], 'issue_no' => $nr];
            }
        }
        if (preg_match('/(?<!\d)0?(1[0-2]|[1-9])(?!\d)/', $rest, $monat)) {
            return ['art' => self::BRIEF, 'year' => (int) $year[1][0], 'issue_no' => (int) $monat[1]];
        }

        return null;
    }

    /**
     * Ausgaben (Georgsbote und Georgsbrief) mit PDF.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeMitPdf(Builder $query): Builder
    {
        return $query->whereNotNull('pdf_path')->where('pdf_path', '!=', '');
    }

    /**
     * Ausgaben (Georgsbote und Georgsbrief) mit PDF, aber ohne Titelbild.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOhneTitelbild(Builder $query): Builder
    {
        return $query->mitPdf()->where(fn (Builder $q) => $q->whereNull('cover_path')->orWhere('cover_path', ''));
    }

    /**
     * Auftrag für den Renderer im Browser (resources/js/titelbild.js).
     *
     * @return array{id: int, label: string, pdfUrl: string, uploadUrl: string}
     */
    public function titelbildAuftrag(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label(),
            'pdfUrl' => route('georgsbote.vorschau-pdf', $this),
            'uploadUrl' => route('georgsbote.titelbild', $this),
        ];
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
        if ($this->istBrief()) {
            return 'Georgsbrief '.(self::MONATE[$this->issue_no] ?? $this->issue_no).' '.$this->year;
        }

        return "Ausgabe {$this->issue_no}/{$this->year}";
    }
}
