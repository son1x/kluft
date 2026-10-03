<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Dokument zu einer Anmeldung, z. B. eine Rechnung aus der Buchhaltung oder
 * eine Teilnahmebescheinigung. Die Datei liegt auf der privaten Ablage und
 * verschwindet mit dem Datensatz.
 */
class RegistrationDocument extends Model
{
    public const DISK = 'local';

    public const TYPES = [
        'rechnung' => 'Rechnung',
        'bescheinigung' => 'Teilnahmebescheinigung',
        'sonstiges' => 'Dokument',
    ];

    protected $fillable = ['event_registration_id', 'type', 'title', 'path', 'original_name', 'size', 'uploaded_by'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleted(fn (self $document) => Storage::disk(self::DISK)->delete($document->path));
    }

    /**
     * @return BelongsTo<EventRegistration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id');
    }

    public function label(): string
    {
        return $this->title ?: (self::TYPES[$this->type] ?? 'Dokument');
    }

    /**
     * Dateiname beim Herunterladen, z. B. „Rechnung – Diözesanversammlung 2026.pdf“.
     */
    public function downloadName(): string
    {
        $name = $this->label().' – '.$this->registration->event->title;

        return str_replace(['/', '\\', '"'], '-', $name).'.pdf';
    }

    /**
     * Dateien mitlöschen, bevor Anmeldungen per Massenlöschung (ohne
     * Model-Events) oder über die Veranstaltung verschwinden.
     *
     * @param  iterable<int>  $registrationIds
     */
    public static function purgeFor(iterable $registrationIds): void
    {
        foreach (collect($registrationIds)->chunk(500) as $chunk) {
            static::query()->whereIn('event_registration_id', $chunk->all())->get()->each->delete();
        }
    }
}
