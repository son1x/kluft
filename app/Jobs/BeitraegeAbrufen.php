<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Beitraege\BeitragsAbruf;
use App\Services\Mitglieder\MitgliederverzeichnisException;
use App\Services\Mitglieder\NamiVerzeichnis;
use App\Support\Beitraege;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Mitglieder für die Beitragskasse aus NaMi holen und danach die Beiträge
 * des Halbjahres anlegen – über die Warteschlange, je Lauf höchstens
 * config mitglieder.per_run Detailabfragen, dann stellt sich der Job für
 * den Rest wieder ein. Wie CheckEventMembers trägt er bei persönlicher
 * Anmeldung nur das Session-Cookie (verschlüsselt) oder die ID der Person
 * mit gespeichertem Zugang, nie das Passwort.
 */
class BeitraegeAbrufen implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    /**
     * @param  list<array<string, mixed>>|null  $namiSession
     */
    public function __construct(public string $nummer, public string $halbjahr, public ?array $namiSession = null, public ?int $namiUserId = null, public int $lauf = 1) {}

    public function handle(): void
    {
        $nami = new NamiVerzeichnis;
        if ($this->namiUserId && ($nutzer = User::query()->find($this->namiUserId))) {
            $nami->mitZugang($nutzer);
        }
        if ($this->namiSession !== null) {
            $nami->mitSession($this->namiSession);
        }
        $abruf = new BeitragsAbruf($nami);

        try {
            $ergebnis = $abruf->abrufen($this->nummer, max(1, (int) config('mitglieder.per_run', 30)));
        } catch (MitgliederverzeichnisException $e) {
            Beitraege::abrufStand('fehler', $e->getMessage());

            return;
        }

        if ($ergebnis['offen'] > 0 && $this->lauf < 50) {
            Beitraege::abrufStand('laeuft', "{$ergebnis['mitglieder']} Mitglieder gefunden, noch {$ergebnis['offen']} abzurufen …");
            self::dispatch($this->nummer, $this->halbjahr, $this->namiSession, $this->namiUserId, $this->lauf + 1);

            return;
        }

        $beitraege = $abruf->halbjahrAnlegen($this->halbjahr);
        Beitraege::abrufStand('fertig', "{$ergebnis['mitglieder']} Mitglieder aus NaMi"
            .($ergebnis['ausgetreten'] ? ", {$ergebnis['ausgetreten']} nicht mehr gelistet" : '')
            .'; '.Beitraege::label($this->halbjahr).": {$beitraege['neu']} Beiträge neu, {$beitraege['angepasst']} angepasst."
            // Zuletzt, weil der Text bei Bedarf hinten gekürzt wird; der nächste Abruf versucht es erneut.
            .($ergebnis['ohne_details'] ? " {$ergebnis['ohne_details']} ohne Beitragsart – {$abruf->detailFehler}" : ''));
    }
}
