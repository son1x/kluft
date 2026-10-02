<?php

namespace App\Services;

use App\Console\Commands\BrevoCheckCommand;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Newsletter-Anmeldung per Double-Opt-In bei Brevo (F-GB-11). Die
 * E-Mail-Adresse wird nur durchgereicht, nie im CMS gespeichert (F-GB-12);
 * der Kontakt landet erst nach dem Bestätigungsklick in der Liste.
 */
class BrevoNewsletter
{
    public const ENDPOINT = 'https://api.brevo.com/v3/contacts/doubleOptinConfirmation';

    public function isConfigured(): bool
    {
        return filled(config('services.brevo.api_key'))
            && filled(config('services.brevo.newsletter_list_id'))
            && filled(config('services.brevo.doi_template_id'));
    }

    /**
     * @return bool true, wenn Brevo die Anfrage angenommen hat
     */
    public function requestDoubleOptIn(string $email, ?string $firstName): bool
    {
        $response = Http::withHeaders([
            'api-key' => config('services.brevo.api_key'),
            'accept' => 'application/json',
        ])
            ->timeout(10)
            ->post(self::ENDPOINT, array_filter([
                'email' => $email,
                'attributes' => filled($firstName) ? ['VORNAME' => $firstName, 'FIRSTNAME' => $firstName] : null,
                'includeListIds' => [(int) config('services.brevo.newsletter_list_id')],
                'templateId' => (int) config('services.brevo.doi_template_id'),
                'redirectionUrl' => config('services.brevo.doi_redirect_url') ?: route('georgsbrief.danke'),
            ]));

        if (! $response->successful()) {
            // Keine Adresse ins Log (Datensparsamkeit): Status, Fehlercode und
            // Brevos Meldung ohne E-Mail-Adressen/IPs. Als error, damit es
            // bei LOG_LEVEL=error im Log und im Deploy-Protokoll erscheint.
            Log::error('Brevo-DOI fehlgeschlagen', [
                'status' => $response->status(),
                'code' => $response->json('code'),
                'message' => BrevoCheckCommand::scrub((string) $response->json('message')),
            ]);
        }

        return $response->successful();
    }
}
