<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

/**
 * Audit-Log nach 12 Monaten bereinigen (Pflichtenheft 8.2 `audit_logs`,
 * 11.4 „Audit-Bereinigung“).
 */
class PruneAuditLogCommand extends Command
{
    public const MONTHS = 12;

    protected $signature = 'audit:prune';

    protected $description = 'Löscht Audit-Log-Einträge, die älter als 12 Monate sind';

    public function handle(): int
    {
        $deleted = 0;
        do {
            // In Blöcken, damit große Tabellen die Datenbank nicht blockieren.
            $ids = AuditLog::query()->where('created_at', '<', now()->subMonths(self::MONTHS))->limit(1000)->pluck('id');
            $deleted += AuditLog::query()->whereIn('id', $ids)->delete();
        } while ($ids->isNotEmpty());

        $this->info("Gelöscht: {$deleted} Audit-Einträge älter als ".self::MONTHS.' Monate.');

        return self::SUCCESS;
    }
}
