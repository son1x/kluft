<?php

namespace App\Services\WhatsApp;

use App\Jobs\SendWhatsAppMessage;
use App\Models\WhatsAppSubscriber;
use App\Models\WhatsAppSuppression;
use App\Support\AuditLogger;

/**
 * Anmeldung (Double-Opt-In mit START) und Abmeldung (STOP), s.
 * docs/whatsapp_newsletter.md Abschnitt 4. Protokolliert wird nur, dass
 * etwas passiert ist – nie die Nummer.
 */
class WhatsAppSubscriptions
{
    /**
     * Weg B: Nummer aus dem Formular. Legt „pending“ an und schickt die
     * Bestätigungs-Vorlage – höchstens einmal in 30 Tagen je Nummer, nie an
     * gesperrte oder schon aktive Nummern. Ergebnis wird nicht verraten.
     */
    public function requestByForm(string $e164): void
    {
        if (WhatsAppSuppression::isSuppressed($e164)) {
            return;
        }

        $subscriber = WhatsAppSubscriber::findByPhone($e164);
        if ($subscriber?->status === 'active' || $subscriber?->requested_at?->gt(now()->subDays(30))) {
            return;
        }

        $subscriber ??= new WhatsAppSubscriber(['phone' => $e164, 'phone_hash' => WhatsAppSubscriber::hash($e164)]);
        $subscriber->fill([
            'status' => 'pending',
            'source' => 'form',
            'consent_version' => (string) config('whatsapp.consent_version'),
            'requested_at' => now(),
        ])->save();

        AuditLogger::log('whatsapp.anmeldung.angefragt');
        SendWhatsAppMessage::dispatch($e164, 'doi');
    }

    /**
     * START von der eigenen Nummer: bestätigt Nummer und Willen zugleich.
     *
     * @return 'welcome'|'already'
     */
    public function start(string $e164): string
    {
        WhatsAppSuppression::query()->whereKey(WhatsAppSubscriber::hash($e164))->delete();

        $subscriber = WhatsAppSubscriber::findByPhone($e164);
        if ($subscriber?->status === 'active') {
            $subscriber->forceFill(['last_inbound_at' => now()])->save();

            return 'already';
        }

        $subscriber ??= new WhatsAppSubscriber(['phone' => $e164, 'phone_hash' => WhatsAppSubscriber::hash($e164), 'source' => 'link']);
        $subscriber->fill([
            'status' => 'active',
            'consent_version' => (string) config('whatsapp.consent_version'),
            'confirmed_at' => now(),
            'last_inbound_at' => now(),
            'failed_streak' => 0,
        ])->save();

        AuditLogger::log('whatsapp.anmeldung.bestaetigt:'.$subscriber->source);

        return 'welcome';
    }

    /** STOP oder Abmeldung über Meta: löschen, nur Prüfwert sperren. */
    public function stop(string $e164, string $reason = 'stop'): void
    {
        WhatsAppSubscriber::query()->where('phone_hash', WhatsAppSubscriber::hash($e164))->delete();
        WhatsAppSuppression::add($e164, $reason);

        AuditLogger::log('whatsapp.abmeldung:'.$reason);
    }
}
