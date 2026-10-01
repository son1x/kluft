<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarEvent extends Model
{
    protected $table = 'calendar_events_cache';

    public $timestamps = false;

    protected $fillable = ['calendar_id', 'uid', 'starts_at', 'ends_at', 'all_day', 'title', 'location', 'description'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Calendar, $this>
     */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }

    /**
     * Ganztägige Termine enden laut ICS am Folgetag (exklusiv); für die
     * Anzeige zählt der letzte tatsächliche Tag.
     */
    public function lastDay(): ?CarbonInterface
    {
        if (! $this->ends_at) {
            return null;
        }

        return $this->all_day ? $this->ends_at->copy()->subDay() : $this->ends_at;
    }
}
