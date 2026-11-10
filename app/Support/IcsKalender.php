<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Kalender-Abo (ICS, RFC 5545) aus den Einträgen des öffentlichen Kalenders:
 * Termine aus der Nextcloud und Veranstaltungen mit Anmeldung
 * (docs/plan-ehrenamt.md Nr. 2). Nur öffentliche Angaben.
 */
class IcsKalender
{
    /** @param  Collection<int, CalendarItem>  $eintraege */
    public static function erzeugen(Collection $eintraege, string $name): string
    {
        $host = (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'kluft');
        $zeilen = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Kluft//'.config('dpsg.site.name').'//DE',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.self::text($name),
            'X-WR-TIMEZONE:Europe/Berlin',
            // Kalender-Apps fragen damit etwa alle sechs Stunden neu nach.
            'REFRESH-INTERVAL;VALUE=DURATION:PT6H',
            'X-PUBLISHED-TTL:PT6H',
        ];
        $jetzt = now()->utc()->format('Ymd\THis\Z');

        foreach ($eintraege as $e) {
            $uid = sha1(($e->url ?? $e->uid ?? $e->title).'|'.$e->starts_at->toIso8601String()).'@'.$host;
            $zeilen[] = 'BEGIN:VEVENT';
            $zeilen[] = 'UID:'.$uid;
            $zeilen[] = 'DTSTAMP:'.$jetzt;
            if ($e->all_day) {
                $ende = $e->ends_at && $e->ends_at->greaterThan($e->starts_at) ? $e->ends_at : $e->starts_at->copy()->addDay();
                $zeilen[] = 'DTSTART;VALUE=DATE:'.$e->starts_at->format('Ymd');
                $zeilen[] = 'DTEND;VALUE=DATE:'.$ende->format('Ymd');
            } else {
                $zeilen[] = 'DTSTART:'.self::utc($e->starts_at);
                $zeilen[] = 'DTEND:'.self::utc($e->ends_at && $e->ends_at->greaterThan($e->starts_at) ? $e->ends_at : $e->starts_at->copy()->addHour());
            }
            $zeilen[] = 'SUMMARY:'.self::text(($e->cancelled ? 'Abgesagt: ' : '').$e->title);
            if (filled($e->location)) {
                $zeilen[] = 'LOCATION:'.self::text((string) $e->location);
            }
            $beschreibung = trim(collect([strip_tags((string) $e->description), $e->badge, $e->url])->filter()->implode("\n\n"));
            if ($beschreibung !== '') {
                $zeilen[] = 'DESCRIPTION:'.self::text($beschreibung);
            }
            if ($e->url) {
                $zeilen[] = 'URL:'.$e->url;
            }
            $zeilen[] = 'CATEGORIES:'.self::text($e->sourceLabel);
            if ($e->cancelled) {
                $zeilen[] = 'STATUS:CANCELLED';
            }
            $zeilen[] = 'END:VEVENT';
        }
        $zeilen[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::falten(...), $zeilen))."\r\n";
    }

    private static function utc(CarbonInterface $zeit): string
    {
        return $zeit->copy()->utc()->format('Ymd\THis\Z');
    }

    /** Text nach RFC 5545 maskieren (Backslash, Komma, Semikolon, Zeilenumbruch). */
    private static function text(string $wert): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\;', '\,', '\n', '\n', ''], $wert);
    }

    /** Zeilen über 75 Byte falten (Fortsetzung mit Leerzeichen), ohne UTF-8-Zeichen zu zerschneiden. */
    private static function falten(string $zeile): string
    {
        if (strlen($zeile) <= 75) {
            return $zeile;
        }
        $teile = [];
        $aktuell = '';
        foreach (mb_str_split($zeile) as $zeichen) {
            if (strlen($aktuell) + strlen($zeichen) > ($teile === [] ? 75 : 74)) {
                $teile[] = $aktuell;
                $aktuell = '';
            }
            $aktuell .= $zeichen;
        }
        $teile[] = $aktuell;

        return implode("\r\n ", $teile);
    }
}
