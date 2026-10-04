<?php

namespace App\Jobs;

use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Legt für alle aktiven Abonnent:innen eine Zustellung an und reiht je
 * Person einen Versand-Job ein (Tempo begrenzt der Rate-Limiter „whatsapp“).
 */
class DispatchWhatsAppCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public int $campaignId) {}

    public function handle(): void
    {
        $campaign = WhatsAppCampaign::query()->find($this->campaignId);
        if (! $campaign || $campaign->status !== 'sending') {
            return;
        }

        // Erst alle Zustellungen anlegen, dann versenden – sonst gilt die
        // Aussendung nach der ersten Nachricht schon als fertig.
        $new = [];
        WhatsAppSubscriber::query()->active()->select('id')->chunkById(200, function ($subscribers) use ($campaign, &$new) {
            foreach ($subscribers as $subscriber) {
                $delivery = $campaign->deliveries()->firstOrCreate(['subscriber_id' => $subscriber->id], ['status' => 'queued']);
                if ($delivery->wasRecentlyCreated) {
                    $new[] = $delivery->id;
                }
            }
        });
        $count = count($new);
        foreach ($new as $id) {
            SendWhatsAppCampaignMessage::dispatch($id);
        }

        $campaign->update(['recipients' => $campaign->deliveries()->count()]);
        if ($count === 0) {
            $campaign->refreshCounts();
        }
    }
}
