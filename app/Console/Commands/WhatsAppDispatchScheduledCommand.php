<?php

namespace App\Console\Commands;

use App\Models\WhatsAppCampaign;
use App\Services\WhatsApp\WhatsAppCampaigns;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * Startet freigegebene, geplante Aussendungen, sobald ihr Zeitpunkt da ist.
 */
class WhatsAppDispatchScheduledCommand extends Command
{
    protected $signature = 'whatsapp:dispatch-scheduled';

    protected $description = 'Startet fällige geplante WhatsApp-Aussendungen';

    public function handle(WhatsAppCampaigns $campaigns): int
    {
        WhatsAppCampaign::query()->where('status', 'scheduled')->where('scheduled_at', '<=', now())->get()
            ->each(function (WhatsAppCampaign $campaign) use ($campaigns) {
                try {
                    $campaigns->start($campaign);
                    $this->info('Gestartet: '.$campaign->title);
                } catch (ValidationException $e) {
                    $campaigns->pause($campaign, null, collect($e->errors())->flatten()->first());
                }
            });

        return self::SUCCESS;
    }
}
