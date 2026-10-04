<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsAppTemplate;

/**
 * Vorlagen bei Meta anlegen und mit der Datenbank abgleichen.
 */
class WhatsAppTemplates
{
    public function __construct(private WhatsAppClient $client) {}

    /**
     * Startausstattung (docs/whatsapp_newsletter.md, 7.3).
     *
     * @return list<array<string, mixed>>
     */
    public static function starter(): array
    {
        $base = rtrim((string) config('app.url'), '/');

        return [
            [
                'name' => 'dpsg_doi_v1', 'category' => 'UTILITY', 'purpose' => 'doi',
                'body' => 'Hallo! Bitte bestätige deine Anmeldung zum WhatsApp-Newsletter der DPSG Trier: Antworte mit START. Warst du das nicht? Dann ignoriere diese Nachricht einfach.',
                'examples' => [], 'footer' => null, 'url' => null, 'quick_replies' => ['START'],
            ],
            [
                'name' => 'dpsg_news_v1', 'category' => 'MARKETING', 'purpose' => 'newsletter',
                'body' => 'Neues aus dem DPSG Diözesanverband Trier: {{1}}',
                'examples' => ['Die Anmeldung zum Diözesanlager ist offen.'], 'footer' => 'Abmelden: STOP antworten',
                'url' => ['text' => 'Mehr lesen', 'base' => $base.'/', 'example' => $base.'/aktuelles/beispiel'], 'quick_replies' => [],
            ],
            [
                'name' => 'dpsg_event_v1', 'category' => 'MARKETING', 'purpose' => 'newsletter',
                'body' => 'Anmeldung offen: {{1}} am {{2}}. Alle Infos und die Anmeldung findest du über den Button.',
                'examples' => ['Diözesan-Wölflingstag', '14. März 2027'], 'footer' => 'Abmelden: STOP antworten',
                'url' => ['text' => 'Zur Anmeldung', 'base' => $base.'/', 'example' => $base.'/veranstaltungen/beispiel'], 'quick_replies' => [],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $def  name, category, purpose, body, examples, footer, url{text, base, example}, quick_replies
     */
    public function create(array $def): WhatsAppTemplate
    {
        $body = ['type' => 'BODY', 'text' => $def['body']];
        if ($def['examples'] !== []) {
            $body['example'] = ['body_text' => [array_values($def['examples'])]];
        }
        $components = [$body];
        if (filled($def['footer'])) {
            $components[] = ['type' => 'FOOTER', 'text' => $def['footer']];
        }
        $buttons = [];
        if ($def['url'] !== null) {
            $buttons[] = ['type' => 'URL', 'text' => $def['url']['text'], 'url' => $def['url']['base'].'{{1}}', 'example' => [$def['url']['example']]];
        }
        foreach ($def['quick_replies'] as $reply) {
            $buttons[] = ['type' => 'QUICK_REPLY', 'text' => $reply];
        }
        if ($buttons !== []) {
            $components[] = ['type' => 'BUTTONS', 'buttons' => $buttons];
        }

        $result = $this->client->createTemplate([
            'name' => $def['name'],
            'language' => 'de',
            'category' => $def['category'],
            'components' => $components,
        ]);

        return WhatsAppTemplate::query()->updateOrCreate(
            ['name' => $def['name'], 'language' => 'de'],
            ['meta_id' => $result['id'], 'category' => $def['category'], 'purpose' => $def['purpose'], 'status' => $result['status'], 'components' => $components, 'synced_at' => now()],
        );
    }

    /** Alle Vorlagen von Meta übernehmen; Zweck bleibt, wie im CMS eingestellt. */
    public function sync(): int
    {
        $count = 0;
        foreach ($this->client->templates() as $remote) {
            $template = WhatsAppTemplate::query()->firstOrNew(['name' => $remote['name'], 'language' => $remote['language']]);
            $template->fill([
                'meta_id' => (string) ($remote['id'] ?? ''),
                'category' => (string) ($remote['category'] ?? 'MARKETING'),
                'status' => (string) ($remote['status'] ?? 'PENDING'),
                'components' => $remote['components'] ?? [],
                'quality' => $remote['quality_score']['score'] ?? null,
                'rejected_reason' => ($remote['rejected_reason'] ?? 'NONE') === 'NONE' ? null : $remote['rejected_reason'],
                'synced_at' => now(),
            ]);
            if (! $template->exists) {
                $template->purpose = ($remote['category'] ?? '') === 'UTILITY' ? 'doi' : 'newsletter';
            }
            $template->save();
            $count++;
        }

        return $count;
    }
}
