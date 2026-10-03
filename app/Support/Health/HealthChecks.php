<?php

namespace App\Support\Health;

use App\Models\Calendar;
use App\Models\IntegrationToken;
use App\Services\Backup\BackupService;
use App\Services\Instagram\InstagramClient;
use App\Support\Roadmap;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Betriebszustand für Statusseite und Monitoring (Pflichtenheft 10.2/10.3).
 * Liefert je Bereich „ok“, „warnung“, „fehler“ oder „aus“ (nicht
 * eingerichtet) mit kurzer Erklärung –
 * ohne Personendaten oder Geheimnisse, denn /status ist öffentlich.
 */
class HealthChecks
{
    public const OK = 'ok';

    public const WARN = 'warnung';

    public const FAIL = 'fehler';

    /** Bewusst (noch) nicht eingerichtet – weder in Ordnung noch Störung. */
    public const OFF = 'aus';

    /**
     * @return list<array{key: string, label: string, status: string, message: string}>
     */
    public function run(): array
    {
        $checks = [
            'datenbank' => ['Datenbank', fn () => $this->database()],
            'cronjob' => ['Geplante Aufgaben (Cronjob)', fn () => $this->scheduler()],
            'mails' => ['E-Mail-Versand', fn () => $this->mailQueue()],
            'backups' => ['Backups', fn () => $this->backups()],
            'kalender' => ['Kalender-Abgleich (Nextcloud)', fn () => $this->calendars()],
            'instagram' => ['Instagram-Anbindung', fn () => $this->instagram()],
        ];

        $results = [];
        foreach ($checks as $key => [$label, $check]) {
            try {
                [$status, $message] = $check();
            } catch (Throwable $e) {
                report($e);
                [$status, $message] = [self::FAIL, 'Prüfung fehlgeschlagen.'];
            }
            $results[] = ['key' => $key, 'label' => $label, 'status' => $status, 'message' => $message];
        }

        return $results;
    }

    /**
     * @param  list<array{status: string}>  $results
     */
    public static function overall(array $results): string
    {
        // „aus“ zählt nicht mit: Nicht Eingerichtetes ist keine Störung.
        $statuses = array_column($results, 'status');

        return in_array(self::FAIL, $statuses, true) ? self::FAIL : (in_array(self::WARN, $statuses, true) ? self::WARN : self::OK);
    }

    /** @return array{0: string, 1: string} */
    private function database(): array
    {
        DB::select('select 1');

        return [self::OK, 'Erreichbar.'];
    }

    /** @return array{0: string, 1: string} */
    private function scheduler(): array
    {
        $last = Cache::get(Roadmap::HEARTBEAT_KEY);
        if ($last === null) {
            return [self::FAIL, 'Noch kein Lebenszeichen – läuft der Cronjob „schedule:run“?'];
        }
        $minutes = (int) floor((now()->timestamp - (int) $last) / 60);

        return $minutes < 10
            ? [self::OK, 'Läuft (zuletzt vor '.max(0, $minutes).' Min.).']
            : [self::FAIL, "Seit {$minutes} Minuten kein Lebenszeichen. Ohne Cronjob gehen keine Mails raus und es laufen keine Backups."];
    }

    /** @return array{0: string, 1: string} */
    private function mailQueue(): array
    {
        $pending = DB::table('jobs')->count();
        $oldest = DB::table('jobs')->min('created_at');
        $failed = DB::table('failed_jobs')->count();

        if ($pending > 0 && $oldest !== null && $oldest < now()->subMinutes(15)->timestamp) {
            return [self::FAIL, "{$pending} Mail(s) warten seit über 15 Minuten."];
        }
        if ($failed > 0) {
            return [self::WARN, "{$failed} Mail(s) fehlgeschlagen – im Backend unter „E-Mail-Versand“ prüfen."];
        }

        return [self::OK, $pending > 0 ? "{$pending} Mail(s) werden gerade verschickt." : 'Keine wartenden oder fehlgeschlagenen Mails.'];
    }

    /** @return array{0: string, 1: string} */
    private function backups(): array
    {
        if (mb_strlen((string) config('dpsg.backup.password')) < 16) {
            return [self::WARN, 'Noch nicht eingeschaltet (Backup-Passwort fehlt).'];
        }
        $run = Cache::get(BackupService::LAST_RUN_KEY);
        if ($run === null) {
            return [self::WARN, 'Eingeschaltet, aber noch kein Backup gelaufen.'];
        }
        $at = Carbon::parse($run['at']);
        if ($at->lt(now()->subHours(36))) {
            return [self::FAIL, 'Letztes Backup '.$at->diffForHumans().' – das tägliche Backup fehlt.'];
        }
        $verify = Cache::get(BackupService::LAST_VERIFY_KEY);
        if ($verify !== null && ! ($verify['ok'] ?? false)) {
            return [self::FAIL, 'Letzte Wiederherstellungsprobe fehlgeschlagen.'];
        }

        return [self::OK, 'Letztes Backup '.$at->timezone(config('app.timezone'))->format('d.m.Y H:i').' Uhr.'];
    }

    /** @return array{0: string, 1: string} */
    private function calendars(): array
    {
        $calendars = Calendar::query()->where('active', true)->get(['label', 'last_synced_at', 'last_error']);
        if ($calendars->isEmpty()) {
            return [self::OFF, 'Keine Kalender eingebunden.'];
        }
        $broken = $calendars->filter(fn ($c) => $c->last_error !== null || $c->last_synced_at === null || $c->last_synced_at->lt(now()->subHours(2)));

        return $broken->isEmpty()
            ? [self::OK, $calendars->count().' Kalender aktuell.']
            : [self::WARN, $broken->count().' von '.$calendars->count().' Kalender(n) nicht aktuell: '.$broken->pluck('label')->implode(', ').'.'];
    }

    /** @return array{0: string, 1: string} */
    private function instagram(): array
    {
        $token = IntegrationToken::for(InstagramClient::PROVIDER);
        if (! $token) {
            return [self::OFF, 'Noch nicht eingerichtet – es fehlt das Instagram-Konto (siehe Roadmap).'];
        }
        if ($token->expires_at !== null && $token->expires_at->lt(now()->addDays(7))) {
            return [self::WARN, 'Zugang läuft am '.$token->expires_at->format('d.m.Y').' ab.'];
        }

        return $token->last_error ? [self::WARN, 'Letzte Erneuerung mit Fehler.'] : [self::OK, 'Verbunden.'];
    }
}
