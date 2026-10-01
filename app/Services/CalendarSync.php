<?php

namespace App\Services;

use App\Models\Calendar;
use App\Models\CalendarEvent;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\Reader;

/**
 * Holt ICS-Kalender (Nextcloud-Freigabelinks) und legt die Termine als
 * Cache ab (Pflichtenheft 6.5). Schlägt ein Abruf fehl, bleibt der letzte
 * gültige Stand erhalten (F-CAL-02).
 */
class CalendarSync
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    /**
     * @return bool true bei Erfolg (auch wenn unverändert)
     */
    public function sync(Calendar $calendar): bool
    {
        try {
            $ics = $this->fetch($calendar->ics_url);
            $hash = hash('sha256', $ics);

            if ($hash !== $calendar->last_hash) {
                $events = $this->parse($ics);

                DB::transaction(function () use ($calendar, $events) {
                    $calendar->events()->delete();
                    foreach (array_chunk($events, 200) as $chunk) {
                        CalendarEvent::query()->insert(array_map(fn (array $e) => [...$e, 'calendar_id' => $calendar->id], $chunk));
                    }
                });
            }

            $calendar->forceFill(['last_synced_at' => now(), 'last_hash' => $hash, 'last_error' => null])->save();

            return true;
        } catch (\Throwable $e) {
            $calendar->forceFill(['last_error' => Str::limit($e->getMessage(), 250)])->save();

            return false;
        }
    }

    /**
     * SSRF-Schutz (F-CAL-07): nur HTTPS, nur freigegebene Hosts, keine
     * Weiterleitungen, Timeout 10 s, höchstens 5 MB.
     */
    public function fetch(string $url): string
    {
        self::assertAllowedUrl($url);

        $response = Http::timeout(10)
            ->withOptions([
                'allow_redirects' => false,
                'on_headers' => function ($response) {
                    if ((int) $response->getHeaderLine('Content-Length') > self::MAX_BYTES) {
                        throw new RuntimeException('Kalenderdatei größer als 5 MB.');
                    }
                },
            ])
            ->accept('text/calendar')
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("Abruf fehlgeschlagen (HTTP {$response->status()}).");
        }

        $body = $response->body();
        if (strlen($body) > self::MAX_BYTES) {
            throw new RuntimeException('Kalenderdatei größer als 5 MB.');
        }

        return $body;
    }

    public static function assertAllowedUrl(string $url): void
    {
        $parts = parse_url($url);
        $host = mb_strtolower($parts['host'] ?? '');

        if (($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['port'])) {
            throw new RuntimeException('Nur https-Adressen ohne Zugangsdaten/Port sind erlaubt.');
        }

        if (! in_array($host, self::allowedHosts(), true)) {
            throw new RuntimeException("Host {$host} ist nicht freigegeben (NEXTCLOUD_ALLOWED_HOSTS).");
        }
    }

    /**
     * @return list<string>
     */
    public static function allowedHosts(): array
    {
        return array_values(array_filter(array_map(
            fn (string $h) => mb_strtolower(trim($h)),
            explode(',', (string) config('services.nextcloud.allowed_hosts'))
        )));
    }

    /**
     * Expandiert Wiederholungen (RRULE/EXDATE) im Fenster von einem Monat
     * zurück bis 18 Monate voraus; Zeiten in Europe/Berlin. Nur Titel, Ort,
     * Beschreibung – keine Organizer/Teilnehmer (F-CAL-05).
     *
     * @return list<array<string, mixed>>
     */
    public function parse(string $ics): array
    {
        $tz = new DateTimeZone(config('app.timezone'));
        $from = CarbonImmutable::now($tz)->subMonth()->startOfDay();
        $to = CarbonImmutable::now($tz)->addMonths(18);

        $vcalendar = Reader::read($ics, Reader::OPTION_FORGIVING);
        $vcalendar = $vcalendar->expand($from, $to, $tz);

        $events = [];
        foreach ($vcalendar->VEVENT ?? [] as $vevent) {
            /** @var VEvent $vevent */
            if (! isset($vevent->DTSTART) || strtoupper((string) ($vevent->STATUS ?? '')) === 'CANCELLED') {
                continue;
            }

            $allDay = ! $vevent->DTSTART->hasTime();
            $start = CarbonImmutable::instance($vevent->DTSTART->getDateTime($tz))->setTimezone($tz);
            $end = isset($vevent->DTEND)
                ? CarbonImmutable::instance($vevent->DTEND->getDateTime($tz))->setTimezone($tz)
                : (isset($vevent->DURATION) ? $start->add($vevent->DURATION->getDateInterval()) : ($allDay ? $start->addDay() : null));

            $events[] = [
                'uid' => Str::limit((string) ($vevent->UID ?? Str::uuid()), 250, ''),
                'starts_at' => $start->format('Y-m-d H:i:s'),
                'ends_at' => $end?->format('Y-m-d H:i:s'),
                'all_day' => $allDay,
                'title' => Str::limit(trim((string) ($vevent->SUMMARY ?? 'Termin')), 250, '…'),
                'location' => filled((string) ($vevent->LOCATION ?? '')) ? Str::limit(trim((string) $vevent->LOCATION), 250, '…') : null,
                'description' => filled((string) ($vevent->DESCRIPTION ?? '')) ? self::stripEmails(Str::limit(trim((string) $vevent->DESCRIPTION), 5000, '…')) : null,
            ];
        }

        return $events;
    }

    /**
     * Auch in Freitext keine E-Mail-Adressen ausgeben (F-CAL-05).
     */
    public static function stripEmails(string $text): string
    {
        return preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[E-Mail entfernt]', $text);
    }
}
