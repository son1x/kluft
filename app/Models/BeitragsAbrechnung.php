<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Rechnung des Bundesverbands über die Mitgliedsbeiträge eines Halbjahres.
 *
 * @property int $id
 * @property string $halbjahr
 * @property ?int $mitglieder
 * @property ?int $rechnungsbetrag_cent
 * @property ?Carbon $rechnungsdatum
 * @property ?Carbon $bezahlt_am
 * @property ?string $notiz
 */
class BeitragsAbrechnung extends Model
{
    protected $table = 'beitrags_abrechnungen';

    protected $fillable = ['halbjahr', 'mitglieder', 'rechnungsbetrag_cent', 'rechnungsdatum', 'bezahlt_am', 'notiz'];

    protected function casts(): array
    {
        return ['mitglieder' => 'integer', 'rechnungsbetrag_cent' => 'integer', 'rechnungsdatum' => 'date', 'bezahlt_am' => 'date'];
    }
}
