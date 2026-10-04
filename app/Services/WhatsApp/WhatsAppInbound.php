<?php

namespace App\Services\WhatsApp;

use App\Jobs\SendWhatsAppMessage;
use App\Models\WhatsAppDelivery;
use App\Models\WhatsAppSubscriber;
use App\Models\WhatsAppTemplate;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Cache;

/**
 * Verarbeitet Webhook-Ereignisse von Meta (docs/whatsapp_newsletter.md,
 * 4.3 und 6.1). Profilnamen und Nachrichteninhalte werden nie gespeichert:
 * aus Texten wird nur das Schlüsselwort gelesen.
 */
class WhatsAppInbound
{
    public function __construct(
        private WhatsAppSubscriptions $subscriptions,
        private WhatsAppTeam $team,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                match ($change['field'] ?? null) {
                    'messages' => $this->messages($value),
                    'message_template_status_update' => $this->templateStatus($value),
                    'phone_number_quality_update' => $this->quality($value),
                    'account_update', 'account_alerts' => $this->account($value),
                    default => null,
                };
            }
        }
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function messages(array $value): void
    {
        foreach ($value['messages'] ?? [] as $message) {
            // Meta stellt Ereignisse ggf. mehrfach zu.
            if (! Cache::add('whatsapp-msg|'.($message['id'] ?? ''), 1, now()->addHours(48))) {
                continue;
            }
            $from = PhoneNumber::normalize((string) ($message['from'] ?? ''));
            if ($from !== null) {
                $this->incoming($from, $this->keyword($message));
            }
        }

        foreach ($value['statuses'] ?? [] as $status) {
            $this->status($status);
        }
    }

    /**
     * Nur das erste Wort, nur Buchstaben, groß: „Start!“ → START.
     *
     * @param  array<string, mixed>  $message
     */
    private function keyword(array $message): string
    {
        $text = match ($message['type'] ?? null) {
            'text' => $message['text']['body'] ?? '',
            'button' => $message['button']['text'] ?? '',
            'interactive' => $message['interactive']['button_reply']['title'] ?? '',
            default => '',
        };
        $first = preg_split('/\s+/u', trim((string) $text))[0] ?? '';

        return mb_strtoupper(preg_replace('/[^\p{L}]/u', '', $first) ?? '');
    }

    private function incoming(string $from, string $keyword): void
    {
        if (in_array($keyword, config('whatsapp.keywords.start'), true)) {
            SendWhatsAppMessage::dispatch($from, $this->subscriptions->start($from));

            return;
        }

        if (in_array($keyword, config('whatsapp.keywords.stop'), true)) {
            $this->subscriptions->stop($from);
            SendWhatsAppMessage::dispatch($from, 'stopped');

            return;
        }

        WhatsAppSubscriber::findByPhone($from)?->forceFill(['last_inbound_at' => now()])->save();
        // Hinweis höchstens einmal je 24 Stunden und Nummer; Inhalt wird verworfen.
        if (Cache::add('whatsapp-info|'.WhatsAppSubscriber::hash($from), 1, now()->addHours(24))) {
            SendWhatsAppMessage::dispatch($from, 'info');
        }
    }

    /**
     * Zustellstatus: sent → delivered (read zählt als zugestellt) oder failed.
     *
     * @param  array<string, mixed>  $status
     */
    private function status(array $status): void
    {
        $delivery = WhatsAppDelivery::query()->where('wamid', (string) ($status['id'] ?? ''))->first();
        if (! $delivery) {
            return;
        }

        $new = match ($status['status'] ?? null) {
            'sent' => $delivery->status === 'queued' ? 'sent' : null,
            'delivered', 'read' => 'delivered',
            'failed' => 'failed',
            default => null,
        };
        if ($new === null || $delivery->status === $new || ($delivery->status === 'delivered' && $new === 'sent')) {
            return;
        }

        $code = (int) ($status['errors'][0]['code'] ?? 0);
        $delivery->update(['status' => $new, 'error_code' => $new === 'failed' ? $code : null]);

        $subscriber = $delivery->subscriber;
        if ($subscriber) {
            if ($new === 'delivered') {
                $subscriber->forceFill(['failed_streak' => 0])->save();
            } elseif ($new === 'failed') {
                $this->recipientFailed($subscriber, $code);
            }
        }

        $delivery->campaign->refreshCounts();
    }

    public function recipientFailed(WhatsAppSubscriber $subscriber, int $code): void
    {
        if (in_array($code, WhatsAppException::OPT_OUT, true)) {
            $this->subscriptions->stop($subscriber->phone, 'meta_optout');

            return;
        }

        $subscriber->increment('failed_streak');
        // Nummer dauerhaft nicht erreichbar (z. B. kein WhatsApp mehr): nach drei Fehlschlägen löschen.
        if ($subscriber->failed_streak >= 3) {
            $subscriber->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function templateStatus(array $value): void
    {
        $event = strtoupper((string) ($value['event'] ?? ''));
        $template = WhatsAppTemplate::query()
            ->where('meta_id', (string) ($value['message_template_id'] ?? ''))
            ->orWhere(fn ($q) => $q->where('name', $value['message_template_name'] ?? '')->where('language', $value['message_template_language'] ?? ''))
            ->first();

        if ($template && in_array($event, ['APPROVED', 'REJECTED', 'PAUSED', 'DISABLED', 'PENDING_DELETION', 'FLAGGED', 'REINSTATED'], true)) {
            $template->update([
                'status' => $event === 'REINSTATED' ? 'APPROVED' : ($event === 'FLAGGED' ? $template->status : $event),
                'rejected_reason' => $value['reason'] ?? null,
                'synced_at' => now(),
            ]);
        }

        if (in_array($event, ['REJECTED', 'PAUSED', 'DISABLED', 'FLAGGED'], true)) {
            $name = (string) ($value['message_template_name'] ?? 'unbekannt');
            $this->team->alert("Vorlage „{$name}“: {$event}", 'Meta hat den Status einer Vorlage geändert'.(filled($value['reason'] ?? null) ? ' (Grund: '.$value['reason'].')' : '').'. Aussendungen mit dieser Vorlage sind nicht möglich.');
        }
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function quality(array $value): void
    {
        $event = strtoupper((string) ($value['event'] ?? ''));
        Cache::forever('whatsapp:quality', ['event' => $event, 'limit' => $value['current_limit'] ?? null, 'at' => now()->toIso8601String()]);

        if (in_array($event, ['FLAGGED', 'DOWNGRADE'], true)) {
            $this->team->alert('Qualität der Nummer gesunken', "Meta meldet „{$event}“ für die WhatsApp-Nummer. Häufige Ursache: zu viele Blockierungen. Bitte Aussendungen sparsam halten und im WhatsApp Manager die Kontoqualität prüfen.");
        }
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function account(array $value): void
    {
        $event = (string) ($value['event'] ?? $value['alert_type'] ?? 'Hinweis');
        $this->team->alert('Hinweis zum WhatsApp-Konto', "Meta meldet „{$event}“ zum WhatsApp-Konto. Bitte im WhatsApp Manager prüfen.");
    }
}
