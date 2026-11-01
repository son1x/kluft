<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Eine Zeile der Unterschiedsliste Meldung ↔ NaMi. */
class DatenabgleichZeile extends Model
{
    protected $table = 'datenabgleich_zeilen';

    protected $fillable = ['quelle', 'quelle_id', 'status', 'ergebnis', 'erledigt_am', 'erledigt_von'];

    protected $hidden = ['ergebnis'];

    public const STATUS = [
        'abweichung' => 'weicht ab',
        'stimmt' => 'stimmt',
        'nicht_gefunden' => 'in NaMi nicht gefunden',
        'unklar' => 'mehrere Treffer',
    ];

    protected function casts(): array
    {
        return ['ergebnis' => 'encrypted:array', 'erledigt_am' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function erlediger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'erledigt_von');
    }
}
