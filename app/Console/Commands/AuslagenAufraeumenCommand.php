<?php

namespace App\Console\Commands;

use App\Models\Auslage;
use App\Support\AuditLogger;
use App\Support\Auslagen;
use Illuminate\Console\Command;

/**
 * Löschfristen der Auslagen: ausgezahlte samt Belegen nach 10 Jahren (zum
 * Jahresende, Aufbewahrung nach Steuerrecht), abgelehnte nach einem Jahr.
 */
class AuslagenAufraeumenCommand extends Command
{
    protected $signature = 'auslagen:aufraeumen';

    protected $description = 'Auslagen nach Ablauf der Aufbewahrungsfrist löschen';

    public function handle(): int
    {
        $grenze = today()->startOfYear()->subYears(Auslagen::AUFBEWAHRUNG_JAHRE);
        $alt = Auslage::query()
            ->where(fn ($q) => $q->where('status', 'ausgezahlt')->whereDate('ausgezahlt_am', '<', $grenze))
            ->orWhere(fn ($q) => $q->where('status', 'abgelehnt')->where('updated_at', '<', now()->subMonths(Auslagen::ABGELEHNT_MONATE)))
            ->get();
        $alt->each->delete();
        if ($alt->isNotEmpty()) {
            AuditLogger::log('auslagen.geloescht:'.$alt->count());
        }
        $this->info($alt->count().' Auslagen gelöscht.');

        return self::SUCCESS;
    }
}
