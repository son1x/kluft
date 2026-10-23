<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Frage einer Umfrage. Typen und Einstellungen: App\Support\Umfragen::TYPEN.
 *
 * @property int $id
 * @property string $schluessel
 * @property int $sort
 * @property string $typ
 * @property string $text
 * @property ?string $hilfe
 * @property bool $pflicht
 * @property bool $kommentar
 * @property list<string>|null $optionen
 * @property array<string, mixed>|null $einstellungen
 * @property array{frage?: string, werte?: list<string>}|null $bedingung
 */
class UmfrageFrage extends Model
{
    protected $table = 'umfrage_fragen';

    protected $fillable = ['umfrage_id', 'schluessel', 'sort', 'typ', 'text', 'hilfe', 'pflicht', 'kommentar', 'optionen', 'einstellungen', 'bedingung'];

    protected function casts(): array
    {
        return ['pflicht' => 'boolean', 'kommentar' => 'boolean', 'optionen' => 'array', 'einstellungen' => 'array', 'bedingung' => 'array', 'sort' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $frage) {
            $frage->schluessel = filled($frage->schluessel) ? $frage->schluessel : (string) Str::uuid();
        });
    }

    /**
     * @return BelongsTo<Umfrage, $this>
     */
    public function umfrage(): BelongsTo
    {
        return $this->belongsTo(Umfrage::class);
    }

    /** @return list<string> */
    public function optionenListe(): array
    {
        return array_values(array_filter(array_map(fn ($o) => trim((string) $o), (array) $this->optionen), fn ($o) => $o !== ''));
    }

    public function einstellung(string $key, mixed $standard = null): mixed
    {
        return ($this->einstellungen ?? [])[$key] ?? $standard;
    }

    public function istAntwortfrage(): bool
    {
        return $this->typ !== 'abschnitt';
    }
}
