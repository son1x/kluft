<?php

namespace App\Jobs;

use App\Models\WhatsAppDelivery;
use App\Services\WhatsApp\WhatsAppCampaigns;
use App\Services\WhatsApp\WhatsAppClient;
use App\Services\WhatsApp\WhatsAppException;
use App\Services\WhatsApp\WhatsAppInbound;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * Eine Newsletter-Nachricht an eine Person. Limits von Meta → später erneut;
 * Konto-, Token- oder Spam-Fehler → ganze Aussendung anhalten.
 */
class SendWhatsAppCampaignMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 10;

    public int $maxExceptions = 3;

    public function __construct(public int $deliveryId) {}

    /** @return list<object> */
    public function middleware(): array
    {
        return [new RateLimited('whatsapp')];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addDays(2);
    }

    public function handle(WhatsAppClient $client, WhatsAppCampaigns $campaigns, WhatsAppInbound $inbound): void
    {
        $delivery = WhatsAppDelivery::query()->with(['campaign.template', 'subscriber'])->find($this->deliveryId);
        if (! $delivery || $delivery->status !== 'queued') {
            return;
        }

        $campaign = $delivery->campaign;
        if ($campaign->status === 'paused') {
            $this->release(300);

            return;
        }
        $subscriber = $delivery->subscriber;
        if ($campaign->status !== 'sending' || ! $subscriber || $subscriber->status !== 'active' || ! $campaign->template) {
            $delivery->update(['status' => 'skipped']);
            $campaign->refreshCounts();

            return;
        }

        try {
            $wamid = $client->sendTemplate($subscriber->phone, $campaign->template, $campaign->variables['body'] ?? [], $campaign->variables['url'] ?? null);
            $delivery->update($wamid === null ? ['status' => 'skipped'] : ['status' => 'sent', 'wamid' => $wamid]);
        } catch (WhatsAppException $e) {
            if ($e->retryable()) {
                $this->release(min(900, 30 * 2 ** ($this->attempts() - 1)));

                return;
            }
            if ($e->fatal()) {
                $campaigns->pause($campaign, null, $e->getMessage().' (Code '.$e->metaCode.')');
                $this->release(300);

                return;
            }
            $delivery->update(['status' => 'failed', 'error_code' => $e->metaCode]);
            $inbound->recipientFailed($subscriber, $e->metaCode);
        }

        $campaign->refreshCounts();
    }
}
