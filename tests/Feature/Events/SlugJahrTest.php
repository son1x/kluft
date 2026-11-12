<?php

namespace Tests\Feature\Events;

use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Schemas\EventForm;
use App\Models\Event;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Hinweis „Ohne Jahreszahl“ am URL-Pfad mit Knopf „Jahr anhängen“.
 */
class SlugJahrTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Erkennung: mit Jahreszahl kein Hinweis')]
    public function test_erkennung(): void
    {
        $this->assertTrue(EventForm::ohneJahr('spirihike'));
        $this->assertTrue(EventForm::ohneJahr('lager-123'));
        $this->assertFalse(EventForm::ohneJahr('spirihike-2027'));
        $this->assertFalse(EventForm::ohneJahr('adventszauber-2026'));
        $this->assertFalse(EventForm::ohneJahr('2027-pfingstlager'));
        $this->assertFalse(EventForm::ohneJahr(''));
    }

    #[TestDox('Formular: Hinweis erscheint, „Jahr anhängen“ nimmt das Jahr des Beginns; Speichern auch ohne Jahr möglich')]
    public function test_formular(): void
    {
        Filament::setCurrentPanel('admin');
        $user = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'admin', 'scope_type' => 'global']);
        $this->actingAs($user->fresh());

        Livewire::test(CreateEvent::class)
            ->fillForm(['title' => 'Spiritueller Hike', 'slug' => 'spirihike', 'beginn_datum' => '2027-05-05'])
            ->assertSee('Ohne Jahreszahl ist die Adresse')
            ->callAction(TestAction::make('jahrAnhaengen')->schemaComponent('slug'))
            ->assertFormSet(['slug' => 'spirihike-2027'])
            ->assertDontSee('Ohne Jahreszahl ist die Adresse')
            ->fillForm(['status' => 'open', 'retention_days' => 90])
            ->call('create')->assertHasNoFormErrors();
        $this->assertSame('spirihike-2027', Event::query()->sole()->slug);

        Livewire::test(CreateEvent::class)
            ->fillForm(['title' => 'Ohne Jahr', 'slug' => 'ohne-jahr', 'beginn_datum' => '2027-06-01', 'status' => 'open', 'retention_days' => 90])
            ->call('create')->assertHasNoFormErrors();
        $this->assertTrue(Event::query()->where('slug', 'ohne-jahr')->exists());
    }
}
