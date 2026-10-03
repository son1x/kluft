<?php

namespace App\Console\Commands;

use App\Models\EventRegistration;
use App\Models\RegistrationDocument;
use App\Services\EventRegistrations;
use App\Support\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

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
        $unconfirmed = $this->purge(EventRegistration::query()
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subHours(EventRegistrations::CONFIRM_HOURS)));

        $expired = $this->option('unconfirmed') ? 0 : $this->purge(EventRegistration::query()
            ->where('purge_at', '<=', now()));

        if ($unconfirmed + $expired > 0) {
            AuditLogger::log("registrations.purge:{$unconfirmed}+{$expired}");
        }

        $this->info("Gelöscht: {$unconfirmed} unbestätigt, {$expired} abgelaufen.");

        return self::SUCCESS;
    }

    /**
     * Löscht samt hochgeladener Dokumente (Rechnungen, Bescheinigungen).
     *
     * @param  Builder<EventRegistration>  $query
     */
    private function purge(Builder $query): int
    {
        RegistrationDocument::purgeFor($query->clone()->pluck('id'));

        return $query->delete();
    }
}
