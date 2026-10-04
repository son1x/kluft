<?php

namespace Tests\Feature\WhatsApp;

use App\Filament\Pages\PrivacyRequestsPage;
use App\Filament\Pages\WhatsAppConnection;
use App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppDelivery;
use App\Models\WhatsAppSubscriber;
use App\Models\WhatsAppSuppression;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\WhatsAppCampaigns;
use App\Services\WhatsApp\WhatsAppSettings;
use App\Services\WhatsApp\WhatsAppSubscriptions;
use App\Services\WhatsApp\WhatsAppTemplates;
use App\Support\Health\HealthChecks;
use App\Support\PhoneNumber;
use App\Support\RoleCatalog;
use App\Support\SpamGuard;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * WhatsApp-Newsletter (docs/whatsapp_newsletter.md): Webhook, Double-Opt-In
 * mit START, Abmeldung mit STOP, Versand mit Freigabe, Datenminimierung.
 */
class WhatsAppNewsletterTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'app-secret-test';

    private const ANNA = '+4915112345678';

    private const BEN = '+4916098765432';

    private int $wamid = 0;

    /** @var list<array<string, mixed>> */
    private array $remoteTemplates = [];

    /** Antwort von Meta auf /messages erzwingen (Fehlerfälle). */
    private ?array $messagesError = null;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        config(['whatsapp.mode' => 'api', 'whatsapp.test_mode' => false, 'whatsapp.form_doi' => false, 'app.url' => 'https://dpsg-trier.de']);
        Http::fake(['graph.facebook.com/*' => function (Request $request) {
            return match (true) {
                str_ends_with($request->url(), '/messages') => $this->messagesError
                    ? Http::response($this->messagesError, 400)
                    : Http::response(['messages' => [['id' => 'wamid.'.(++$this->wamid)]]]),
                str_contains($request->url(), '/message_templates') && $request->method() === 'GET' => Http::response(['data' => $this->remoteTemplates]),
                str_contains($request->url(), '/message_templates') => Http::response(['id' => '9', 'status' => 'PENDING']),
                default => Http::response(['success' => true]),
            };
        }]);
    }

    private function connect(): void
    {
        app(WhatsAppSettings::class)->save(['token' => 'tok', 'phone_number_id' => '111', 'waba_id' => '222', 'app_secret' => self::SECRET]);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function hook(array $value, string $field = 'messages', ?string $signature = null)
    {
        $body = json_encode(['object' => 'whatsapp_business_account', 'entry' => [['id' => '222', 'changes' => [['field' => $field, 'value' => $value]]]]]);

        return $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature ?? 'sha256='.hash_hmac('sha256', $body, self::SECRET),
        ], $body);
    }

    private function message(string $from, string $text, ?string $id = null)
    {
        return $this->hook([
            'metadata' => ['phone_number_id' => '111'],
            'contacts' => [['wa_id' => ltrim($from, '+'), 'profile' => ['name' => 'Anna Geheimname']]],
            'messages' => [['from' => ltrim($from, '+'), 'id' => $id ?? 'in.'.uniqid(), 'type' => 'text', 'text' => ['body' => $text]]],
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function sent(): array
    {
        return Http::recorded()->map(fn ($pair) => $pair[0])
            ->filter(fn (Request $r) => str_ends_with($r->url(), '/messages'))->map(fn (Request $r) => $r->data())->values()->all();
    }

    private function user(string $email, string $role): User
    {
        $user = User::query()->create(['name' => $email, 'email' => $email, 'status' => 'active']);
        $user->roleAssignments()->create(['role' => $role, 'scope_type' => 'global', 'scope_key' => null]);

        return $user->fresh();
    }

    private function newsletterTemplate(): WhatsAppTemplate
    {
        return WhatsAppTemplate::query()->create([
            'name' => 'dpsg_news_v1', 'language' => 'de', 'category' => 'MARKETING', 'purpose' => 'newsletter', 'status' => 'APPROVED',
            'components' => [
                ['type' => 'BODY', 'text' => 'Neues aus dem DPSG Diözesanverband Trier: {{1}}'],
                ['type' => 'FOOTER', 'text' => 'Abmelden: STOP antworten'],
                ['type' => 'BUTTONS', 'buttons' => [['type' => 'URL', 'text' => 'Mehr lesen', 'url' => 'https://dpsg-trier.de/{{1}}']]],
            ],
        ]);
    }

    #[TestDox('Telefonnummern werden ins E.164-Format gebracht und maskiert')]
    public function test_phone_numbers(): void
    {
        $this->assertSame(self::ANNA, PhoneNumber::normalize('0151 / 123 456-78'));
        $this->assertSame(self::ANNA, PhoneNumber::normalize('0049 151 12345678'));
        $this->assertSame(self::ANNA, PhoneNumber::normalize('4915112345678'));
        $this->assertSame('+4965146298290', PhoneNumber::normalize('0651 4629829-0'));
        $this->assertNull(PhoneNumber::normalize('12'));
        $this->assertNull(PhoneNumber::normalize('+490151123'));
        $this->assertSame('+49 151 •••••678', PhoneNumber::mask(self::ANNA));
        $this->assertSame('https://wa.me/4965146298290?text=START', PhoneNumber::waMe('+4965146298290', 'START'));
    }

    #[TestDox('Webhook-Verifizierung nur mit dem richtigen Verify-Token')]
    public function test_webhook_verification(): void
    {
        $this->connect();
        $token = app(WhatsAppSettings::class)->get('verify_token');

        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token='.$token.'&hub.challenge=4711')->assertOk()->assertSeeText('4711');
        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=falsch&hub.challenge=4711')->assertForbidden();
    }

    #[TestDox('Webhook ohne gültige Signatur wird abgelehnt und ändert nichts')]
    public function test_webhook_rejects_invalid_signature(): void
    {
        $this->connect();

        $this->hook(['messages' => [['from' => '4915112345678', 'id' => 'x', 'type' => 'text', 'text' => ['body' => 'START']]]], signature: 'sha256=falsch')->assertForbidden();

        $this->assertSame(0, WhatsAppSubscriber::query()->count());
    }

    #[TestDox('START meldet sofort an; gespeichert sind nur verschlüsselte Nummer und Einwilligung, kein Name')]
    public function test_start_subscribes_with_minimal_data(): void
    {
        $this->connect();

        $this->message(self::ANNA, 'Start!', 'in.1')->assertOk();
        $this->message(self::ANNA, 'START', 'in.1'); // doppelt zugestellt

        $subscriber = WhatsAppSubscriber::query()->sole();
        $this->assertSame('active', $subscriber->status);
        $this->assertSame('link', $subscriber->source);
        $this->assertNotNull($subscriber->confirmed_at);
        $this->assertSame(self::ANNA, $subscriber->phone);

        $raw = json_encode([DB::table('whatsapp_subscribers')->get(), DB::table('audit_logs')->get()]);
        $this->assertStringNotContainsString('Geheimname', $raw);
        $this->assertStringNotContainsString('15112345678', $raw);

        $this->assertCount(1, $this->sent());
        $this->assertStringContainsString('Willkommen', $this->sent()[0]['text']['body']);
        $this->assertSame('4915112345678', $this->sent()[0]['to']);
    }

    #[TestDox('STOP löscht sofort und sperrt nur den Prüfwert; START hebt die Sperre wieder auf')]
    public function test_stop_and_resubscribe(): void
    {
        $this->connect();
        $this->message(self::ANNA, 'START');

        $this->message(self::ANNA, 'stopp');

        $this->assertSame(0, WhatsAppSubscriber::query()->count());
        $this->assertTrue(WhatsAppSuppression::isSuppressed(self::ANNA));
        $this->assertStringContainsString('abgemeldet', $this->sent()[1]['text']['body']);

        $this->message(self::ANNA, 'START');
        $this->assertSame('active', WhatsAppSubscriber::query()->sole()->status);
        $this->assertFalse(WhatsAppSuppression::isSuppressed(self::ANNA));
    }

    #[TestDox('Andere Nachrichten werden nicht gespeichert und höchstens einmal am Tag mit einem Hinweis beantwortet')]
    public function test_other_messages_get_one_hint_per_day(): void
    {
        $this->connect();

        $this->message(self::BEN, 'Hallo, wann ist das Lager?');
        $this->message(self::BEN, 'Hallo??');

        $this->assertCount(1, $this->sent());
        $this->assertStringContainsString('lesen keine Nachrichten', $this->sent()[0]['text']['body']);
        $this->assertSame(0, WhatsAppSubscriber::query()->count());
    }

    #[TestDox('Formular-Weg: erst nach START aktiv, höchstens eine Bestätigungs-Vorlage in 30 Tagen')]
    public function test_form_double_opt_in(): void
    {
        $this->connect();
        config(['whatsapp.form_doi' => true]);
        WhatsAppTemplate::query()->create(['name' => 'dpsg_doi_v1', 'language' => 'de', 'category' => 'UTILITY', 'purpose' => 'doi', 'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'Bitte mit START bestätigen.']]]);
        $token = SpamGuard::token();
        $this->travel(5)->seconds();

        $form = ['_t' => $token, 'nummer' => '0151 12345678', 'alter' => '1', 'einwilligung' => '1'];
        $this->post('/whatsapp', $form)->assertRedirect();
        $this->post('/whatsapp', $form)->assertRedirect();

        $this->assertSame('pending', WhatsAppSubscriber::query()->sole()->status);
        $this->assertCount(1, $this->sent());
        $this->assertSame('dpsg_doi_v1', $this->sent()[0]['template']['name']);

        $this->message(self::ANNA, 'START');
        $this->assertSame('active', WhatsAppSubscriber::query()->sole()->status);
        $this->assertSame('form', WhatsAppSubscriber::query()->sole()->source);

        $this->post('/whatsapp', [...$form, 'alter' => null])->assertSessionHasErrors('alter');
    }

    #[TestDox('Aussendung: Vier-Augen-Freigabe, Versand an aktive Abonnent:innen, Status per Webhook, Abbestellung bei Meta wirkt wie STOP')]
    public function test_campaign_flow(): void
    {
        $this->connect();
        $template = $this->newsletterTemplate();
        foreach ([self::ANNA, self::BEN] as $phone) {
            app(WhatsAppSubscriptions::class)->start($phone);
        }
        WhatsAppSubscriber::query()->create(['phone' => '+4917011111111', 'phone_hash' => WhatsAppSubscriber::hash('+4917011111111'), 'status' => 'pending', 'source' => 'form', 'consent_version' => 'x', 'requested_at' => now()]);
        $author = $this->user('wa@dpsg-trier.de', 'whatsapp');
        $approver = $this->user('buero@dpsg-trier.de', 'verwaltung');
        $campaign = WhatsAppCampaign::query()->create([
            'title' => 'Lager', 'template_id' => $template->id, 'status' => 'draft', 'created_by' => $author->id,
            'variables' => ['body' => ['Die Anmeldung zum Lager ist offen.'], 'url' => 'aktuelles/lager'],
        ]);
        $service = app(WhatsAppCampaigns::class);

        $service->submit($campaign, $author);
        try {
            $service->approve($campaign->fresh(), $author);
            $this->fail('Vier-Augen-Prinzip verletzt');
        } catch (ValidationException) {
        }
        $service->approve($campaign->fresh(), $approver);
        $this->assertSame(2, $campaign->fresh()->recipients);
        $this->assertEqualsWithDelta(0.26, (float) $campaign->fresh()->estimated_cost_eur, 0.001);

        $service->start($campaign->fresh(), $approver);

        $campaign->refresh();
        $this->assertSame('sent', $campaign->status);
        $this->assertSame(2, $campaign->sent);
        $templates = array_values(array_filter($this->sent(), fn ($m) => ($m['type'] ?? '') === 'template'));
        $this->assertCount(2, $templates);
        $this->assertSame('Die Anmeldung zum Lager ist offen.', $templates[0]['template']['components'][0]['parameters'][0]['text']);
        $this->assertSame('aktuelles/lager', $templates[0]['template']['components'][1]['parameters'][0]['text']);

        [$first, $second] = WhatsAppDelivery::query()->orderBy('id')->get()->all();
        $this->hook(['statuses' => [['id' => $first->wamid, 'status' => 'read']]])->assertOk();
        $this->hook(['statuses' => [['id' => $second->wamid, 'status' => 'failed', 'errors' => [['code' => 131050]]]]]);

        $campaign->refresh();
        $this->assertSame(1, $campaign->delivered);
        $this->assertSame(1, $campaign->failed);
        $this->assertSame(1, WhatsAppSubscriber::query()->active()->count());
        $this->assertSame(1, WhatsAppSuppression::query()->count());
    }

    #[TestDox('Konto- oder Spam-Fehler von Meta hält die Aussendung an und alarmiert')]
    public function test_fatal_error_pauses_campaign(): void
    {
        $this->connect();
        $this->messagesError = ['error' => ['message' => 'Spam rate limit hit', 'code' => 131048]];
        $template = $this->newsletterTemplate();
        app(WhatsAppSubscriptions::class)->start(self::ANNA);
        $campaign = WhatsAppCampaign::query()->create(['title' => 'X', 'template_id' => $template->id, 'status' => 'approved', 'variables' => ['body' => ['Hallo'], 'url' => 'x']]);

        app(WhatsAppCampaigns::class)->start($campaign);

        $campaign->refresh();
        $this->assertSame('paused', $campaign->status);
        $this->assertStringContainsString('131048', (string) $campaign->last_error);
        $this->assertSame('warnung', collect(app(HealthChecks::class)->run())->firstWhere('key', 'whatsapp')['status']);
    }

    #[TestDox('Testmodus: Nachrichten nur an Nummern der Test-Allowlist')]
    public function test_test_mode_allowlist(): void
    {
        $this->connect();
        config(['whatsapp.test_mode' => true, 'whatsapp.test_allowlist' => '0151 12345678']);

        $this->message(self::ANNA, 'START');
        $this->message(self::BEN, 'START');

        $this->assertSame(['4915112345678'], array_column($this->sent(), 'to'));
        $this->assertSame(2, WhatsAppSubscriber::query()->count());
    }

    #[TestDox('Rolle WhatsApp-Newsletter-Admin: ganzes Modul inkl. Verbindung; Verwaltung ohne Verbindung; Autor:innen gar nicht')]
    public function test_roles_and_access(): void
    {
        $this->assertArrayHasKey('whatsapp|global|', RoleCatalog::all());
        $this->assertTrue(RoleCatalog::isPrivileged('whatsapp|global|'));
        $this->assertArrayHasKey('whatsapp|global|', RoleCatalog::grantableBy($this->user('buero@dpsg-trier.de', 'verwaltung')));

        $this->actingAs($this->user('wa@dpsg-trier.de', 'whatsapp'));
        $this->get(WhatsAppCampaignResource::getUrl('index'))->assertOk();
        $this->get(WhatsAppConnection::getUrl())->assertOk()->assertSee('Zugangsdaten');

        $this->actingAs(User::query()->where('email', 'buero@dpsg-trier.de')->first());
        $this->get(WhatsAppCampaignResource::getUrl('index'))->assertOk();
        $this->get(WhatsAppConnection::getUrl())->assertForbidden();

        $this->actingAs($this->user('autor@dpsg-trier.de', 'autor'));
        $this->get(WhatsAppCampaignResource::getUrl('index'))->assertForbidden();
    }

    #[TestDox('Anmeldeseite nur, wenn verbunden; QR-Code zeigt auf die konfigurierte Nummer mit START')]
    public function test_landing_page_and_qr(): void
    {
        $this->get('/whatsapp')->assertNotFound();
        $this->assertSame('aus', collect(app(HealthChecks::class)->run())->firstWhere('key', 'whatsapp')['status']);

        $this->connect();
        $url = PhoneNumber::waMe((string) config('whatsapp.public_number'), 'START');
        $this->get('/whatsapp')->assertOk()->assertSee($url, false)->assertSee('ab 16 Jahren', false)->assertDontSee('Bestätigungsnachricht anfordern');

        $svg = (string) file_get_contents(public_path('images/whatsapp/anmelden-qr.svg'));
        $this->assertStringContainsString('<!-- data: '.$url.' -->', $svg, 'QR-Code neu erzeugen: python3 scripts/whatsapp-qr.py "'.$url.'"');
    }

    #[TestDox('Vorlagen: Startvorlagen bei Meta einreichen und abgleichen')]
    public function test_templates_create_and_sync(): void
    {
        $this->connect();
        $this->remoteTemplates = [['id' => '9', 'name' => 'dpsg_news_v1', 'language' => 'de', 'category' => 'MARKETING', 'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'Neues: {{1}}']]]];

        app(WhatsAppTemplates::class)->create(WhatsAppTemplates::starter()[1]);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r['category'] === 'MARKETING'
            && $r['components'][2]['buttons'][0]['url'] === 'https://dpsg-trier.de/{{1}}'
            && $r['components'][0]['example']['body_text'][0][0] !== '');
        $this->assertSame('PENDING', WhatsAppTemplate::query()->sole()->status);

        $this->assertSame(1, app(WhatsAppTemplates::class)->sync());
        $this->assertSame('APPROVED', WhatsAppTemplate::query()->sole()->status);

        $this->hook(['event' => 'PAUSED', 'message_template_id' => '9', 'message_template_name' => 'dpsg_news_v1', 'message_template_language' => 'de', 'reason' => 'LOW_QUALITY'], 'message_template_status_update');
        $this->assertSame('PAUSED', WhatsAppTemplate::query()->sole()->status);
    }

    #[TestDox('Löschfristen und Datenschutz-Anfrage per Telefonnummer')]
    public function test_prune_and_privacy_request(): void
    {
        $this->connect();
        WhatsAppSubscriber::query()->create(['phone' => self::BEN, 'phone_hash' => WhatsAppSubscriber::hash(self::BEN), 'status' => 'pending', 'source' => 'form', 'consent_version' => 'x', 'requested_at' => now()->subHours(49)]);
        WhatsAppSuppression::add('+4917099999999', 'stop');
        $this->travel(13)->months();
        app(WhatsAppSubscriptions::class)->start(self::ANNA);

        $this->artisan('whatsapp:prune')->assertSuccessful();
        $this->assertSame(1, WhatsAppSubscriber::query()->count());
        $this->assertSame(0, WhatsAppSuppression::query()->count());

        $this->actingAs($this->user('buero@dpsg-trier.de', 'verwaltung'));
        Livewire::test(PrivacyRequestsPage::class)
            ->set('phone', '0151 12345678')->call('searchPhone')
            ->assertSee('+49 151 •••••678')->assertSee('aktiv')
            ->call('deleteWhatsApp');
        $this->assertSame(0, WhatsAppSubscriber::query()->count());
    }
}
