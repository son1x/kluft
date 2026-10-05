<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\Mitglieder\MitgliederAbgleich;
use App\Services\Mitglieder\MitgliederverzeichnisException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Mitgliederabgleich einer Veranstaltung über die Warteschlange (der Cron
 * startet sie jede Minute). Je Lauf höchstens config mitglieder.per_run
 * Abfragen, danach stellt sich der Job für den Rest wieder ein.
 */
class CheckEventMembers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [120, 600];

    /**
     * @param  bool  $alle  auch schon geprüfte (nicht manuell entschiedene) Anmeldungen neu prüfen
     * @param  list<int>|null  $nurIds  nur diese Anmeldungen (z. B. direkt nach der Bestätigung)
     */
    public function __construct(public int $eventId, public bool $alle = false, public ?array $nurIds = null, public ?int $seit = null) {}

    public function handle(MitgliederAbgleich $abgleich): void
    {
        $event = Event::find($this->eventId);
        if (! $event || ! $event->member_check || ! MitgliederAbgleich::eingerichtet()) {
            return;
        }

        $seit = $this->seit ?? now()->timestamp;
        $offen = $event->registrations()
            ->whereIn('status', ['confirmed', 'waitlist'])
            ->where('member_manual', false)
            ->when($this->nurIds !== null, fn ($q) => $q->whereIn('id', $this->nurIds))
            ->where(fn ($q) => $this->alle
                ? $q->whereNull('member_checked_at')->orWhere('member_checked_at', '<', date('Y-m-d H:i:s', $seit))
                : $q->whereNull('member_status'))
            ->orderBy('id');

        $limit = max(1, (int) config('mitglieder.per_run', 30));
        $ergebnisse = [];
        foreach ((clone $offen)->limit($limit)->get() as $registration) {
            try {
                $status = $abgleich->pruefe($registration);
                $ergebnisse[$status] = ($ergebnisse[$status] ?? 0) + 1;
            } catch (MitgliederverzeichnisException $e) {
                Log::warning('Mitgliederabgleich: '.$e->getMessage(), ['event' => $event->id]);
                $this->release($this->backoff[min($this->attempts() - 1, count($this->backoff) - 1)]);

                return;
            }
        }

        Log::info('Mitgliederabgleich', ['event' => $event->id, 'ergebnisse' => $ergebnisse]);

        if ((clone $offen)->exists()) {
            self::dispatch($this->eventId, $this->alle, $this->nurIds, $seit);
        }
    }
}
