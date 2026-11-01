<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Support\NextcloudAblage;
use Illuminate\Console\Command;
use Throwable;

/**
 * Nächtlicher Abgleich der Zuschussunterlagen mit der Nextcloud-Ablage
 * (ZU-40): Veranstaltungen mit Zuschuss von 14 Tagen vor Beginn bis 90
 * Tage nach Ende.
 */
class ZuschussNextcloudCommand extends Command
{
    protected $signature = 'zuschuss:nextcloud';

    protected $description = 'Zuschussunterlagen in der Nextcloud-Ablage aktualisieren';

    public function handle(): int
    {
        if (! NextcloudAblage::eingerichtet()) {
            return self::SUCCESS;
        }
        $events = Event::query()->where('zuschuss', true)
            ->where('starts_at', '<=', now()->addDays(14))
            ->where(fn ($q) => $q->where('ends_at', '>=', now()->subDays(90))->orWhere(fn ($q) => $q->whereNull('ends_at')->where('starts_at', '>=', now()->subDays(90))))
            ->get();
        foreach ($events as $event) {
            try {
                $this->info($event->title.': '.NextcloudAblage::abgleichen($event).' Datei(en)');
            } catch (Throwable $e) {
                report($e);
                $this->warn($event->title.': '.$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
