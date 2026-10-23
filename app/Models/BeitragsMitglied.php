<?php

namespace App\Models;

use App\Support\Beitraege;
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
 * @property bool $beitragsart_manuell
 * @property bool $details_verweigert
 * @property ?Carbon $eintritt
 * @property ?Carbon $austritt
 * @property bool $aktiv
 * @property ?int $nami_version
 */
class BeitragsMitglied extends Model
{
    protected $table = 'beitrags_mitglieder';

    protected $fillable = ['nami_id', 'mitgliedsnummer', 'vorname', 'nachname', 'beitragsart', 'beitragsart_manuell', 'details_verweigert', 'schnupper', 'eintritt', 'austritt', 'aktiv', 'nami_version', 'abgerufen_am'];

    protected function casts(): array
    {
        return ['eintritt' => 'date', 'austritt' => 'date', 'aktiv' => 'boolean', 'schnupper' => 'boolean', 'beitragsart_manuell' => 'boolean', 'details_verweigert' => 'boolean', 'abgerufen_am' => 'datetime', 'nami_id' => 'integer', 'nami_version' => 'integer'];
    }

    /**
     * @return HasMany<Beitrag, $this>
     */
    public function beitraege(): HasMany
    {
        return $this->hasMany(Beitrag::class);
    }

    /** Beitragsart von Hand setzen; offene Beiträge folgen ihr (wie beim Abruf). */
    public function beitragsartSetzen(string $art): int
    {
        $this->forceFill(['beitragsart' => $art, 'beitragsart_manuell' => true])->save();
        $angepasst = 0;
        // Wie beim Abruf: noch nicht bezahlte und nicht erlassene Beiträge.
        foreach ($this->beitraege()->whereNull('bezahlt_am')->where(fn ($q) => $q->whereNull('zahlart')->orWhere('zahlart', '!=', 'erlassen'))->get() as $beitrag) {
            $soll = $this->schnupper ? ['soll' => 0, 'bund' => 0] : Beitraege::soll($art);
            $beitrag->fill(['beitragsart' => $this->schnupper ? $beitrag->beitragsart : $art, 'soll_cent' => $soll['soll'], 'bundes_cent' => $soll['bund']])->save();
            $angepasst++;
        }

        return $angepasst;
    }

    public function name(): string
    {
        return trim($this->nachname.', '.$this->vorname, ', ');
    }
}
