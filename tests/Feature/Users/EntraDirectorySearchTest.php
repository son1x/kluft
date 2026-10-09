<?php

namespace Tests\Feature\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\FeatureSwitch;
use App\Models\User;
use App\Services\DirectoryUnavailable;
use App\Services\EntraDirectory;
use App\Support\Features;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Suche im Entra-Verzeichnis über Microsoft Graph (Pflichtenheft 5.6,
 * F-USR-02): delegiert, User.ReadBasic.All, Token nur in der Sitzung.
 */
class EntraDirectorySearchTest extends TestCase
{
    use RefreshDatabase;

    private const OID = '11111111-2222-3333-4444-555555555555';

    private const PERSON = '99999999-8888-7777-6666-555555555555';

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        // Verzeichnissuche gehört zur Anmeldung mit Microsoft 365; Instanzen
        // mit E-Mail-Code (z. B. Saar-Hochwald) legen sonst Code-Zugänge an.
        FeatureSwitch::query()->create(['key' => Features::CODE_LOGIN, 'enabled' => false]);
        Cache::forget('features.schalter');
    }

    private function verwaltung(): User
    {
        $user = User::query()->create(['name' => 'Büro', 'email' => 'buero@dpsg-trier.de', 'upn' => 'buero@dpsg-trier.de', 'entra_oid' => self::OID, 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global', 'scope_key' => null]);

        return $user->fresh();
    }

    private function fakeGraph(): void
    {
        Http::fake([
            'graph.microsoft.com/v1.0/users/'.self::PERSON.'*' => Http::response(['id' => self::PERSON, 'displayName' => 'Till Mang', 'mail' => 'T.Mang@dpsg-trier.de', 'userPrincipalName' => 't.mang@dpsg-trier.de']),
            'graph.microsoft.com/v1.0/users*' => Http::response(['value' => [
                ['id' => self::PERSON, 'displayName' => 'Till Mang', 'mail' => 'T.Mang@dpsg-trier.de', 'userPrincipalName' => 't.mang@dpsg-trier.de'],
            ]]),
        ]);
    }

    #[TestDox('Verbinden fragt bei Microsoft nur zusätzlich User.ReadBasic.All an; normaler Login bleibt ohne')]
    public function test_connect_requests_directory_scope_only_in_separate_step(): void
    {
        $this->actingAs($this->verwaltung());

        $connect = $this->get('/auth/entra/verzeichnis')->assertRedirect();
        $this->assertStringContainsString('User.ReadBasic.All', urldecode((string) $connect->headers->get('Location')));
        $this->assertSame('verzeichnis', session('entra.flow'));

        auth()->logout();
        Socialite::forgetDrivers(); // neuer Request
        $login = $this->get('/auth/entra/redirect');
        $this->assertStringNotContainsString('User.ReadBasic.All', urldecode((string) $login->headers->get('Location')));
    }

    #[TestDox('Verbinden nur für Personen, die Konten anlegen dürfen')]
    public function test_connect_requires_user_management_rights(): void
    {
        $this->get('/auth/entra/verzeichnis')->assertForbidden();

        $autor = User::query()->create(['name' => 'A', 'email' => 'a@dpsg-trier.de', 'entra_oid' => self::PERSON, 'status' => 'active']);
        $this->actingAs($autor)->get('/auth/entra/verzeichnis')->assertForbidden();
    }

    #[TestDox('Rückkehr von Microsoft: Token nur für das eigene Konto in der Sitzung, kein neuer Login')]
    public function test_callback_stores_token_for_own_account_only(): void
    {
        $user = $this->verwaltung();
        $this->actingAs($user)->withSession(['entra.flow' => 'verzeichnis']);
        Socialite::shouldReceive('driver->user')->andReturn((new SocialiteUser)->map(['id' => self::OID])->setToken('graph-token')->setExpiresIn(3600));

        $this->get('/auth/entra/callback')->assertRedirect('/admin/users/create');
        $this->assertTrue(app(EntraDirectory::class)->connected());
        $this->assertSame(1, User::query()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'entra.verzeichnis.verbunden', 'user_id' => $user->id]);
    }

    #[TestDox('Anderes Microsoft-Konto oder Ablehnung durch Microsoft: keine Verbindung, kein Fehler')]
    public function test_callback_rejects_other_account_and_errors(): void
    {
        $this->actingAs($this->verwaltung())->withSession(['entra.flow' => 'verzeichnis']);
        Socialite::shouldReceive('driver->user')->andReturn((new SocialiteUser)->map(['id' => self::PERSON])->setToken('fremd')->setExpiresIn(3600));
        $this->get('/auth/entra/callback')->assertRedirect('/admin/users/create');
        $this->assertFalse(app(EntraDirectory::class)->connected());

        $this->withSession(['entra.flow' => 'verzeichnis'])
            ->get('/auth/entra/callback?error=consent_required&error_description=x')
            ->assertRedirect('/admin/users/create');
        $this->assertFalse(app(EntraDirectory::class)->connected());
    }

    #[TestDox('Suche schickt $search mit ConsistencyLevel und liefert nur Name, E-Mail, Konto-ID')]
    public function test_search_queries_graph_with_basic_fields(): void
    {
        $this->fakeGraph();
        $directory = app(EntraDirectory::class);
        $directory->remember('graph-token', 3600);

        $this->assertSame([], $directory->search('M'));
        $people = $directory->search('Ma"ng');

        $this->assertSame([['id' => self::PERSON, 'name' => 'Till Mang', 'email' => 't.mang@dpsg-trier.de', 'upn' => 't.mang@dpsg-trier.de']], $people);
        Http::assertSent(fn (Request $r) => $r->hasHeader('ConsistencyLevel', 'eventual')
            && $r->hasHeader('Authorization', 'Bearer graph-token')
            && str_contains(urldecode($r->url()), '"displayName:Mang"')
            && str_contains(urldecode($r->url()), '$select=id,displayName,mail,userPrincipalName'));
    }

    #[TestDox('Abgelaufener Token (401) trennt die Verbindung')]
    public function test_expired_token_disconnects(): void
    {
        Http::fake(['graph.microsoft.com/*' => Http::response(['error' => ['code' => 'InvalidAuthenticationToken']], 401)]);
        $directory = app(EntraDirectory::class);
        $directory->remember('alt', 3600);

        try {
            $directory->search('Mang');
            $this->fail('DirectoryUnavailable erwartet');
        } catch (DirectoryUnavailable) {
            $this->assertFalse($directory->connected());
        }
    }

    #[TestDox('Anlegen aus dem Verzeichnis: Angaben kommen von Microsoft, Konto ist direkt verknüpft')]
    public function test_create_from_directory_links_account(): void
    {
        $this->fakeGraph();
        $this->actingAs($this->verwaltung());
        app(EntraDirectory::class)->remember('graph-token', 3600);

        Livewire::test(CreateUser::class)
            ->assertSee('Im M365-Verzeichnis suchen')
            ->fillForm(['directory_oid' => self::PERSON, 'name' => 'Manipuliert', 'email' => 'falsch@example.org', 'status' => 'active'])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::query()->where('entra_oid', self::PERSON)->firstOrFail();
        $this->assertSame('Till Mang', $created->name);
        $this->assertSame('t.mang@dpsg-trier.de', $created->email);
        $this->assertSame('t.mang@dpsg-trier.de', $created->upn);
    }

    #[TestDox('Ohne Verbindung: Link zum Verbinden, Anlegen per E-Mail funktioniert wie bisher')]
    public function test_without_connection_manual_entry_still_works(): void
    {
        $this->actingAs($this->verwaltung());

        Livewire::test(CreateUser::class)
            ->assertSee('Mit Microsoft verbinden und suchen')
            ->fillForm(['name' => 'Hand', 'email' => 'hand@dpsg-trier.de', 'status' => 'active'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertNull(User::query()->where('email', 'hand@dpsg-trier.de')->value('entra_oid'));
    }
}
