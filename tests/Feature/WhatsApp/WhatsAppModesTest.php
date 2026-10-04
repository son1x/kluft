<?php

namespace Tests\Feature\WhatsApp;

use App\Filament\Pages\WhatsAppConnection;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource;
use App\Models\Event;
use App\Models\Post;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppSettings;
use App\Support\Health\HealthChecks;
use App\Support\PushEvents;
use App\Support\RoleCatalog;
use App\Support\WhatsAppChannelText;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Beide WhatsApp-Varianten sind vorbereitet, sichtbar nur die per
 * WHATSAPP_MODE eingeschaltete (off | channel | api).
 */
class WhatsAppModesTest extends TestCase
{
    use RefreshDatabase;

    private const CHANNEL = 'https://whatsapp.com/channel/0029TestKanalDpsgTrier';

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    private function verwaltung(): User
    {
        $user = User::query()->create(['name' => 'Büro', 'email' => 'buero@dpsg-trier.de', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global', 'scope_key' => null]);

        return $user->fresh();
    }

    private function makePost(): Post
    {
        return Post::query()->create([
            'title' => 'Diözesanlager 2027: Anmeldung offen', 'slug' => 'dioezesanlager-2027', 'teaser' => 'Vom *1.* bis 10. August am Hunsrück.',
            'body' => '<p>Text</p>', 'status' => 'published', 'published_at' => now()->subHour(),
        ]);
    }

    private function event(): Event
    {
        return Event::query()->create([
            'title' => 'Wölflingstag', 'slug' => 'woelflingstag', 'teaser' => 'Ein Tag für alle Wölflinge.', 'location' => 'Trier',
            'starts_at' => now()->addMonth()->setTime(10, 0), 'ends_at' => now()->addMonth()->setTime(17, 0), 'status' => 'open',
            'form_fields' => ['birth_year' => 'optional'],
        ]);
    }

    #[TestDox('Standard „off“: weder Kanal noch Business-Variante sichtbar')]
    public function test_off_by_default(): void
    {
        $this->assertSame('off', config('whatsapp.mode'));
        app(WhatsAppSettings::class)->save(['token' => 't', 'phone_number_id' => '1', 'waba_id' => '2', 'app_secret' => 's']);

        $this->get('/whatsapp')->assertNotFound();
        $this->get('/webhooks/whatsapp?hub.mode=subscribe')->assertNotFound();
        $this->assertArrayNotHasKey('whatsapp|global|', RoleCatalog::all());
        $this->assertNull(collect(app(HealthChecks::class)->run())->firstWhere('key', 'whatsapp'));

        $user = $this->verwaltung();
        $this->assertArrayNotHasKey(PushEvents::WHATSAPP, PushEvents::availableFor($user));
        $this->actingAs($user);
        $this->get(WhatsAppCampaignResource::getUrl('index'))->assertForbidden();
        $this->get(WhatsAppConnection::getUrl())->assertForbidden();
        Livewire::test(EditPost::class, ['record' => $this->makePost()->getRouteKey()])->assertActionHidden('whatsappChannel');
    }

    #[TestDox('Variante „channel“: /whatsapp lädt zum Kanal ein, Backend bietet fertigen Text zum Kopieren')]
    public function test_channel_mode(): void
    {
        config(['whatsapp.mode' => 'channel', 'whatsapp.channel_url' => self::CHANNEL]);

        $this->get('/whatsapp')->assertOk()->assertSee(self::CHANNEL, false)->assertSee('Nicht mehr folgen')
            ->assertDontSee('kanal-qr.svg')->assertDontSee('START');
        $this->get('/webhooks/whatsapp')->assertNotFound();
        $this->assertArrayHasKey('whatsapp|global|', RoleCatalog::all());

        $this->actingAs($this->verwaltung());
        $this->get(WhatsAppCampaignResource::getUrl('index'))->assertForbidden();
        Livewire::test(EditPost::class, ['record' => $this->makePost()->getRouteKey()])
            ->assertActionVisible('whatsappChannel')
            ->mountAction('whatsappChannel')
            ->assertActionMounted('whatsappChannel');
        $this->assertStringContainsString('Diözesanlager 2027', view('filament.actions.whatsapp-channel-copy', [
            'text' => WhatsAppChannelText::forPost(Post::query()->firstOrFail()), 'channelUrl' => self::CHANNEL,
        ])->render());
        Livewire::test(EditEvent::class, ['record' => $this->event()->getRouteKey()])->assertActionVisible('whatsappChannel');
    }

    #[TestDox('Kanal-Text: Titel fett, Kurztext ohne Formatzeichen, Link zur Website')]
    public function test_channel_text(): void
    {
        $post = WhatsAppChannelText::forPost($this->makePost());
        $this->assertStringStartsWith('*Diözesanlager 2027: Anmeldung offen*', $post);
        $this->assertStringContainsString('Vom 1. bis 10. August am Hunsrück.', $post);
        $this->assertStringEndsWith('/aktuelles/dioezesanlager-2027', $post);

        $event = WhatsAppChannelText::forEvent($this->event());
        $this->assertStringContainsString('📍 Trier', $event);
        $this->assertStringEndsWith('/veranstaltungen/woelflingstag', $event);
    }

    #[TestDox('Variante „channel“ ohne Kanal-Adresse: keine Seite')]
    public function test_channel_without_url(): void
    {
        config(['whatsapp.mode' => 'channel', 'whatsapp.channel_url' => null]);

        $this->get('/whatsapp')->assertNotFound();
    }
}
