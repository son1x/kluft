<?php

use App\Services\Backup\BackupEinspielen;
use App\Services\Update\Updater;
use App\Services\WhatsApp\WhatsAppSettings;
use App\Support\Roadmap;
use App\Support\WhatsAppMode;
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

// Seitenaufrufe nach 25 Monaten löschen (docs/aufrufe.md).
Schedule::command('aufrufe:aufraeumen')->dailyAt('03:40');

// Auslagen: Löschfristen (10 Jahre ausgezahlt, 1 Jahr abgelehnt).
Schedule::command('auslagen:aufraeumen')->dailyAt('04:05');

// Mail-Warteschlange (F-MAIL-05, Pflichtenheft 3/156): ohne Daemon – der
// Cronjob startet jede Minute einen Worker, der die Schlange abarbeitet und
// sich dann beendet. Fehlgeschlagene Mails nach 14 Tagen verwerfen.
Schedule::command('queue:work --stop-when-empty --max-time=50 --sleep=1')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->runInBackground()
    ->name('mail-warteschlange');
Schedule::command('queue:prune-failed --hours=336')->dailyAt('03:45');

// Monitoring (Pflichtenheft 10.2): Störungen per Mail an die Admins.
Schedule::command('monitor:check')->everyFifteenMinutes()->withoutOverlapping();

// Lebenszeichen des Schedulers, damit /roadmap anzeigen kann, ob der
// Cronjob auf dem Server läuft.
Schedule::call(fn () => Cache::forever(Roadmap::HEARTBEAT_KEY, now()->timestamp))->everyMinute()->name('scheduler-heartbeat');

// Instagram-Token wöchentlich erneuern (F-IG-04, Pflichtenheft 11.4).
Schedule::command('instagram:refresh-token')->weeklyOn(1, '04:00');
// Instagram-Einblendung: neueste Beiträge eigener Konten, Bilder lokal (docs/instagram.md).
Schedule::command('instagram:feed')->hourly()->withoutOverlapping(30);
// Umfragen: Antworten und Gewinnspiel nach Ablauf der Frist löschen (docs/umfragen.md).
Schedule::command('umfragen:aufraeumen')->dailyAt('03:50');
Schedule::command('videokonferenzen:aufraeumen')->dailyAt('03:55');

// Backups (Pflichtenheft 10.2, 11.4): täglich 02:00, Wiederherstellungstest
// wöchentlich.
Schedule::command('backup:run')->dailyAt('02:00')->withoutOverlapping();
// Erinnerungen vor Veranstaltungen (je Veranstaltung „Erinnerung … Tage vorher“).
Schedule::command('veranstaltungen:erinnerungen')->dailyAt('09:00');
// Aufträge aus Verwaltung → Backups (jetzt sichern, einspielen); Sperre per Datei, nicht im Cache (der wird beim Einspielen ersetzt).
Schedule::command('backup:auftrag')->everyMinute()->when(fn () => is_file(BackupEinspielen::auftragPfad()));
Schedule::command('backup:verify')->weeklyOn(0, '05:00');

// WhatsApp-Newsletter (docs/whatsapp_newsletter.md): geplante Aussendungen
// starten, Zustand prüfen, Löschfristen.
Schedule::command('whatsapp:dispatch-scheduled')->everyMinute()->withoutOverlapping()->when(fn () => WhatsAppMode::api());
Schedule::command('whatsapp:check')->hourly()->when(fn () => app(WhatsAppSettings::class)->configured());
Schedule::command('whatsapp:prune')->dailyAt('03:50');

// Nachrichten-Eingang: gespeicherte Kontaktnachrichten nach der Frist löschen.
Schedule::command('kontakt:prune')->dailyAt('03:55');

// Zurückgehaltene Nachrichten (Spamverdacht) einmal täglich an die Verwaltung melden (Mail/Push nach Wahl).
Schedule::command('nachrichten:spamverdacht-melden')->dailyAt('08:05');

// Sammelbestellung: Zahlungserinnerung am Vortag des Bestelldatums, Hinweis
// am Bestelldatum, Löschfristen (docs/pflichtenheft-sammelbestellung.md).
Schedule::command('sammelbestellung:taeglich')->dailyAt('08:10');

// Rollen mit Ablaufdatum: Hinweise 30/7 Tage vorher, danach entfernen.
Schedule::command('rollen:ablauf')->dailyAt('08:15');

// Zuschussanträge: Unterlagen in die Nextcloud-Ablage (docs/pflichtenheft-zuschuesse.md, ZU-40).
Schedule::command('zuschuss:nextcloud')->dailyAt('03:20')->withoutOverlapping();
Schedule::command('zuschuss:juleica')->dailyAt('08:20');
Schedule::command('zuschuss:fristen')->dailyAt('08:25');

// Präventions-Nachweise: Erinnerungen 90/30 Tage vorher, nach einem Jahr löschen.
Schedule::command('praevention:erinnerungen')->dailyAt('08:30');

// Materialverleih: Rückgabe-Erinnerung, alte Reservierungen löschen.
Schedule::command('material:taeglich')->dailyAt('08:35');

// NaMi-Datenabgleich: Löschfristen (docs/pflichtenheft-nami-datenabgleich.md).
Schedule::command('datenabgleich:aufraeumen')->dailyAt('03:45');

// Kluft-Updates für eigenständige Installationen (docs/updates.md): im
// Backend angestoßene Installation jede Minute abholen, täglich nach neuen
// Versionen schauen und bei KLUFT_UPDATES=auto nachts installieren – nach
// dem Backup um 02:00.
Schedule::command('kluft:update --angefordert')->everyMinute()->withoutOverlapping(60)->when(fn () => Updater::aktiv() && Cache::has(Updater::CACHE_ANGEFORDERT));
Schedule::command('kluft:update --auto')->dailyAt('04:20')->withoutOverlapping(60)->when(fn () => Updater::aktiv());
