<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppTemplate;
use App\Support\PhoneNumber;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Cloud API (Meta Graph API). Läuft wie alles andere direkt auf
 * dem Webspace über HTTPS; keine weiteren Dienste.
 *
 * In der Testumgebung (whatsapp.test_mode) gehen Nachrichten nur an die
 * Nummern der Test-Allowlist; alle anderen werden übersprungen (null).
 */
class WhatsAppClient
{
    public function __construct(private WhatsAppSettings $settings) {}

    /**
     * @param  array<int|string, string>  $bodyParams  Werte für {{1}}, {{2}} … (Reihenfolge zählt)
     * @return string|null Nachrichten-ID (wamid) oder null, wenn im Testmodus übersprungen
     */
    public function sendTemplate(string $to, WhatsAppTemplate $template, array $bodyParams = [], ?string $urlParam = null): ?string
    {
        $components = [];
        if ($bodyParams !== []) {
            $components[] = ['type' => 'body', 'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], array_values($bodyParams))];
        }
        if ($urlParam !== null && $template->urlButton() !== null) {
            $components[] = ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $urlParam]]];
        }

        return $this->send($to, [
            'type' => 'template',
            'template' => array_filter([
                'name' => $template->name,
                'language' => ['code' => $template->language],
                'components' => $components ?: null,
            ]),
        ]);
    }

    /** Freie Antwort – nur im 24-Stunden-Servicefenster erlaubt. */
    public function sendText(string $to, string $text): ?string
    {
        return $this->send($to, ['type' => 'text', 'text' => ['body' => $text, 'preview_url' => false]]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function templates(): array
    {
        $templates = [];
        $url = $this->graph($this->wabaId().'/message_templates');
        $query = ['fields' => 'id,name,language,category,status,components,quality_score,rejected_reason', 'limit' => 100];

        for ($page = 0; $url !== null && $page < 20; $page++) {
            $json = $this->call('get', $url, $query);
            $templates = [...$templates, ...($json['data'] ?? [])];
            $url = $json['paging']['next'] ?? null;
            $query = [];
        }

        return $templates;
    }

    /**
     * @param  array<string, mixed>  $payload  name, language, category, components
     * @return array{id: string, status: string}
     */
    public function createTemplate(array $payload): array
    {
        $json = $this->call('post', $this->graph($this->wabaId().'/message_templates'), $payload);

        return ['id' => (string) ($json['id'] ?? ''), 'status' => (string) ($json['status'] ?? 'PENDING')];
    }

    /**
     * @return array<string, mixed>
     */
    public function phoneStatus(): array
    {
        return $this->call('get', $this->graph((string) $this->settings->phoneNumberId()), [
            'fields' => 'display_phone_number,verified_name,quality_rating,messaging_limit_tier,status,code_verification_status,name_status',
        ]);
    }

    public function subscribeApp(): void
    {
        $this->call('post', $this->graph($this->wabaId().'/subscribed_apps'));
    }

    /** Einmalige Registrierung der Nummer für die Cloud API (PIN der Zwei-Schritt-Verifizierung). */
    public function register(string $pin): void
    {
        $this->call('post', $this->graph($this->settings->phoneNumberId().'/register'), [
            'messaging_product' => 'whatsapp',
            'pin' => $pin,
        ]);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function send(string $to, array $message): ?string
    {
        if (! $this->allowedInTestMode($to)) {
            Log::info('WhatsApp: Testmodus, Nachricht übersprungen', ['an' => '…'.substr($to, -3)]);

            return null;
        }

        $json = $this->call('post', $this->graph($this->settings->phoneNumberId().'/messages'), [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => ltrim($to, '+'),
            ...$message,
        ]);

        return (string) ($json['messages'][0]['id'] ?? '');
    }

    public function allowedInTestMode(string $to): bool
    {
        if (! config('whatsapp.test_mode')) {
            return true;
        }

        $allowed = array_filter(array_map(
            fn (string $n) => PhoneNumber::normalize($n),
            preg_split('/[,;\r\n]+/', (string) config('whatsapp.test_allowlist')) ?: [],
        ));

        return in_array($to, $allowed, true);
    }

    private function wabaId(): string
    {
        return $this->settings->get('waba_id') ?? throw new WhatsAppException('WABA-ID fehlt (WhatsApp-Verbindung im Backend).');
    }

    private function graph(string $path): string
    {
        return 'https://graph.facebook.com/'.config('whatsapp.graph_version').'/'.ltrim($path, '/');
    }

    private function request(): PendingRequest
    {
        $token = $this->settings->token() ?? throw new WhatsAppException('WhatsApp ist nicht verbunden.');

        return Http::withToken($token)->acceptJson()->asJson()->timeout(15);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function call(string $method, string $url, array $data = []): array
    {
        try {
            $response = $method === 'get' ? $this->request()->get($url, $data) : $this->request()->post($url, $data);
        } catch (ConnectionException $e) {
            throw new WhatsAppException('Meta nicht erreichbar: '.$e->getMessage(), 0, 503);
        }

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        $error = $response->json('error') ?? [];
        $message = (string) ($error['error_user_msg'] ?? $error['message'] ?? 'Fehler '.$response->status());
        Log::warning('WhatsApp-API-Fehler', ['status' => $response->status(), 'code' => $error['code'] ?? null]);

        throw new WhatsAppException($message, (int) ($error['code'] ?? 0), $response->status());
    }
}
