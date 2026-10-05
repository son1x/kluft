<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Datei auf der Download-Seite (Methoden, Formulare, Arbeitshilfen). Typ,
 * Größe, Seiten bzw. Dateien und Stand ermittelt das Modell beim Speichern
 * selbst; die Redaktion gibt nur Titel, Beschreibung und Einordnung an.
 *
 * @property Carbon|null $file_updated_at
 */
class Download extends Model
{
    public const DISK = 'local';

    /** Erlaubte Endungen; keine HTML-, SVG- oder Skriptdateien. */
    public const EXTENSIONS = ['pdf', 'doc', 'docx', 'odt', 'xls', 'xlsx', 'ods', 'ppt', 'pptx', 'odp', 'zip', 'jpg', 'jpeg', 'png'];

    /** Endung => [Bezeichnung, Farbfamilie] */
    private const TYPES = [
        'pdf' => ['PDF', 'pdf'],
        'doc' => ['Word', 'text'], 'docx' => ['Word', 'text'], 'odt' => ['Text', 'text'],
        'xls' => ['Tabelle', 'tabelle'], 'xlsx' => ['Tabelle', 'tabelle'], 'ods' => ['Tabelle', 'tabelle'],
        'ppt' => ['Präsentation', 'praesentation'], 'pptx' => ['Präsentation', 'praesentation'], 'odp' => ['Präsentation', 'praesentation'],
        'zip' => ['ZIP-Archiv', 'zip'],
        'jpg' => ['Bild', 'bild'], 'jpeg' => ['Bild', 'bild'], 'png' => ['Bild', 'bild'],
    ];

    /** Wie lange eine neue oder ersetzte Datei als „neu“ gilt. */
    public const NEU_TAGE = 30;

    protected $fillable = [
        'title', 'description', 'category', 'stage', 'section',
        'file_path', 'file_name', 'rights_confirmed', 'published', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'parts' => 'integer',
            'file_updated_at' => 'datetime',
            'rights_confirmed' => 'boolean',
            'published' => 'boolean',
            'sort' => 'integer',
            'downloads' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $download) {
            if ($download->isDirty('file_path')) {
                $download->readFileInfo();
            }
            if (blank($download->file_name)) {
                $download->file_name = basename((string) $download->file_path);
            }
        });

        // Datei mit dem Eintrag löschen bzw. die ersetzte Datei entfernen.
        static::deleted(fn (self $download) => Storage::disk(self::DISK)->delete($download->file_path));
        static::updated(function (self $download) {
            $alt = $download->getOriginal('file_path');
            if ($download->wasChanged('file_path') && filled($alt)) {
                Storage::disk(self::DISK)->delete($alt);
            }
        });
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('published', true)->where('rights_confirmed', true);
    }

    /** Größe, MIME-Typ, Seiten (PDF) bzw. Dateien (ZIP) und Stand aus der Datei. */
    public function readFileInfo(): void
    {
        $disk = Storage::disk(self::DISK);
        if (! $disk->exists((string) $this->file_path)) {
            return;
        }

        $this->size = (int) $disk->size($this->file_path);
        $this->mime = $disk->mimeType($this->file_path) ?: null;
        $this->file_updated_at = now();
        $this->parts = match ($this->extension()) {
            'pdf' => self::pdfPages($disk->path($this->file_path)),
            'zip' => self::zipFiles($disk->path($this->file_path)),
            default => null,
        };
    }

    public function extension(): string
    {
        return Str::lower(pathinfo((string) ($this->file_name ?: $this->file_path), PATHINFO_EXTENSION));
    }

    /** Kurzzeichen auf dem Dateisymbol, z. B. „PDF“, „DOCX“. */
    public function badge(): string
    {
        return Str::upper($this->extension() === 'jpeg' ? 'jpg' : $this->extension());
    }

    /** Farbfamilie für das Dateisymbol (pdf, text, tabelle, praesentation, zip, bild). */
    public function family(): string
    {
        return self::TYPES[$this->extension()][1] ?? 'zip';
    }

    /** Infozeile, z. B. „12 Seiten · 1,4 MB · Stand 03/2026“. */
    public function meta(): string
    {
        $art = match ($this->extension()) {
            'pdf' => $this->parts ? $this->parts.' '.($this->parts === 1 ? 'Seite' : 'Seiten') : 'PDF',
            'zip' => $this->parts ? $this->parts.' '.($this->parts === 1 ? 'Datei' : 'Dateien') : 'ZIP-Archiv',
            default => self::TYPES[$this->extension()][0] ?? 'Datei',
        };

        return collect([
            $art,
            self::groesse($this->size),
            $this->file_updated_at ? 'Stand '.$this->file_updated_at->format('m/Y') : null,
        ])->filter()->implode(' · ');
    }

    /** Dateigröße auf Deutsch, z. B. „620 KB“, „1,4 MB“. */
    public static function groesse(int $bytes): string
    {
        return match (true) {
            $bytes >= 1024 * 1024 => number_format($bytes / 1024 / 1024, 1, ',', '.').' MB',
            $bytes >= 1024 => number_format($bytes / 1024, 0, ',', '.').' KB',
            default => $bytes.' Byte',
        };
    }

    public function isNew(): bool
    {
        return $this->file_updated_at !== null && $this->file_updated_at->gt(now()->subDays(self::NEU_TAGE));
    }

    /** Lesbarer Dateiname für die Auslieferung, mit der echten Endung. */
    public function downloadName(): string
    {
        $base = Str::slug(pathinfo((string) $this->file_name, PATHINFO_FILENAME)) ?: Str::slug($this->title) ?: 'download';

        return $base.'.'.$this->extension();
    }

    public function url(): string
    {
        return route('downloads.file', ['download' => $this->id, 'name' => $this->downloadName()]);
    }

    /** Stufe/Fachbereich als Schlagworte: [[Bezeichnung, CSS-Klasse], …] */
    public function tags(): array
    {
        $tags = [];
        if ($this->stage && ($stage = config("dpsg.stages.{$this->stage}"))) {
            $tags[] = [$stage['label'], 'stufe-'.$this->stage.' bg-(--stufe) text-(--stufe-ink)'];
        }
        if ($this->section && ($section = config("dpsg.sections.{$this->section}"))) {
            $tags[] = [$section['label'], 'fach-'.$section['color'].' bg-(--fach) text-(--fach-ink)'];
        }

        return $tags;
    }

    public static function categories(): array
    {
        return (array) config('dpsg.downloads.categories', []);
    }

    public function categoryLabel(): string
    {
        return self::categories()[$this->category] ?? Str::headline($this->category);
    }

    /**
     * Seitenzahl ohne Zusatzsoftware: zählt die Seitenobjekte. Bei
     * komprimierten Objektströmen ggf. ungenau, dann null.
     */
    private static function pdfPages(string $path): ?int
    {
        $inhalt = (string) @file_get_contents($path);
        // PDF ab 1.5: Seitenbaum oft in komprimierten Objektströmen (ObjStm).
        if (preg_match_all('#/Type\s*/ObjStm[^>]*>>\s*stream\r?\n(.*?)endstream#s', $inhalt, $stroeme)) {
            foreach ($stroeme[1] as $strom) {
                $inhalt .= "\n".(string) @gzuncompress(rtrim($strom, "\r\n"));
            }
        }
        if (preg_match_all('#/Type\s*/Pages\b[^>]*?/Count\s+(\d+)#s', $inhalt, $m)) {
            return max(array_map('intval', $m[1])) ?: null;
        }
        $seiten = preg_match_all('#/Type\s*/Page\b(?!s)#', $inhalt);

        return $seiten ?: null;
    }

    private static function zipFiles(string $path): ?int
    {
        if (! class_exists(ZipArchive::class)) {
            return null;
        }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return null;
        }
        $dateien = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (! str_ends_with($name, '/') && ! str_starts_with(basename($name), '.') && ! str_starts_with($name, '__MACOSX/')) {
                $dateien++;
            }
        }
        $zip->close();

        return $dateien ?: null;
    }
}
