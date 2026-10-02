<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Prüft die Brevo-Anbindung für den Georgsbrief, ohne eine Mail zu
 * verschicken: API-Schlüssel (inkl. IP-Freigabe), Liste und
 * Double-Opt-In-Vorlage. Gibt keine Schlüssel oder Adressen aus.
 */
class BrevoCheckCommand extends Command
{
    protected $signature = 'brevo:check';

    protected $description = 'Prüft API-Schlüssel, Liste und Double-Opt-In-Vorlage bei Brevo';

    private const API = 'https://api.brevo.com/v3';

    public function handle(): int
    {
        $key = (string) config('services.brevo.api_key');
        $listId = (int) config('services.brevo.newsletter_list_id');
        $templateId = (int) config('services.brevo.doi_template_id');

        $kind = match (true) {
            str_starts_with($key, 'xkeysib-') => 'API-Schlüssel (xkeysib-…)',
            str_starts_with($key, 'xsmtpsib-') => 'SMTP-Schlüssel (xsmtpsib-…) – falsch, hier gehört der API-Schlüssel hin',
            default => 'unbekanntes Format',
        };
        $this->line('API-Schlüssel gesetzt: '.($key !== '' ? "ja, {$kind}" : 'NEIN'));
        $this->line('Listen-ID: '.($listId ?: 'NEIN').' · Vorlagen-ID: '.($templateId ?: 'NEIN'));
        $this->line('Weiterleitung nach Bestätigung: '.(config('services.brevo.doi_redirect_url') ?: route('georgsbrief.danke')));
        if ($key === '') {
            return self::FAILURE;
        }

        $ok = true;

        $account = $this->get('/account', $key);
        if ($account?->successful()) {
            $this->info('✓ API-Schlüssel gültig');
        } else {
            $ok = false;
            $this->error('✗ API-Schlüssel abgelehnt: '.$this->describe($account));
        }

        if ($listId) {
            $list = $this->get("/contacts/lists/{$listId}", $key);
            if ($list?->successful()) {
                $this->info('✓ Liste #'.$listId.': „'.$list->json('name').'“ ('.$list->json('uniqueSubscribers', $list->json('totalSubscribers')).' Kontakte)');
            } else {
                $ok = false;
                $this->error('✗ Liste #'.$listId.': '.$this->describe($list));
            }
        }

        if ($templateId) {
            $template = $this->get("/smtp/templates/{$templateId}", $key);
            if ($template?->successful()) {
                $html = (string) $template->json('htmlContent');
                $this->line('Vorlage #'.$templateId.': „'.$template->json('name').'“');
                $checks = [
                    'aktiv' => (bool) $template->json('isActive'),
                    'Link {{ doubleoptin }} enthalten' => (bool) preg_match('/\{\{\s*doubleoptin\s*\}\}/i', $html),
                    'Absender gesetzt' => filled($template->json('sender.email')),
                ];
                foreach ($checks as $label => $passed) {
                    $passed ? $this->info("  ✓ {$label}") : $this->error("  ✗ {$label}");
                    $ok = $ok && $passed;
                }
                $this->line('  Absender-Domain: '.Str::after((string) $template->json('sender.email'), '@').' · Tag: '.($template->json('tag') ?: '–'));
            } else {
                $ok = false;
                $this->error('✗ Vorlage #'.$templateId.': '.$this->describe($template));
            }
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function get(string $path, string $key): ?Response
    {
        try {
            return Http::withHeaders(['api-key' => $key, 'accept' => 'application/json'])->timeout(10)->get(self::API.$path);
        } catch (Throwable $e) {
            $this->error('Brevo nicht erreichbar: '.Str::limit($e->getMessage(), 200));

            return null;
        }
    }

    private function describe(?Response $response): string
    {
        if (! $response) {
            return 'keine Antwort';
        }

        return 'HTTP '.$response->status().' – '.self::scrub((string) ($response->json('message') ?? $response->json('code') ?? ''));
    }

    /** E-Mail-Adressen und IPs aus Fehlermeldungen entfernen. */
    public static function scrub(string $message): string
    {
        $message = preg_replace('/[^\s@]+@[^\s@]+\.[a-z]{2,}/i', '<email>', $message) ?? '';

        return Str::limit(preg_replace('/\b(\d{1,3}\.){3}\d{1,3}\b|\b[0-9a-f]{0,4}(:[0-9a-f]{0,4}){3,7}\b/i', '<ip>', $message) ?? '', 300);
    }
}
