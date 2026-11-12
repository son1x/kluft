<?php

namespace Tests\Feature\Content;

use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Models\Event;
use App\Models\User;
use App\Support\Slug;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Unsichtbare Zeichen im URL-Pfad (aus Word/Outlook eingefügt, Leerzeichen
 * am Ende) werden bereinigt statt abgelehnt.
 */
class SlugBereinigenTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Bereinigen: Leerzeichen, geschützte Bindestriche, Gedankenstriche, weiche Trennzeichen; Umlaute bleiben')]
    public function test_bereinigen(): void
    {
        $this->assertSame('spiritueller-hike-2027', Slug::bereinigen(" spiritueller\u{2011}hike\u{2013}2027\u{00AD} "));
        $this->assertSame('spiritueller-hike-2027', Slug::bereinigen('spiritueller hike  2027'));
        $this->assertSame('über-uns', Slug::bereinigen('über-uns'));
        $this->assertSame('a_b-c', Slug::bereinigen("a_b\u{200B}-c/"));
    }

    #[TestDox('Bereinigen: Richtungsmarken aus Teams/Outlook, Steuerzeichen, Apostroph, Schrägstrich, Emoji – danach immer gültig')]
    public function test_alles_gueltig(): void
    {
        $this->assertSame('spirihike', Slug::bereinigen("\u{2066}spirihike\u{2069}"));
        $this->assertSame('spirihike', Slug::bereinigen("spirihike\u{200E}"));
        $this->assertSame('spirihike', Slug::bereinigen("\u{202A}spiri\u{200B}hike\u{202C}"));
        $this->assertSame('spiri-hike', Slug::bereinigen("spiri\thike"));
        $this->assertSame('spiri-hike', Slug::bereinigen("spiri'hike"));
        $this->assertSame('lager-2027', Slug::bereinigen('lager/2027'));
        $this->assertSame('zeltlager', Slug::bereinigen('zeltlager 🏕️'));
        $this->assertSame('über-uns', Slug::bereinigen('über-uns'));

        foreach (["\u{2066}a\u{2069}", "x\u{FEFF}y", 'a.b', 'a&b', "a\u{00A0}b", '😀a😀'] as $wert) {
            $this->assertMatchesRegularExpression('/\A[\pL\pM\pN_-]+\z/u', Slug::bereinigen($wert), json_encode($wert));
        }
    }

    #[TestDox('Veranstaltung anlegen: eingefügter Slug mit unsichtbaren Zeichen wird gespeichert, bereinigt')]
    public function test_veranstaltung(): void
    {
        Filament::setCurrentPanel('admin');
        $user = User::query()->create(['name' => 'Lisa', 'email' => 'lisa@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);
        $this->actingAs($user->fresh());

        Livewire::test(CreateEvent::class)
            ->fillForm(['title' => 'Spiritueller Hike', 'slug' => "spiritueller\u{2011}hike\u{2011}2027 ", 'starts_at' => '2027-05-05 18:00', 'status' => 'open', 'retention_days' => 60])
            ->assertFormSet(['slug' => 'spiritueller-hike-2027'])
            ->call('create')->assertHasNoFormErrors();
        $this->assertSame('spiritueller-hike-2027', Event::query()->sole()->slug);

        // Aus Teams kopiert: unsichtbare Richtungsmarken um den Text – mit Hinweis.
        Livewire::test(CreateEvent::class)
            ->fillForm(['title' => 'Spiritueller Hike', 'slug' => "\u{2066}spirihike\u{2069}", 'starts_at' => '2027-05-05 18:00', 'status' => 'open', 'retention_days' => 60])
            ->assertNotified('Unsichtbare Zeichen entfernt')
            ->call('create')->assertHasNoFormErrors();
        $this->assertTrue(Event::query()->where('slug', 'spirihike')->exists());
    }

    #[TestDox('Slug aus dem Titel (nichts von Hand eingetragen) wird gespeichert – Ursache von Lisas Fehler')]
    public function test_slug_aus_titel(): void
    {
        Filament::setCurrentPanel('admin');
        $user = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'superadmin', 'scope_type' => 'global']);
        $this->actingAs($user->fresh());

        // Erst den Titel (der Vorschlag füllt den Slug), dann der Rest – wie im Browser.
        Livewire::test(CreateEvent::class)
            ->fillForm(['title' => 'Spiritueller Hike Test'])
            ->assertFormSet(['slug' => 'spiritueller-hike-test'])
            ->fillForm(['starts_at' => '2027-05-05 18:00', 'status' => 'open', 'retention_days' => 60])
            ->call('create')->assertHasNoFormErrors();
        $this->assertSame('spiritueller-hike-test', Event::query()->sole()->slug);

        // Auch ein Stringable im Formularzustand besteht die Prüfung.
        $this->assertSame('a-b', Slug::bereinigen(str('a b')));
    }
}
