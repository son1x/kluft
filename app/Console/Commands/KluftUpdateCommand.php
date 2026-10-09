<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Update\Release;
use App\Services\Update\UpdateFehler;
use App\Services\Update\Updater;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Updates für eigenständige Installationen (docs/updates.md).
 *
 *   kluft:update                 nur prüfen und anzeigen
 *   kluft:update --installieren  neueste Version jetzt installieren
 *   kluft:update --angefordert   Installation, die im Backend angestoßen wurde (Cronjob, jede Minute)
 *   kluft:update --auto          nachts: installieren, wenn KLUFT_UPDATES=auto
 *   kluft:update --abschluss     Protokoll und Mails nach einem Update (ruft der Updater selbst auf)
 */
class KluftUpdateCommand extends Command
{
    protected $signature = 'kluft:update {--installieren} {--angefordert} {--auto} {--abschluss}';

    protected $description = 'Kluft-Updates prüfen und installieren';

    public function handle(Updater $updater): int
    {
        if ($this->option('abschluss')) {
            $updater->abschliessen();

            return self::SUCCESS;
        }
        if (! Updater::aktiv()) {
            $this->info('Updates über Kluft sind abgeschaltet (KLUFT_UPDATES=off); diese Installation wird anders aktualisiert.');

            return self::SUCCESS;
        }

        $anfrage = null;
        if ($this->option('angefordert')) {
            $anfrage = Cache::pull(Updater::CACHE_ANGEFORDERT);
            if (! is_array($anfrage)) {
                return self::SUCCESS;
            }
        }

        try {
            $release = $updater->pruefen();
        } catch (UpdateFehler $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $release instanceof Release) {
            $this->info('Kluft ist aktuell.');

            return self::SUCCESS;
        }
        $this->info("Verfügbar: Kluft {$release->version}.");

        $installieren = $this->option('installieren')
            || ($anfrage !== null && ($anfrage['version'] ?? null) === $release->version)
            || ($this->option('auto') && Updater::modus() === 'auto' && $release->auto);
        if (! $installieren) {
            if ($anfrage !== null) {
                $this->warn('Angefordert war Version '.($anfrage['version'] ?? '?').', verfügbar ist '.$release->version.' – bitte im Backend neu anstoßen.');
            }

            return self::SUCCESS;
        }

        $von = $anfrage !== null ? User::query()->find($anfrage['von'] ?? null) : null;
        try {
            foreach ($updater->installieren($release, $von) as $zeile) {
                $this->line($zeile);
            }
        } catch (UpdateFehler $e) {
            foreach ($updater->protokoll() as $zeile) {
                $this->line($zeile);
            }
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
