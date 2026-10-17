<?php

namespace Tests\Feature\Instanzen;

use App\Filament\Pages\Ausbaustufe;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Ausbau;
use App\Support\Features;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Ausbaustufen: Visitenkarte, Gruppen, Termine & Aktuelles, Anmeldungen,
 * Vollausbau. Kluft läuft immer; die Stufe bestimmt, was erscheint.
 */
class AusbauTest extends TestCase
{
    use RefreshDatabase;

    private function seite(string $slug, string $titel, array $attrs = []): Page
    {
        return Page::query()->create([
            'title' => $titel, 'slug' => $slug, 'body' => '<p>Text zu '.$titel.'</p>',
            'status' => 'published', 'published_at' => now()->subDay(), ...$attrs,
        ]);
    }

    private function veranstaltung(): Event
    {
        return Event::query()->create([
            'title' => 'Sommerlager', 'slug' => 'sommerlager', 'status' => 'open', 'location' => 'Zeltplatz',
            'starts_at' => now()->addMonths(2)->setTime(10, 0), 'ends_at' => now()->addMonths(2)->addDays(5),
            'form_fields' => ['birth_year' => 'optional'],
        ]);
    }

    #[TestDox('Ohne Einstellung gilt der Standard der Instanz (DV/Bezirk: Vollausbau); gleich dem Standard entfernt die Einstellung')]
    public function test_standard(): void
    {
        config(['dpsg.ausbau' => null]);
        $this->assertSame(Ausbau::VOLL, Ausbau::stufe());
        Ausbau::setzen(Ausbau::TERMINE);
        $this->assertSame(Ausbau::TERMINE, Ausbau::stufe());
        Ausbau::setzen(Ausbau::VOLL);
        $this->assertNull(SiteSetting::query()->find('ausbau'));
        $this->assertSame(Ausbau::VOLL, Ausbau::stufe());

        config(['dpsg.ausbau' => Ausbau::GRUPPEN]);
        $this->assertSame(Ausbau::GRUPPEN, Ausbau::stufe());
    }

    #[TestDox('Visitenkarte: nur Startseite, Kontakt, Impressum und Datenschutz; keine Termine, Meldungen, Anmeldungen')]
    public function test_visitenkarte(): void
    {
        $this->seite('start', 'Willkommen');
        $this->seite('impressum', 'Impressum');
        $this->seite('ueber-uns', 'Über uns', ['show_in_nav' => true]);
        $this->seite('stufe-woelflinge', 'Wölflinge', ['stage' => 'woelflinge']);
        Ausbau::setzen(Ausbau::VISITENKARTE);

        $this->get('/')->assertOk()->assertDontSee('Über uns')->assertDontSee('Alle Meldungen')->assertDontSee('/meine-anmeldungen', false);
        $this->get('/impressum')->assertOk();
        foreach (['/ueber-uns', '/stufe-woelflinge', '/aktuelles', '/feed', '/kalender', '/veranstaltungen', '/meine-anmeldungen'] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->assertFalse(Features::enabled(Features::TEILNEHMENDE_EINTRAGEN));
        $this->assertTrue(Features::geschaltet(Features::TEILNEHMENDE_EINTRAGEN));

        Ausbau::setzen(Ausbau::GRUPPEN);
        $this->get('/ueber-uns')->assertOk();
        $this->get('/stufe-woelflinge')->assertOk()->assertDontSee('Alle Veranstaltungen');
        $this->get('/veranstaltungen')->assertNotFound();
    }

    #[TestDox('Termine ohne Online-Anmeldung, ab Stufe „Anmeldungen“ mit')]
    public function test_termine_und_anmeldungen(): void
    {
        Mail::fake();
        $event = $this->veranstaltung();
        Ausbau::setzen(Ausbau::TERMINE);

        $this->get('/veranstaltungen')->assertOk()->assertSee('Sommerlager');
        $this->get('/kalender')->assertOk();
        $this->assertFalse($event->fresh()->acceptsRegistrations());
        $this->get('/veranstaltungen/sommerlager')->assertOk()->assertDontSee('Verbindlich anmelden');
        $anmeldung = ['first_name' => 'Mia', 'last_name' => 'Muster', 'email' => 'mia@example.org', 'consent' => '1'];
        $this->post('/veranstaltungen/sommerlager/anmeldung', $anmeldung)->assertNotFound();

        Ausbau::setzen(Ausbau::ANMELDUNGEN);
        $this->get('/veranstaltungen/sommerlager')->assertOk()->assertSee('Verbindlich anmelden');
        $this->post('/veranstaltungen/sommerlager/anmeldung', $anmeldung)->assertSessionHasNoErrors()->assertRedirect();
        $this->get('/meine-anmeldungen')->assertOk();
        $this->assertTrue(Features::enabled(Features::TEILNEHMENDE_EINTRAGEN));
        $this->assertFalse(Features::enabled(Features::PUSH));
    }

    #[TestDox('Backend: nur Admins stellen die Stufe um; Änderung steht im Protokoll')]
    public function test_backend(): void
    {
        $verwaltung = User::query()->create(['name' => 'V', 'email' => 'v@example.org', 'status' => 'active']);
        $verwaltung->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global', 'scope_key' => null]);
        Filament::setCurrentPanel('admin');
        $this->actingAs($verwaltung->fresh());
        $this->get(Ausbaustufe::getUrl())->assertForbidden();

        $admin = User::query()->create(['name' => 'A', 'email' => 'a@example.org', 'status' => 'active']);
        $admin->roleAssignments()->create(['role' => 'admin', 'scope_type' => 'global', 'scope_key' => null]);
        $this->actingAs($admin->fresh());
        $this->get(Ausbaustufe::getUrl())->assertOk()->assertSee('Visitenkarte')->assertSee('Vollausbau');

        Livewire::test(Ausbaustufe::class)->set('data.stufe', Ausbau::GRUPPEN)->call('save')->assertNotified();
        $this->assertSame(Ausbau::GRUPPEN, Ausbau::stufe());
        $this->assertSame($admin->id, SiteSetting::query()->find('ausbau')?->updated_by);
        $this->assertTrue(AuditLog::query()->where('action', 'ausbau:2')->exists());
    }
}
