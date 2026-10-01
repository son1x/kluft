<?php

namespace App\Console\Commands;

use App\Models\Calendar;
use App\Services\CalendarSync;
use Illuminate\Console\Command;

/**
 * Ruft alle aktiven Nextcloud-Kalender ab (F-CAL-02), per Scheduler alle
 * 15 Minuten.
 */
class SyncCalendarsCommand extends Command
{
    protected $signature = 'calendar:sync';

    protected $description = 'Ruft alle aktiven ICS-Kalender ab und aktualisiert den Termin-Cache';

    public function handle(CalendarSync $sync): int
    {
        foreach (Calendar::query()->where('active', true)->get() as $calendar) {
            $ok = $sync->sync($calendar);
            $this->line(($ok ? 'OK   ' : 'FEHL ').$calendar->label.($ok ? '' : ': '.$calendar->last_error));
        }

        return self::SUCCESS;
    }
}
