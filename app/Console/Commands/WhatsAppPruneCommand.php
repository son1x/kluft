<?php

namespace App\Console\Commands;

use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppDelivery;
use App\Models\WhatsAppSubscriber;
use App\Models\WhatsAppSuppression;
use Illuminate\Console\Command;

/**
 * Löschfristen des WhatsApp-Newsletters (docs/whatsapp_newsletter.md,
 * F-WA-OPS-01): unbestätigte Anmeldungen nach 48 h, Zustellungen 30 Tage
 * nach Abschluss, Sperrliste nach 12 Monaten.
 */
class WhatsAppPruneCommand extends Command
{
    protected $signature = 'whatsapp:prune';

    protected $description = 'Löscht abgelaufene WhatsApp-Daten (unbestätigte Anmeldungen, Zustellungen, Sperrliste)';

    public function handle(): int
    {
        $r = config('whatsapp.retention');

        $pending = WhatsAppSubscriber::query()->where('status', 'pending')
            ->where('requested_at', '<', now()->subHours($r['pending_hours']))->delete();

        $finished = WhatsAppCampaign::query()->whereIn('status', ['sent', 'cancelled'])
            ->where('finished_at', '<', now()->subDays($r['deliveries_days']))->pluck('id');
        $deliveries = WhatsAppDelivery::query()->whereIn('campaign_id', $finished)->delete();

        $suppressions = WhatsAppSuppression::query()->where('created_at', '<', now()->subMonths($r['suppression_months']))->delete();

        $this->info("Gelöscht: {$pending} unbestätigte Anmeldungen, {$deliveries} Zustellungen, {$suppressions} Sperrlisten-Einträge.");

        return self::SUCCESS;
    }
}
