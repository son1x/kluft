<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Zähler je Tag und Pfad (docs/aufrufe.md). Nichts Personenbezogenes:
 * keine IP, kein User-Agent, keine Kennung.
 */
class Seitenaufruf extends Model
{
    protected $table = 'seitenaufrufe';

    // Eindeutig ist (datum, pfad); eine eigene Kennung gibt es bewusst nicht.
    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['datum', 'pfad', 'aufrufe'];

    protected function casts(): array
    {
        return [
            'datum' => 'date',
            'aufrufe' => 'integer',
        ];
    }
}
