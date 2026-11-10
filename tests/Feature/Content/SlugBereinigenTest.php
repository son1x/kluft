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
    }
}
