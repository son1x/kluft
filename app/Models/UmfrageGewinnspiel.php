<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Teilnahme am Gewinnspiel einer Umfrage: getrennt von den Antworten,
 * ohne Verweis auf sie.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon $eingegangen_am
 * @property bool $gewonnen
 */
class UmfrageGewinnspiel extends Model
{
    public $timestamps = false;

    protected $table = 'umfrage_gewinnspiel';

    protected $fillable = ['umfrage_id', 'name', 'email', 'eingegangen_am', 'gewonnen'];

    protected function casts(): array
    {
        return ['eingegangen_am' => 'date', 'gewonnen' => 'boolean'];
    }

    /**
     * @return BelongsTo<Umfrage, $this>
     */
    public function umfrage(): BelongsTo
    {
        return $this->belongsTo(Umfrage::class);
    }
}
