<?php

namespace Tests\Feature\Dashboard;

use App\Filament\Widgets\WasStehtAn as Widget;
use App\Models\ContactMessage;
use App\Models\Event;
use App\Models\Post;
use App\Models\User;
use App\Support\Features;
use App\Support\RechtePruefung;
use App\Support\WasStehtAn;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * „Was steht an“ auf der Startseite des Backends.
 */
class WasStehtAnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    private function user(string $email, string $role, string $scope = 'global', ?string $key = null): User
    {
        $user = User::query()->create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'status' => 'active']);
        $user->roleAssignments()->create(['role' => $role, 'scope_type' => $scope, 'scope_key' => $key]);

        return $user->fresh();
    }

    #[TestDox('Verwaltung: Freigaben, Nachrichten, hängende Anmeldungen, nächste Veranstaltungen; Dringendes zuerst; jeweils mit Link')]
    public function test_verwaltung(): void
    {
        Features::setzen(Features::NACHRICHTEN, true);
        $buero = $this->user('buero@example.org', 'verwaltung');
        $this->actingAs($buero);
        RechtePruefung::abschliessen($buero); // eigener Test: RechtePruefungTest

        $this->assertSame([], WasStehtAn::fuer($buero));
        Livewire::test(Widget::class)->assertSee('Alles erledigt');

        Post::query()->create(['title' => 'Bericht', 'slug' => 'bericht', 'status' => 'review', 'teaser' => 'Kurz', 'body' => '<p>x</p>', 'author_id' => $buero->id]);
        ContactMessage::query()->create(['thema' => 'Allgemein', 'name' => 'Kim', 'email' => 'kim@example.org', 'nachricht' => 'Hallo, eine Frage', 'status' => 'neu', 'empfaenger' => ['info@example.org'], 'zugestellt' => true]);
        $event = Event::query()->create(['title' => 'Wölflingstag', 'slug' => 'wt', 'starts_at' => now()->addDays(5), 'status' => 'open', 'capacity' => 30]);
        $alt = $event->registrations()->create(['status' => 'pending', 'first_name' => 'A', 'last_name' => 'B', 'email' => 'a@example.org', 'consent_text_version' => 'v1', 'consent_at' => now()]);
        $alt->forceFill(['created_at' => now()->subDays(2)])->save();
        $event->registrations()->create(['status' => 'pending', 'first_name' => 'C', 'last_name' => 'D', 'email' => 'c@example.org', 'consent_text_version' => 'v1', 'consent_at' => now()]);

        $titel = array_column(WasStehtAn::fuer($buero), 'titel');
        $this->assertSame(['1 Beitrag wartet auf Freigabe', '1 Anmeldung seit über 24 Stunden unbestätigt', '1 neue Nachricht', 'Wölflingstag'], $titel);

        Livewire::test(Widget::class)->assertSee('Was steht an')->assertSee('1 Beitrag wartet auf Freigabe')
            ->assertSee('0/30 angemeldet')->assertSee('relation=0', false);
    }

    #[TestDox('Rollen bestimmen, was erscheint: Autor:in ohne Freigaberecht sieht nichts davon')]
    public function test_rollen(): void
    {
        $autor = $this->user('autor@example.org', 'autor', 'own');
        Post::query()->create(['title' => 'Bericht', 'slug' => 'bericht', 'status' => 'review', 'teaser' => 'Kurz', 'body' => '<p>x</p>', 'author_id' => $autor->id]);
        $this->actingAs($autor);
        $this->assertSame([], WasStehtAn::fuer($autor));
        Livewire::test(Widget::class)->assertSee('Alles erledigt');
    }
}
