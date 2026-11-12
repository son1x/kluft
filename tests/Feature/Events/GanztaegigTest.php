<?php

namespace Tests\Feature\Events;

use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Pages\ListEvents;
use App\Models\Event;
use App\Models\User;
use App\Support\CalendarItem;
use App\Support\IcsKalender;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Beginn und Ende wie im Apple-Kalender: Datum und Uhrzeit getrennt,
 * Schalter „Ganztägig“.
 */
class GanztaegigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        $user = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'admin', 'scope_type' => 'global']);
        $this->actingAs($user->fresh());
    }

    #[TestDox('Mit Uhrzeit: Ende rückt mit (gleicher Tag, eine Stunde später), Beginn/Ende werden zusammengesetzt')]
    public function test_mit_uhrzeit(): void
    {
        Livewire::test(CreateEvent::class)
            ->fillForm(['title' => 'Leitungsrunde', 'beginn_datum' => '2027-05-05'])
            ->fillForm(['beginn_zeit' => '18:00'])
            ->assertFormSet(['ende_datum' => '2027-05-05', 'ende_zeit' => '19:00'])
            ->fillForm(['ende_zeit' => '21:30', 'status' => 'open', 'retention_days' => 90])
            ->call('create')->assertHasNoFormErrors();

        $e = Event::query()->sole();
        $this->assertSame(['2027-05-05 18:00', '2027-05-05 21:30', false], [$e->starts_at->format('Y-m-d H:i'), $e->ends_at->format('Y-m-d H:i'), $e->ganztaegig]);
        $this->assertSame('Mi, 5. Mai 2027, 18:00–21:30 Uhr', $e->dateLabel());
    }

    #[TestDox('Ganztägig: nur Datum, Ende 23:59; Website ohne Uhrzeit; Kalender und Abo als Ganztagstermin')]
    public function test_ganztaegig(): void
    {
        Livewire::test(CreateEvent::class)
            ->fillForm(['title' => 'Pfingstlager', 'ganztaegig' => true, 'beginn_datum' => '2027-05-14'])
            ->assertFormFieldIsHidden('beginn_zeit')
            ->fillForm(['ende_datum' => '2027-05-17', 'status' => 'open', 'retention_days' => 90])
            ->call('create')->assertHasNoFormErrors();

        $e = Event::query()->sole();
        $this->assertTrue($e->ganztaegig);
        $this->assertSame(['2027-05-14 00:00', '2027-05-17 23:59'], [$e->starts_at->format('Y-m-d H:i'), $e->ends_at->format('Y-m-d H:i')]);
        $this->assertSame('14. Mai – 17. Mai 2027', $e->dateLabel());

        $item = CalendarItem::fromEvent($e);
        $this->assertTrue($item->all_day);
        $this->assertSame('2027-05-17', $item->lastDay()->toDateString());
        $ics = IcsKalender::erzeugen(collect([$item]), 'Test');
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20270514', $ics);
        $this->assertStringContainsString('DTEND;VALUE=DATE:20270518', $ics);

        // Bearbeiten: Felder kommen getrennt zurück, Tabelle zeigt „ganztägig“.
        Livewire::test(EditEvent::class, ['record' => $e->getRouteKey()])
            ->assertFormSet(['ganztaegig' => true, 'beginn_datum' => '2027-05-14', 'ende_datum' => '2027-05-17']);
        Livewire::test(ListEvents::class)->assertSee('14.05.2027 – 17.05.2027')->assertSee('ganztägig');
    }

    #[TestDox('Bearbeiten und Speichern ohne Änderung lässt Beginn und Ende unverändert (keine Zeitzonen-Verschiebung)')]
    public function test_speichern_unveraendert(): void
    {
        $e = Event::query()->create(['title' => 'Abend', 'slug' => 'abend', 'starts_at' => '2027-07-01 19:30', 'ends_at' => '2027-07-01 22:00', 'status' => 'open', 'retention_days' => 90]);
        Livewire::test(EditEvent::class, ['record' => $e->getRouteKey()])
            ->assertFormSet(['beginn_datum' => '2027-07-01', 'beginn_zeit' => '19:30', 'ende_zeit' => '22:00'])
            ->call('save')->assertHasNoFormErrors();
        $e->refresh();
        $this->assertSame(['2027-07-01 19:30', '2027-07-01 22:00'], [$e->starts_at->format('Y-m-d H:i'), $e->ends_at->format('Y-m-d H:i')]);

        // Nur die Uhrzeit ändern.
        Livewire::test(EditEvent::class, ['record' => $e->getRouteKey()])
            ->fillForm(['beginn_zeit' => '20:00'])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('2027-07-01 20:00', $e->fresh()->starts_at->format('Y-m-d H:i'));
    }

    #[TestDox('Ende vor Beginn wird abgelehnt; ohne Datum kein Speichern')]
    public function test_pruefung(): void
    {
        Livewire::test(CreateEvent::class)
            ->fillForm(['title' => 'Falsch', 'beginn_datum' => '2027-05-05', 'beginn_zeit' => '18:00', 'ende_datum' => '2027-05-04', 'ende_zeit' => '10:00', 'status' => 'open', 'retention_days' => 90])
            ->call('create')->assertHasFormErrors(['ende_datum']);
        Livewire::test(CreateEvent::class)
            ->fillForm(['title' => 'Ohne Datum', 'status' => 'open', 'retention_days' => 90])
            ->call('create')->assertHasFormErrors(['beginn_datum' => 'required']);
        $this->assertSame(0, Event::query()->count());
    }
}
