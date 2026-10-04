<?php

namespace App\Services\WhatsApp;

use App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource;
use App\Jobs\DispatchWhatsAppCampaign;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppSubscriber;
use App\Support\AuditLogger;
use App\Support\WhatsAppAccess;
use Illuminate\Validation\ValidationException;

/**
 * Ablauf einer Aussendung: Entwurf → zur Freigabe → freigegeben → (geplant)
 * → Versand → versendet. Jeder Schritt im Audit-Log.
 */
class WhatsAppCampaigns
{
    public function __construct(private WhatsAppTeam $team) {}

    public function recipients(): int
    {
        return WhatsAppSubscriber::query()->active()->count();
    }

    public function estimate(int $recipients): float
    {
        return round($recipients * (float) config('whatsapp.price_marketing_eur'), 2);
    }

    public function submit(WhatsAppCampaign $campaign, User $user): void
    {
        $this->assertSendable($campaign);
        $campaign->update(['status' => 'review']);
        AuditLogger::log('whatsapp.aussendung.zur_freigabe', $user, 'whatsapp_campaign', $campaign->id);

        $this->team->notifyApprovers(
            'Aussendung wartet auf Freigabe',
            "„{$campaign->title}“ an voraussichtlich {$this->recipients()} Abonnent:innen.",
            WhatsAppCampaignResource::getUrl('edit', ['record' => $campaign], panel: 'admin'),
            except: $user,
        );
    }

    public function approve(WhatsAppCampaign $campaign, User $user): void
    {
        if (! WhatsAppAccess::manage($user)) {
            throw ValidationException::withMessages(['status' => 'Du darfst Aussendungen nicht freigeben.']);
        }
        if (config('whatsapp.four_eyes') && $campaign->created_by === $user->id) {
            throw ValidationException::withMessages(['status' => 'Vier-Augen-Prinzip: Freigeben muss eine andere Person als die, die die Aussendung erstellt hat.']);
        }
        $this->assertSendable($campaign);

        $recipients = $this->recipients();
        $campaign->update([
            'status' => $campaign->scheduled_at?->isFuture() ? 'scheduled' : 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'recipients' => $recipients,
            'estimated_cost_eur' => $this->estimate($recipients),
        ]);
        AuditLogger::log('whatsapp.aussendung.freigegeben', $user, 'whatsapp_campaign', $campaign->id);
    }

    /** Versand starten (sofort oder durch den Zeitplan). */
    public function start(WhatsAppCampaign $campaign, ?User $user = null): void
    {
        if (! in_array($campaign->status, ['approved', 'scheduled'], true)) {
            throw ValidationException::withMessages(['status' => 'Nur freigegebene Aussendungen können versendet werden.']);
        }
        $this->assertSendable($campaign);

        $campaign->update(['status' => 'sending', 'started_at' => now(), 'last_error' => null]);
        AuditLogger::log('whatsapp.aussendung.gestartet', $user, 'whatsapp_campaign', $campaign->id);
        DispatchWhatsAppCampaign::dispatch($campaign->id);
    }

    public function pause(WhatsAppCampaign $campaign, ?User $user, ?string $reason = null): void
    {
        $campaign->update(['status' => 'paused', 'last_error' => $reason]);
        AuditLogger::log('whatsapp.aussendung.angehalten', $user, 'whatsapp_campaign', $campaign->id);

        if ($reason !== null) {
            $this->team->alert('Versand angehalten', "„{$campaign->title}“ wurde angehalten: {$reason}");
        }
    }

    public function resume(WhatsAppCampaign $campaign, User $user): void
    {
        $campaign->update(['status' => 'sending', 'last_error' => null]);
        AuditLogger::log('whatsapp.aussendung.fortgesetzt', $user, 'whatsapp_campaign', $campaign->id);
    }

    public function cancel(WhatsAppCampaign $campaign, User $user): void
    {
        $campaign->update(['status' => 'cancelled', 'finished_at' => now()]);
        $campaign->deliveries()->where('status', 'queued')->update(['status' => 'skipped']);
        AuditLogger::log('whatsapp.aussendung.abgebrochen', $user, 'whatsapp_campaign', $campaign->id);
    }

    private function assertSendable(WhatsAppCampaign $campaign): void
    {
        $template = $campaign->template;
        if (! $template || $template->status !== 'APPROVED' || $template->purpose !== 'newsletter') {
            throw ValidationException::withMessages(['template_id' => 'Bitte eine von Meta freigegebene Newsletter-Vorlage wählen.']);
        }
        $body = array_filter($campaign->variables['body'] ?? [], fn ($v) => filled($v));
        if (count($body) < $template->bodyPlaceholders()) {
            throw ValidationException::withMessages(['variables' => 'Bitte alle Platzhalter der Vorlage ausfüllen.']);
        }
        if ($template->urlButton() !== null && blank($campaign->variables['url'] ?? null)) {
            throw ValidationException::withMessages(['variables' => 'Bitte den Link für den Button angeben.']);
        }
    }
}
