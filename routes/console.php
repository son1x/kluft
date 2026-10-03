<?php

use App\Support\Roadmap;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// Nextcloud-Kalender alle 15 Minuten abrufen (F-CAL-02). Voraussetzung auf
// dem Server: Cronjob "php artisan schedule:run" jede Minute (oder alle
// 15 Minuten) im Hosting-Panel.
Schedule::command('calendar:sync')->everyFifteenMinutes()->withoutOverlapping();

// Löschfristen der Veranstaltungsanmeldungen (Pflichtenheft 9.3, 11.4).
Schedule::command('registrations:purge --unconfirmed')->hourly();
Schedule::command('registrations:purge')->dailyAt('03:00');

// Audit-Log nach 12 Monaten bereinigen (Pflichtenheft 8.2, 11.4).
Schedule::command('audit:prune')->dailyAt('03:30');

// Mail-Warteschlange (F-MAIL-05, Pflichtenheft 3/156): ohne Daemon – der
// Cronjob startet jede Minute einen Worker, der die Schlange abarbeitet und
// sich dann beendet. Fehlgeschlagene Mails nach 14 Tagen verwerfen.
Schedule::command('queue:work --stop-when-empty --max-time=50 --sleep=1')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->runInBackground()
    ->name('mail-warteschlange');
Schedule::command('queue:prune-failed --hours=336')->dailyAt('03:45');

// Lebenszeichen des Schedulers, damit /roadmap anzeigen kann, ob der
// Cronjob auf dem Server läuft.
Schedule::call(fn () => Cache::forever(Roadmap::HEARTBEAT_KEY, now()->timestamp))->everyMinute()->name('scheduler-heartbeat');

// Instagram-Token wöchentlich erneuern (F-IG-04, Pflichtenheft 11.4).
Schedule::command('instagram:refresh-token')->weeklyOn(1, '04:00');

// Backups (Pflichtenheft 10.2, 11.4): täglich 02:00, Wiederherstellungstest
// wöchentlich.
Schedule::command('backup:run')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('backup:verify')->weeklyOn(0, '05:00');
