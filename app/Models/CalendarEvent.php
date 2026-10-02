<?php

namespace App\Models;

use App\Support\CalendarItem;
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
     * Veranstaltung mit Anmeldung zu diesem Termin, falls es eine gibt
     * (aus dem Termin angelegt oder gleicher Tag und Titel).
     */
    public function matchingEvent(): ?Event
    {
        $item = CalendarItem::fromCalendarEvent($this);

        return Event::query()->whereDate('starts_at', $this->starts_at->toDateString())->get()
            ->first(fn (Event $event) => CalendarItem::fromEvent($event)->sameAs($item));
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
