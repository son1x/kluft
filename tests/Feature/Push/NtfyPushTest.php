<?php

namespace Tests\Feature\Push;

use App\Filament\Pages\PushSettings;
use App\Mail\EventRegistrationMail;
use App\Models\Event;
use App\Models\Post;
use App\Models\User;
use App\Notifications\RolesGrantedNotification;
use App\Support\Ausbau;
use App\Support\PushEvents;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Push-Nachrichten über ntfy: je Person freiwillig, nur passend zu den
 * Rechten, ohne personenbezogene Daten.
 */
class NtfyPushTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        // Push gibt es erst im Vollausbau (Stammes-Instanzen starten kleiner).
        config(['dpsg.ausbau' => Ausbau::VOLL, 'services.ntfy.server' => 'https://ntfy.example.org', 'services.ntfy.token' => null]);
        Http::fake(['ntfy.example.org*' => Http::response(['id' => 'x'])]);
        Mail::fake();
    }

    /**
     * @param  list<string>|null  $events  null = kein Push
     */
    private function user(string $email, string $role, string $scopeType = 'global', ?string $scopeKey = null, ?array $events = null): User
    {
        $user = User::query()->create(['name' => $email, 'email' => $email, 'status' => 'active']);
        $user->roleAssignments()->create(['role' => $role, 'scope_type' => $scopeType, 'scope_key' => $scopeKey]);
        if ($events !== null) {
            $user->forceFill(['ntfy_topic' => 'thema-'.str_replace(['@', '.'], '-', $email), 'ntfy_events' => $events])->save();
        }

        return $user->fresh();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pushes(): array
    {
        return Http::recorded()->map(fn (array $pair) => $pair[0]->data())->values()->all();
    }

    private function topics(): array
    {
        $topics = array_column($this->pushes(), 'topic');
        sort($topics);

        return $topics;
    }

    #[TestDox('Kanal veröffentlicht JSON an den Server (mit Token, falls gesetzt)')]
    public function test_channel_publishes_json(): void
    {
        config(['services.ntfy.token' => 'tk_geheim']);
        $user = $this->user('a@dpsg-trier.de', 'autor', 'own', null, [PushEvents::RECHTE]);

        $user->notify(new RolesGrantedNotification(['verwaltung|global|'], false, 'Büro'));

        Http::assertSent(fn (Request $r) => $r->url() === 'https://ntfy.example.org'
            && $r->hasHeader('Authorization', 'Bearer tk_geheim')
            && $r['topic'] === 'thema-a-dpsg-trier-de'
            && $r['title'] === 'Neue Rechte im Redaktionsbereich'
            && $r['message'] === 'Verwaltung'
            && $r['tags'] === ['key']);
    }

    #[TestDox('Ohne Abo oder ohne passende Rechte: kein Push')]
    public function test_no_push_without_subscription_or_rights(): void
    {
        $ohne = $this->user('ohne@dpsg-trier.de', 'autor', 'own');
        $ohne->notify(new RolesGrantedNotification(['autor|own|'], false, 'Büro'));

        // Autor:in hat „System“ abonniert, darf es aber nicht bekommen.
        $autor = $this->user('autor@dpsg-trier.de', 'autor', 'own', null, [PushEvents::SYSTEM, PushEvents::ANMELDUNGEN]);
        $this->assertFalse($autor->wantsPush(PushEvents::SYSTEM));
        $this->assertFalse($autor->wantsPush(PushEvents::ANMELDUNGEN));

        Http::assertNothingSent();
    }

    #[TestDox('Anmeldung: Push nur an Abonnierende mit Zugriff auf die Veranstaltung, ohne Teilnehmerdaten')]
    public function test_registration_push_respects_scope_and_contains_no_personal_data(): void
    {
        $this->user('woe@dpsg-trier.de', 'stufenadmin', 'stage', 'woelflinge', [PushEvents::ANMELDUNGEN]);
        $this->user('biber@dpsg-trier.de', 'stufenadmin', 'stage', 'biber', [PushEvents::ANMELDUNGEN]);
        $this->user('buero@dpsg-trier.de', 'verwaltung', 'global', null, [PushEvents::BEITRAEGE_NEU]);
        $event = Event::query()->create([
            'title' => 'Wölflingstag', 'slug' => 'woelflingstag', 'stage' => 'woelflinge', 'status' => 'open', 'capacity' => 30,
            'starts_at' => now()->addMonth(), 'ends_at' => now()->addMonth()->addHours(6), 'location' => 'Trier',
            'form_fields' => ['birth_year' => 'optional'],
        ]);

        $this->post('/veranstaltungen/woelflingstag/anmeldung', ['first_name' => 'Mia', 'last_name' => 'Muster', 'email' => 'eltern@example.org', 'birth_year' => now()->year - 20, 'consent' => '1']);
        Http::assertNothingSent(); // erst nach Bestätigung
        $token = null;
        Mail::assertQueued(EventRegistrationMail::class, function (EventRegistrationMail $m) use (&$token) {
            $token = $m->token;

            return true;
        });
        $this->post('/anmeldung/bestaetigen/'.$token);

        $this->assertSame(['thema-woe-dpsg-trier-de'], $this->topics());
        $push = $this->pushes()[0];
        $this->assertSame('Neue Anmeldung', $push['title']);
        $this->assertSame('Wölflingstag: 1/30 Plätze belegt', $push['message']);
        $this->assertStringContainsString('/admin/events/', $push['click']);
        $this->assertStringNotContainsString('Mia', json_encode($push));
        $this->assertStringNotContainsString('eltern@', json_encode($push));
    }

    #[TestDox('Beitrag zur Freigabe: Push an Freigebende des Bereichs, nicht an die Autorin; veröffentlicht: an Abonnierende')]
    public function test_post_pushes(): void
    {
        $autorin = $this->user('autorin@dpsg-trier.de', 'autor', 'own', 'woelflinge', [PushEvents::BEITRAEGE_FREIGABE, PushEvents::BEITRAEGE_NEU]);
        $this->user('woe@dpsg-trier.de', 'stufenadmin', 'stage', 'woelflinge', [PushEvents::BEITRAEGE_FREIGABE]);
        $this->user('biber@dpsg-trier.de', 'stufenadmin', 'stage', 'biber', [PushEvents::BEITRAEGE_FREIGABE]);
        $this->user('leser@dpsg-trier.de', 'autor', 'own', null, [PushEvents::BEITRAEGE_NEU]);

        $this->actingAs($autorin);
        $post = Post::query()->create(['title' => 'Rückblick Wölflingstag', 'slug' => 'rueckblick', 'teaser' => 'Kurz', 'body' => '<p>x</p>', 'stage' => 'woelflinge', 'status' => 'review', 'author_id' => $autorin->id]);

        $this->assertSame(['thema-woe-dpsg-trier-de'], $this->topics());
        $this->assertSame('Beitrag wartet auf Freigabe', $this->pushes()[0]['title']);
        $this->assertSame('Rückblick Wölflingstag', $this->pushes()[0]['message']);

        Http::fake(['ntfy.example.org*' => Http::response(['id' => 'y'])]);
        $this->actingAs(User::query()->where('email', 'woe@dpsg-trier.de')->firstOrFail());
        $post->update(['status' => 'published', 'published_at' => now()->subMinute()]);

        $this->assertSame(['thema-autorin-dpsg-trier-de', 'thema-leser-dpsg-trier-de'], $this->topics());
        $this->assertStringEndsWith('/aktuelles/rueckblick', $this->pushes()[0]['click']);

        Http::fake(['ntfy.example.org*' => Http::response(['id' => 'z'])]);
        $post->update(['title' => 'Rückblick (korrigiert)']);
        Http::assertNothingSent();
    }

    #[Group('dv-trier')]

    #[TestDox('Einstellungsseite: einschalten, nur erlaubte Ereignisse speichern, Test senden, ausschalten')]
    public function test_settings_page(): void
    {
        $user = $this->user('woe@dpsg-trier.de', 'stufenadmin', 'stage', 'woelflinge');
        $this->actingAs($user);

        $page = Livewire::test(PushSettings::class)->assertSee('Einschalten')->call('enable');
        $user->refresh();
        $this->assertMatchesRegularExpression('/^dpsg-trier-[a-z0-9]{28}$/', (string) $user->ntfy_topic);
        $this->assertEqualsCanonicalizing(array_keys(PushEvents::availableFor($user)), $user->ntfy_events);
        $this->assertNotContains(PushEvents::SYSTEM, $user->ntfy_events);

        // Nicht erlaubte Ereignisse (manipulierter Request) werden abgelehnt.
        $page->assertSee($user->ntfy_topic)
            ->set('data.events', [PushEvents::ANMELDUNGEN, PushEvents::SYSTEM])
            ->call('save')
            ->assertHasErrors();
        $page->set('data.events', [PushEvents::ANMELDUNGEN])->call('save')->assertHasNoErrors();
        $this->assertSame([PushEvents::ANMELDUNGEN], $user->fresh()->ntfy_events);

        $page->call('sendTest');
        Http::assertSent(fn (Request $r) => $r['topic'] === $user->ntfy_topic && $r['title'] === 'Test von dpsg-trier.de');

        $page->call('disable');
        $this->assertNull($user->fresh()->ntfy_topic);
        $this->assertDatabaseHas('audit_logs', ['action' => 'push.ausgeschaltet', 'user_id' => $user->id]);
    }

    #[TestDox('Push-Dienst nicht erreichbar: Anmeldung und Mail laufen trotzdem')]
    public function test_push_failure_never_breaks_anything(): void
    {
        Http::fake(['ntfy.example.org*' => Http::response('kaputt', 500)]);
        $user = $this->user('a@dpsg-trier.de', 'autor', 'own', null, [PushEvents::RECHTE]);

        $user->notify(new RolesGrantedNotification(['autor|own|'], false, 'Büro'));

        Http::assertSentCount(1);
        $this->assertTrue(true);
    }
}
