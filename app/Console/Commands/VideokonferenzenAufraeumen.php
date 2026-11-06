<?php

namespace App\Console\Commands;

use App\Models\Videokonferenz;
use Illuminate\Console\Command;

/**
 * Löscht die Einladungsadressen 7 Tage nach einer Videokonferenz und
 * Konferenzen, die länger als 12 Monate zurückliegen (docs/videokonferenzen.md).
 */
class VideokonferenzenAufraeumen extends Command
{
    protected $signature = 'videokonferenzen:aufraeumen';

    protected $description = 'Einladungsadressen vergangener Videokonferenzen löschen';

    public function handle(): int
    {
        $adressen = 0;
        Videokonferenz::query()->where('beginn', '<', now()->subDays(7))
            ->where(fn ($q) => $q->whereNotNull('moderation_emails')->orWhereNotNull('teilnehmende_emails'))
            ->each(function (Videokonferenz $k) use (&$adressen) {
                $k->forceFill(['moderation_emails' => null, 'teilnehmende_emails' => null])->save();
                $adressen++;
            });
        $alt = Videokonferenz::query()->where('beginn', '<', now()->subMonths(12))->delete();

        $this->info("Adressen gelöscht bei {$adressen} Konferenz(en), {$alt} alte Konferenz(en) entfernt.");

        return self::SUCCESS;
    }
}
