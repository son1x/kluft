<?php

namespace Tests\Feature\Calendar;

use App\Filament\Resources\CalendarEntries\CalendarEntryResource;
use App\Filament\Resources\CalendarEntries\Pages\ListCalendarEntries;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Models\Calendar;
use App\Models\CalendarEvent;
use App\Models\Event;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Backend „Kalendertermine“: zu Nextcloud-Terminen direkt eine
 * Veranstaltung mit Anmeldung anlegen.
 */
class CalendarEntriesTest extends TestCase
{
    use RefreshDatabase;

    private CalendarEvent $termin;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        $calendar = Calendar::query()->create(['label' => 'Wölflinge', 'ics_url' => 'https://cloud.example.org/x.ics', 'color' => '#ff6400', 'stage' => 'woelflinge']);
        $this->termin = CalendarEvent::query()->create([
            'calendar_id' => $calendar->id, 'uid' => 'abc-123', 'title' => 'Wölflingstag',
            'starts_at' => now()->addMonth()->setTime(10, 0), 'ends_at' => now()->addMonth()->setTime(17, 0),
            'location' => 'Jugendhaus Trier', 'description' => 'Spiele, Lagerfeuer und Würstchen.',
        ]);
        CalendarEvent::query()->create(['calendar_id' => $calendar->id, 'uid' => 'alt', 'title' => 'Vergangen', 'starts_at' => now()->subWeek(), 'ends_at' => now()->subWeek()->addHour()]);
    }

    private function user(string $role, string $scopeType = 'global', ?string $scopeKey = null): User
    {
        $user = User::query()->create(['name' => $role, 'email' => $role.'@dpsg-trier.de', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => $role, 'scope_type' => $scopeType, 'scope_key' => $scopeKey]);

        return $user->fresh();
    }

    #[TestDox('Kommende Termine mit „Anmeldung anlegen“; nach dem Anlegen verknüpft und im Kalender nur einmal')]
    public function test_create_registration_from_calendar_entry(): void
    {
        $this->actingAs($this->user('stufenadmin', 'stage', 'woelflinge'));

        $this->get(CalendarEntryResource::getUrl('index'))->assertOk()->assertSee('Wölflingstag')->assertDontSee('Vergangen');
        Livewire::test(ListCalendarEntries::class)
            ->assertTableColumnStateSet('anmeldung', 'keine', $this->termin)
            ->assertTableActionVisible('anlegen', $this->termin)
            ->assertTableActionHasUrl('anlegen', EventResource::getUrl('create', ['kalendertermin' => $this->termin->id]), $this->termin);

        Livewire::withQueryParams(['kalendertermin' => $this->termin->id])
            ->test(CreateEvent::class)
            ->assertSet('data.title', 'Wölflingstag')
            ->assertSet('data.location', 'Jugendhaus Trier')
            ->assertSet('data.stage', 'woelflinge')
            ->assertSet('data.calendar_uid', 'abc-123')
            ->assertSet('data.teaser', 'Spiele, Lagerfeuer und Würstchen.')
            ->fillForm(['title' => 'Wölflingstag 2026 – jetzt anmelden', 'status' => 'open', 'retention_days' => 90])
            ->call('create')
            ->assertHasNoFormErrors();

        $event = Event::query()->sole();
        $this->assertSame('abc-123', $event->calendar_uid);
        $this->assertTrue($event->starts_at->equalTo($this->termin->starts_at));
        $this->assertStringStartsWith('woelflingstag-', $event->slug);

        Livewire::test(ListCalendarEntries::class)
            ->assertTableColumnStateSet('anmeldung', 'vorhanden', $this->termin)
            ->assertTableActionHidden('anlegen', $this->termin)
            ->assertTableActionVisible('oeffnen', $this->termin);

        // Trotz geändertem Titel nur einmal im öffentlichen Kalender – als Veranstaltung.
        $html = $this->get('/kalender')->assertOk()->assertSee('Wölflingstag 2026 – jetzt anmelden')->getContent();
        $this->assertSame(1, substr_count($html, '>Wölflingstag'));
    }

    #[TestDox('Ohne Rechte für Veranstaltungen keine Kalendertermine im Backend')]
    public function test_only_event_managers_see_calendar_entries(): void
    {
        $this->actingAs($this->user('autor', 'own'));
        $this->get(CalendarEntryResource::getUrl('index'))->assertForbidden();

        $this->actingAs($this->user('verwaltung'));
        $this->get(CalendarEntryResource::getUrl('index'))->assertOk();
    }
}
