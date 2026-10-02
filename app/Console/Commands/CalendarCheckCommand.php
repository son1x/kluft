<?php

namespace App\Console\Commands;

use App\Models\Calendar;
use App\Services\CalendarSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Prüft die Nextcloud-Kalender-Konfiguration auf dem Server: Ist
 * NEXTCLOUD_ALLOWED_HOSTS gesetzt und richtig geschrieben (nur Hostname),
 * ist der Host erreichbar, passen die eingetragenen Kalender dazu? Gibt
 * keine Kalender-Links aus (die wirken wie Zugangsschlüssel).
 */
class CalendarCheckCommand extends Command
{
    protected $signature = 'calendar:check';

    protected $description = 'Nextcloud-Kalender-Konfiguration prüfen (ohne Geheimnisse auszugeben)';

    public function handle(): int
    {
        $raw = (string) config('services.nextcloud.allowed_hosts');
        $problems = 0;

        if (trim($raw) === '') {
            $this->error('NEXTCLOUD_ALLOWED_HOSTS ist leer – GitHub-Secret fehlt oder ist leer.');

            return self::FAILURE;
        }

        $hosts = CalendarSync::allowedHosts();
        $this->info('NEXTCLOUD_ALLOWED_HOSTS ist gesetzt: '.count($hosts).' Host(s).');

        foreach ($hosts as $i => $host) {
            $label = 'Host '.($i + 1).' ('.$this->mask($host).')';
            $issues = [];
            if (preg_match('#^[a-z]+://#', $host)) {
                $issues[] = 'enthält „https://“ – bitte nur den Hostnamen eintragen';
            }
            if (str_contains($host, '/')) {
                $issues[] = 'enthält einen Pfad oder Schrägstrich – nur den Hostnamen eintragen';
            }
            if (str_contains($host, ':') && ! str_contains($host, '://')) {
                $issues[] = 'enthält einen Port – Ports sind nicht erlaubt';
            }
            if (preg_match('/\s/', $host)) {
                $issues[] = 'enthält Leerzeichen';
            }
            if ($issues) {
                $problems++;
                $this->error("{$label}: ".implode('; ', $issues));

                continue;
            }

            $dns = gethostbynamel($host);
            if (! $dns) {
                $problems++;
                $this->error("{$label}: Name lässt sich nicht auflösen (Tippfehler?).");

                continue;
            }

            try {
                $status = Http::timeout(10)->withOptions(['allow_redirects' => false])->get("https://{$host}/status.php");
                $json = $status->json();
                $isNextcloud = is_array($json) && array_key_exists('installed', $json);
                $this->line("{$label}: erreichbar (HTTPS {$status->status()})".($isNextcloud ? ', Nextcloud erkannt'.(isset($json['versionstring']) ? ' (Version '.$json['versionstring'].')' : '') : ', aber keine Nextcloud-Statusseite gefunden'));
            } catch (Throwable $e) {
                $problems++;
                $this->error("{$label}: nicht per HTTPS erreichbar – ".$e->getMessage());
            }
        }

        $calendars = Calendar::query()->orderBy('label')->get();
        if ($calendars->isEmpty()) {
            $this->warn('Im Backend ist noch kein Kalender eingetragen (Backend → Kalender).');
        }

        foreach ($calendars as $calendar) {
            $host = mb_strtolower((string) parse_url((string) $calendar->ics_url, PHP_URL_HOST));
            $allowed = in_array($host, $hosts, true);
            $state = $calendar->active ? 'aktiv' : 'inaktiv';
            $sync = $calendar->last_synced_at ? 'zuletzt abgerufen '.$calendar->last_synced_at->timezone('Europe/Berlin')->format('d.m.Y H:i') : 'noch nie abgerufen';
            $line = "Kalender „{$calendar->label}“ ({$state}): Host ".($allowed ? 'freigegeben' : 'NICHT in NEXTCLOUD_ALLOWED_HOSTS')."; {$sync}";
            if ($calendar->last_error) {
                $line .= '; letzter Fehler: '.$calendar->last_error;
            }
            ($allowed && ! $calendar->last_error) ? $this->line($line) : $this->warn($line);
            $problems += $allowed ? 0 : 1;
        }

        $this->newLine();
        $problems === 0 ? $this->info('Keine Probleme gefunden.') : $this->error("{$problems} Problem(e) gefunden.");

        return $problems === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Hostname teilweise maskieren, falls das Protokoll öffentlich ist.
     */
    private function mask(string $host): string
    {
        $parts = explode('.', $host);

        return count($parts) >= 2
            ? mb_substr($parts[0], 0, 2).str_repeat('•', max(1, mb_strlen($parts[0]) - 2)).'.'.implode('.', array_slice($parts, 1))
            : mb_substr($host, 0, 2).'…';
    }
}
