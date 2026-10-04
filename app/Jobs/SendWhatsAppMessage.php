<?php

namespace App\Jobs;

use App\Models\ContactTopic;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\WhatsAppClient;
use App\Services\WhatsApp\WhatsAppException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Antworten auf Schlüsselwörter (im 24-h-Servicefenster, kostenlos) und die
 * Bestätigungs-Vorlage beim Formular-Weg. Über die Warteschlange, damit der
 * Webhook sofort antwortet. Nutzlast verschlüsselt (enthält die Nummer).
 */
class SendWhatsAppMessage implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    /**
     * @param  'welcome'|'already'|'stopped'|'info'|'doi'  $kind
     */
    public function __construct(public string $to, public string $kind) {}

    public static function text(string $kind): string
    {
        $privacy = url('/datenschutz');
        $email = ContactTopic::fallbackEmail();
        $phone = (string) config('whatsapp.public_number_display');

        return match ($kind) {
            'welcome' => 'Willkommen beim WhatsApp-Newsletter des '.config('dpsg.site.name_genitive')."! Du bekommst hier ab und zu Neuigkeiten, Termine und Anmeldestarts.\n\nDer Newsletter ist für Menschen ab 16 Jahren. Wir speichern nur deine Nummer und den Zeitpunkt deiner Anmeldung. Datenschutz: {$privacy}\n\nAbmelden: jederzeit STOP schreiben.",
            'already' => 'Du bist schon angemeldet. Abmelden: STOP schreiben.',
            'stopped' => 'Du bist abgemeldet und bekommst keine Nachrichten mehr. Mit START kannst du dich jederzeit wieder anmelden.',
            default => "Über diese Nummer verschicken wir nur unseren Newsletter und lesen keine Nachrichten. Fragen bitte an {$email} oder telefonisch unter {$phone}.\n\nAnmelden: START · Abmelden: STOP",
        };
    }

    public function handle(WhatsAppClient $client): void
    {
        try {
            if ($this->kind === 'doi') {
                $template = WhatsAppTemplate::query()->usable('doi')->first();
                if (! $template) {
                    Log::warning('WhatsApp: keine freigegebene Bestätigungs-Vorlage (Zweck „doi“).');

                    return;
                }
                $client->sendTemplate($this->to, $template);

                return;
            }

            $client->sendText($this->to, self::text($this->kind));
        } catch (WhatsAppException $e) {
            if ($e->retryable()) {
                $this->release(120);

                return;
            }
            Log::warning('WhatsApp-Antwort nicht zugestellt', ['art' => $this->kind, 'code' => $e->metaCode]);
        }
    }
}
