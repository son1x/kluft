<?php

namespace Tests\Feature\Instagram;

use App\Filament\Pages\Integrations;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Models\AuditLog;
use App\Models\IntegrationToken;
use App\Models\Media;
use App\Models\Post;
use App\Models\SocialPost;
use App\Models\User;
use App\Notifications\InstagramTokenProblemNotification;
use App\Services\Instagram\InstagramImage;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Instagram-Integration, Pflichtenheft 6.4 (gegen eine simulierte Graph API).
 */
class InstagramTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://graph.instagram.com/v23.0';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Filament::setCurrentPanel('admin');
    }

    private function user(string $email, string $role, string $scopeType = 'global', ?string $scopeKey = null): User
    {
        $user = User::query()->create(['name' => $email, 'email' => $email, 'status' => 'active']);
        $user->roleAssignments()->create(['role' => $role, 'scope_type' => $scopeType, 'scope_key' => $scopeKey]);

        return $user->fresh();
    }

    private function connect(): IntegrationToken
    {
        return IntegrationToken::query()->create([
            'provider' => 'instagram', 'account_id' => '1789', 'account_name' => 'dpsgtrier',
            'access_token' => 'IGAA-geheim', 'expires_at' => now()->addDays(50), 'refreshed_at' => now()->subDays(10),
        ]);
    }

    private function blogPost(array $attrs = []): Post
    {
        // Hochformat 600x1000 → muss auf 4:5 zugeschnitten werden.
        $image = imagecreatetruecolor(600, 1000);
        ob_start();
        imagepng($image);
        Storage::disk('public')->put('media/bild.png', ob_get_clean());
        $media = Media::query()->create(['path' => 'media/bild.png', 'mime' => 'image/png', 'size' => 100, 'alt_text' => 'Lager', 'credit' => 'DPSG Trier']);

        return Post::query()->create([
            'title' => 'Wölflingslager', 'slug' => 'lager', 'teaser' => 'Eine Woche Abenteuer.', 'body' => '<p>x</p>',
            'stage' => 'woelflinge', 'scope' => 'stage', 'status' => 'published', 'published_at' => now()->subHour(),
            'cover_media_id' => $media->id, ...$attrs,
        ]);
    }

    private function fakeGraph(string $status = 'FINISHED'): void
    {
        Http::fake([
            self::API.'/1789/media_publish' => Http::response(['id' => 'M42']),
            self::API.'/1789/media' => Http::response(['id' => 'C7']),
            self::API.'/C7*' => Http::response(['status_code' => $status, 'id' => 'C7']),
            self::API.'/M42*' => Http::response(['permalink' => 'https://www.instagram.com/p/abc/', 'id' => 'M42']),
        ]);
    }

    #[TestDox('Teilen veröffentlicht ein JPEG und merkt sich den Instagram-Link')]
    public function test_sharing_publishes_jpeg_and_stores_permalink(): void
    {
        $this->connect();
        $this->fakeGraph();
        $post = $this->blogPost();
        $this->actingAs($this->user('woe@dpsg-trier.de', 'stufenadmin', 'stage', 'woelflinge'));

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->assertActionVisible('instagram')
            ->assertActionEnabled('instagram')
            ->callAction('instagram', ['caption' => 'Wölflingslager – Link in der Bio', 'rights' => true])
            ->assertHasNoActionErrors()
            ->assertNotified('Auf Instagram veröffentlicht');

        $social = SocialPost::query()->sole();
        $this->assertSame('published', $social->status);
        $this->assertSame('M42', $social->external_id);
        $this->assertSame('https://www.instagram.com/p/abc/', $social->permalink);
        $this->assertSame(1, AuditLog::query()->where('action', 'instagram.publish')->count());

        Http::assertSent(function (Request $request) {
            if ($request->url() !== self::API.'/1789/media') {
                return false;
            }
            $this->assertSame('Wölflingslager – Link in der Bio', $request['caption']);
            $this->assertStringEndsWith('.jpg', $request['image_url']);

            return true;
        });
        // Aufbereitetes JPEG nach Erfolg wieder gelöscht.
        $this->assertSame([], Storage::disk('public')->files('instagram'));
    }

    #[TestDox('Bild wird auf ein von Instagram erlaubtes Seitenverhältnis zugeschnitten')]
    public function test_image_is_cropped_to_allowed_aspect_ratio(): void
    {
        $path = app(InstagramImage::class)->prepare($this->blogPost()->coverMedia);
        [$w, $h, $type] = getimagesizefromstring(Storage::disk('public')->get($path));

        $this->assertSame(IMAGETYPE_JPEG, $type);
        $this->assertSame([600, 750], [$w, $h]);
    }

    #[TestDox('Fehler werden angezeigt, ohne den Zugangsschlüssel preiszugeben')]
    public function test_errors_are_shown_without_leaking_the_token(): void
    {
        $this->connect();
        Http::fake([self::API.'/1789/media' => Http::response(['error' => ['message' => 'Invalid image aspect ratio']], 400)]);
        $post = $this->blogPost();
        $this->actingAs($this->user('buero@dpsg-trier.de', 'verwaltung'));

        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->callAction('instagram', ['caption' => 'Test', 'rights' => true])
            ->assertNotified('Instagram-Veröffentlichung fehlgeschlagen');

        $social = SocialPost::query()->sole();
        $this->assertSame('failed', $social->status);
        $this->assertStringContainsString('Invalid image aspect ratio', $social->error);
        $this->assertStringNotContainsString('IGAA-geheim', $social->error);
        $this->assertStringNotContainsString('IGAA-geheim', (string) DB::table('integration_tokens')->value('access_token'));
    }

    #[TestDox('Nur Berechtigte können teilen, und nur wenn alles bereit ist')]
    public function test_permissions_and_preconditions(): void
    {
        $post = $this->blogPost();
        $autor = $this->user('autor@dpsg-trier.de', 'autor', 'own', 'woelflinge');
        $rover = $this->user('rover@dpsg-trier.de', 'stufenadmin', 'stage', 'rover');
        $vorstand = $this->user('vorstand@dpsg-trier.de', 'vorstand');

        $this->assertFalse($autor->can('shareToInstagram', $post));
        $this->assertFalse($rover->can('shareToInstagram', $post));
        $this->assertTrue($vorstand->can('shareToInstagram', $post));
        $this->assertFalse($vorstand->can('shareToInstagram', $this->blogPost(['slug' => 'entwurf', 'status' => 'draft'])));

        // Ohne Verbindung sichtbar, aber gesperrt; Rechte-Checkbox ist Pflicht.
        $this->actingAs($vorstand);
        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])->assertActionDisabled('instagram');
        $this->connect();
        $this->fakeGraph();
        Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
            ->callAction('instagram', ['caption' => 'Test', 'rights' => false])
            ->assertHasActionErrors(['rights']);
        $this->assertSame(0, SocialPost::query()->count());
    }

    #[TestDox('Nur Admins hinterlegen den Instagram-Zugang; er wird geprüft')]
    public function test_only_admins_manage_the_token_and_it_is_validated(): void
    {
        $this->actingAs($this->user('buero@dpsg-trier.de', 'verwaltung'))->get('/admin/integrations')->assertForbidden();

        $admin = $this->user('technik@dpsg-trier.de', 'admin');
        $this->actingAs($admin)->get('/admin/integrations')->assertOk()->assertSee('Noch nicht verbunden');

        Http::fake([self::API.'/me*' => Http::sequence()
            ->push(['error' => ['message' => 'Invalid OAuth access token']], 400)
            ->push(['user_id' => '1789', 'username' => 'dpsgtrier'])]);

        Livewire::test(Integrations::class)->fillForm(['access_token' => 'falsch'])->call('saveInstagram')
            ->assertNotified('Token wurde nicht gespeichert');
        $this->assertNull(IntegrationToken::for('instagram'));

        Livewire::test(Integrations::class)->fillForm(['access_token' => 'IGAA-geheim'])->call('saveInstagram')
            ->assertNotified('Instagram verbunden: @dpsgtrier');
        $token = IntegrationToken::for('instagram');
        $this->assertSame('1789', $token->account_id);
        $this->assertSame('IGAA-geheim', $token->access_token);
        $this->get('/admin/integrations')->assertSee('@dpsgtrier')->assertDontSee('IGAA-geheim');
    }

    #[TestDox('Zugang wird verlängert; bei Problemen werden Admins benachrichtigt')]
    public function test_token_refresh_and_admin_alert_on_failure(): void
    {
        Notification::fake();
        $admin = $this->user('technik@dpsg-trier.de', 'admin');
        $this->connect();

        Http::fake(['graph.instagram.com/refresh_access_token*' => Http::sequence()
            ->push(['access_token' => 'IGAA-neu', 'token_type' => 'bearer', 'expires_in' => 5184000])
            ->push(['error' => ['message' => 'Session has expired']], 400)]);

        $this->artisan('instagram:refresh-token')->assertSuccessful();
        $token = IntegrationToken::for('instagram');
        $this->assertSame('IGAA-neu', $token->access_token);
        $this->assertTrue($token->expires_at->between(now()->addDays(59), now()->addDays(61)));

        // Jünger als 24 h: kein Aufruf ohne --force.
        $this->artisan('instagram:refresh-token')->assertSuccessful();
        Http::assertSentCount(1);

        $this->artisan('instagram:refresh-token --force')->assertFailed();
        $this->assertStringContainsString('Session has expired', IntegrationToken::for('instagram')->last_error);
        Notification::assertSentTo($admin, InstagramTokenProblemNotification::class);
    }
}
