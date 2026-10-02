<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamPhotoSpot extends Model
{
    public $timestamps = false;

    protected $fillable = ['team_photo_id', 'person_id', 'x', 'y'];

    protected function casts(): array
    {
        return ['x' => 'float', 'y' => 'float'];
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
