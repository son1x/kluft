<?php

use Illuminate\Support\Facades\Schedule;

// Nextcloud-Kalender alle 15 Minuten abrufen (F-CAL-02). Voraussetzung auf
// dem Server: Cronjob "php artisan schedule:run" jede Minute (oder alle
// 15 Minuten) im Hosting-Panel.
Schedule::command('calendar:sync')->everyFifteenMinutes()->withoutOverlapping();
