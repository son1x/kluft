<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Mitglieder\MitgliederverzeichnisException;
use App\Services\Mitglieder\NamiVerzeichnis;
use App\Support\Datenabgleich;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * NaMi-Datenabgleich über die Warteschlange, je Lauf höchstens
 * config mitglieder.per_run Personen; danach stellt sich der Job für den
 * Rest wieder ein. Trägt wie BeitraegeAbrufen nur das Session-Cookie
 * (verschlüsselt) oder die ID der Person mit gespeichertem Zugang.
 */
class DatenabgleichAusfuehren implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    /** @param list<array<string, mixed>>|null $namiSession */
    public function __construct(public string $nummer, public ?array $namiSession = null, public ?int $namiUserId = null, public int $ab = 0) {}

    public function handle(): void
    {
        $nami = new NamiVerzeichnis;
        if ($this->namiUserId && ($nutzer = User::query()->find($this->namiUserId))) {
            $nami->mitZugang($nutzer);
        }
        if ($this->namiSession !== null) {
            $nami->mitSession($this->namiSession);
        }
        $max = max(1, (int) config('mitglieder.per_run', 30));
        try {
            $offen = Datenabgleich::abgleichen($nami, $this->nummer, $this->ab, $max);
        } catch (MitgliederverzeichnisException $e) {
            Datenabgleich::standSetzen('fehler', $e->getMessage());

            return;
        }
        if ($offen > 0 && $this->ab < 5000) {
            Datenabgleich::standSetzen('laeuft', "noch {$offen} Personen abzugleichen …");
            self::dispatch($this->nummer, $this->namiSession, $this->namiUserId, $this->ab + $max);

            return;
        }
        Datenabgleich::standSetzen('fertig', 'Abgleich abgeschlossen');
    }
}
