<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Mitglied des Stamms für die Beitragskasse, aus NaMi übernommen (nur die
 * Angaben, die für den Beitrag nötig sind).
 *
 * @property int $id
 * @property int $nami_id
 * @property ?string $mitgliedsnummer
 * @property string $vorname
 * @property string $nachname
 * @property ?string $beitragsart
 * @property ?Carbon $eintritt
 * @property ?Carbon $austritt
 * @property bool $aktiv
 * @property ?int $nami_version
 */
class BeitragsMitglied extends Model
{
    protected $table = 'beitrags_mitglieder';

    protected $fillable = ['nami_id', 'mitgliedsnummer', 'vorname', 'nachname', 'beitragsart', 'eintritt', 'austritt', 'aktiv', 'nami_version', 'abgerufen_am'];

    protected function casts(): array
    {
        return ['eintritt' => 'date', 'austritt' => 'date', 'aktiv' => 'boolean', 'abgerufen_am' => 'datetime', 'nami_id' => 'integer', 'nami_version' => 'integer'];
    }

    /**
     * @return HasMany<Beitrag, $this>
     */
    public function beitraege(): HasMany
    {
        return $this->hasMany(Beitrag::class);
    }

    public function name(): string
    {
        return trim($this->nachname.', '.$this->vorname, ', ');
    }
}
