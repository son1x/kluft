<?php

namespace App\Console\Commands;

use App\Models\EventRegistration;
use App\Services\EventRegistrations;
use App\Support\AuditLogger;
use Illuminate\Console\Command;

/**
 * Löschfristen der Veranstaltungsanmeldungen, Pflichtenheft 9.3 und 11.4:
 * unbestätigte Anmeldungen nach 48 h, alle übrigen zum `purge_at`
 * (Standard 90 Tage nach Veranstaltungsende).
 */
class PurgeRegistrationsCommand extends Command
{
    protected $signature = 'registrations:purge {--unconfirmed : Nur unbestätigte Anmeldungen (älter als 48 h)}';

    protected $description = 'Löscht abgelaufene Veranstaltungsanmeldungen';

    public function handle(): int
    {
        $unconfirmed = EventRegistration::query()
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subHours(EventRegistrations::CONFIRM_HOURS))
            ->delete();

        $expired = $this->option('unconfirmed') ? 0 : EventRegistration::query()
            ->where('purge_at', '<=', now())
            ->delete();

        if ($unconfirmed + $expired > 0) {
            AuditLogger::log("registrations.purge:{$unconfirmed}+{$expired}");
        }

        $this->info("Gelöscht: {$unconfirmed} unbestätigt, {$expired} abgelaufen.");

        return self::SUCCESS;
    }
}
