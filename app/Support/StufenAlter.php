<?php

namespace App\Support;

use App\Models\StageAge;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Altersangaben der Stufen. Standard ist die Angabe der DPSG (config
 * dpsg.stages); jede Gruppierung darf jede Grenze um bis zu SPIELRAUM Jahre
 * verschieben, z. B. Wölflinge 7 bis 10 statt 6 bis 10. Festgelegt unter
 * „Verwaltung → Stufen“, gespeichert in stage_ages, beim Start in die
 * Konfiguration übernommen (from, to und der Text age).
 */
class StufenAlter
{
    public const SPIELRAUM = 1;

    private const CACHE_KEY = 'stufen.alter';

    /**
     * DPSG-Standard je Stufe (vor dem Überstimmen).
     *
     * @return array{from: int, to: int}
     */
    public static function standard(string $stage): array
    {
        // Beim Überstimmen merkt sich die Konfiguration den Standard unter „standard“.
        $s = (array) config("dpsg.stages.{$stage}", []);
        $s = (array) ($s['standard'] ?? $s);

        return ['from' => (int) ($s['from'] ?? 0), 'to' => (int) ($s['to'] ?? 0)];
    }

    /**
     * Erlaubte Werte für eine Grenze.
     *
     * @param  'from'|'to'  $grenze
     * @return list<int>
     */
    public static function erlaubt(string $stage, string $grenze): array
    {
        $wert = self::standard($stage)[$grenze];

        return range(max(0, $wert - self::SPIELRAUM), $wert + self::SPIELRAUM);
    }

    public static function gueltig(string $stage, int $from, int $to): bool
    {
        return in_array($from, self::erlaubt($stage, 'from'), true)
            && in_array($to, self::erlaubt($stage, 'to'), true)
            && $from < $to;
    }

    public static function text(int $from, int $to): string
    {
        return "{$from} bis {$to} Jahre";
    }

    /** Festgelegte Angaben in die Konfiguration übernehmen (beim Start und nach dem Speichern). */
    public static function anwenden(): void
    {
        foreach (array_keys((array) config('dpsg.stages')) as $stage) {
            $standard = self::standard($stage);
            [$from, $to] = [$standard['from'], $standard['to']];
            $eigen = self::festgelegt()[$stage] ?? null;
            if ($eigen && self::gueltig($stage, $eigen['from'], $eigen['to'])) {
                [$from, $to] = [$eigen['from'], $eigen['to']];
            }
            $aktuell = (array) config("dpsg.stages.{$stage}");
            if ($aktuell['from'] === $from && $aktuell['to'] === $to) {
                continue;
            }
            config(["dpsg.stages.{$stage}" => ['from' => $from, 'to' => $to, 'age' => self::text($from, $to), 'standard' => $standard] + $aktuell]);
        }
    }

    /**
     * @return array<string, array{from: int, to: int}>
     */
    public static function festgelegt(): array
    {
        try {
            $werte = Cache::get(self::CACHE_KEY);
            if (is_array($werte)) {
                return $werte;
            }
            if (! Schema::hasTable('stage_ages')) {
                return [];
            }
            $werte = StageAge::query()->get()
                ->mapWithKeys(fn (StageAge $a) => [$a->stage => ['from' => $a->from, 'to' => $a->to]])->all();
            Cache::forever(self::CACHE_KEY, $werte);

            return $werte;
        } catch (Throwable) {
            return [];
        }
    }

    /** Festlegen; gleich dem Standard entfernt den Eintrag. */
    public static function setzen(string $stage, int $from, int $to, ?int $userId = null): void
    {
        if (! self::gueltig($stage, $from, $to)) {
            throw new \InvalidArgumentException("Alter {$from}–{$to} für {$stage} außerhalb des Rahmens.");
        }
        if (['from' => $from, 'to' => $to] === self::standard($stage)) {
            StageAge::query()->whereKey($stage)->delete();
        } else {
            StageAge::query()->updateOrCreate(['stage' => $stage], ['from' => $from, 'to' => $to, 'updated_by' => $userId]);
        }
        Cache::forget(self::CACHE_KEY);
        self::anwenden();
    }
}
