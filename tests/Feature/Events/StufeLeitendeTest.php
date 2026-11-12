<?php

namespace Tests\Feature\Events;

use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Schemas\EventForm;
use App\Models\Event;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * „Leiter*innen“ als Stufe bei Veranstaltungen: Zugriff für
 * Bildungsreferent*innen, Verwaltung, Admins und (lesend) den Vorstand.
 */
class StufeLeitendeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    private function user(string $rolle, string $typ = 'global', ?string $key = null): User
    {
        static $n = 0;
        $n++;
        $u = User::query()->create(['name' => ucfirst($rolle).$n, 'email' => "u{$n}@example.org", 'status' => 'active']);
        $u->roleAssignments()->create(['role' => $rolle, 'scope_type' => $typ, 'scope_key' => $key]);

        return $u->fresh();
    }

    private function event(?string $stufe): Event
    {
        return Event::query()->create(['title' => 'Leiterrunde '.$stufe, 'slug' => 'leiterrunde-'.($stufe ?? 'ohne'), 'starts_at' => now()->addMonth(), 'status' => 'open', 'stage' => $stufe]);
    }

    #[TestDox('Auswahl: „Leiter*innen“ für Rechte auf der ganzen Website, nicht für Stufenadmins')]
    public function test_auswahl(): void
    {
        $this->actingAs($this->user('admin'));
        $this->assertSame('Leiter*innen', EventForm::stageOptions()[Event::LEITENDE] ?? null);

        $this->actingAs($this->user('veranstaltungen', 'stage', 'woelflinge'));
        $this->assertArrayNotHasKey(Event::LEITENDE, EventForm::stageOptions());
    }

    #[TestDox('Anlegen mit „Leiter*innen“ klappt; Bezeichnung in Listen')]
    public function test_anlegen(): void
    {
        $this->actingAs($this->user('verwaltung'));
        Livewire::test(CreateEvent::class)
            ->fillForm(['title' => 'Leiterrunde', 'slug' => 'leiterrunde-2027', 'beginn_datum' => '2027-03-01', 'stage' => Event::LEITENDE, 'status' => 'open', 'retention_days' => 90])
            ->call('create')->assertHasNoFormErrors();
        $this->assertSame('Leiter*innen', Event::query()->sole()->scopeLabel());
    }

    #[TestDox('Zugriff auf Teilnehmende: Vorstand liest nur bei Leiter*innen, Stufenadmins nie, BR voll')]
    public function test_rechte(): void
    {
        $leitende = $this->event(Event::LEITENDE);
        $woe = $this->event('woelflinge');

        $vorstand = $this->user('vorstand');
        $this->assertTrue($vorstand->can('viewParticipants', $leitende));
        $this->assertFalse($vorstand->can('manageParticipants', $leitende));
        $this->assertFalse($vorstand->can('viewParticipants', $woe));

        $stufenadmin = $this->user('stufenadmin', 'stage', 'woelflinge');
        $this->assertFalse($stufenadmin->can('viewParticipants', $leitende));

        // Bildungsreferent*innen gibt es nur oberhalb der Stämme.
        $br = $this->user(config('dpsg.site.level') === 'Stamm' ? 'verwaltung' : 'bildungsreferent');
        $this->assertTrue($br->can('manageParticipants', $leitende));
    }

    #[TestDox('Website: Kennzeichen „Leiter*innen“ und Filter auf Veranstaltungen und im Kalender')]
    public function test_website(): void
    {
        $this->event(Event::LEITENDE);
        $this->event('woelflinge');

        $this->get('/veranstaltungen/leiterrunde-leitende')->assertOk()->assertSee('Leiter*innen');
        $this->get('/veranstaltungen?stufe=leitende')->assertOk()
            ->assertSee('Leiterrunde leitende')->assertDontSee('Leiterrunde woelflinge')
            ->assertSee('<option value="leitende" selected', false);
        $this->get('/kalender?stufe=leitende')->assertOk()->assertSee('Leiterrunde leitende')->assertDontSee('Leiterrunde woelflinge');
    }
}
