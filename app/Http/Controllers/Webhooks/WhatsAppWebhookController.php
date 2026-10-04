<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\WhatsApp\WhatsAppInbound;
use App\Services\WhatsApp\WhatsAppSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Webhook der WhatsApp Cloud API (docs/whatsapp_newsletter.md, 6.1).
 * GET: Verifizierung bei der Einrichtung. POST: Ereignisse, nur mit
 * gültiger Signatur (HMAC-SHA256 des rohen Bodys mit dem App-Secret).
 */
class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request, WhatsAppSettings $settings): Response
    {
        // PHP macht aus „hub.mode“ im Query-String „hub_mode“.
        $expected = $settings->get('verify_token');
        $given = (string) $request->query('hub_verify_token', '');

        if ($request->query('hub_mode') !== 'subscribe' || $expected === null || ! hash_equals($expected, $given)) {
            return response('Forbidden', 403);
        }

        return response((string) $request->query('hub_challenge', ''), 200)->header('Content-Type', 'text/plain');
    }

    public function receive(Request $request, WhatsAppSettings $settings, WhatsAppInbound $inbound): Response
    {
        $secret = $settings->get('app_secret');
        $body = $request->getContent();
        $signature = (string) $request->header('X-Hub-Signature-256', '');

        if ($secret === null || ! hash_equals('sha256='.hash_hmac('sha256', $body, $secret), $signature)) {
            Log::warning('WhatsApp-Webhook: ungültige Signatur abgelehnt');

            return response('Forbidden', 403);
        }

        $payload = json_decode($body, true);
        if (! is_array($payload) || ($payload['object'] ?? null) !== 'whatsapp_business_account') {
            return response('OK', 200);
        }

        try {
            $inbound->handle($payload);
        } catch (Throwable $e) {
            // Meta stellt erneut zu; doppelte Nachrichten werden übersprungen.
            report($e);

            return response('Fehler', 500);
        }

        return response('OK', 200);
    }
}
