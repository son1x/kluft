<?php

namespace Tests\Feature\People;

use App\Filament\Resources\People\Pages\EditPerson;
use App\Models\Page;
use App\Models\Person;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Eine Person als Ansprechperson für mehrere Fachbereiche (Rückmeldung
 * Till, 05.11.2026).
 */
#[Group('dv-trier')]
class AnsprechpersonMehrereBereicheTest extends TestCase
{
    use RefreshDatabase;

    private function page(string $slug, array $attrs = []): Page
    {
        return Page::query()->create(['slug' => $slug, 'title' => ucfirst($slug), 'body' => "<p>Inhalt {$slug}</p>", 'status' => 'published', 'published_at' => now()->subDay(), ...$attrs]);
    }

    #[TestDox('Person mit zwei Fachbereichen erscheint auf beiden Fachbereichsseiten und in der Übersicht')]
    public function test_person_auf_mehreren_fachbereichen(): void
    {
        $this->page('ausbildung', ['section' => 'ausbildung']);
        $this->page('oekologie', ['section' => 'oekologie']);
        $this->page('spiritualitaet', ['section' => 'spiritualitaet']);
        $this->page('fachbereiche');
        Person::query()->create(['gremium' => 'leitung', 'name' => 'Till Mang', 'role' => 'Diözesanvorstand', 'sections' => ['ausbildung', 'oekologie'], 'consent' => true]);

        $person = Person::query()->where('name', 'Till Mang')->first();
        $this->assertSame('ausbildung', $person->section);
        $this->assertSame(['ausbildung', 'oekologie'], $person->ansprechBereiche());

        $this->get('/ausbildung')->assertOk()->assertSee('Till Mang');
        $this->get('/oekologie')->assertOk()->assertSee('Till Mang');
        $this->get('/spiritualitaet')->assertOk()->assertDontSee('Till Mang');
        $this->assertSame(['Till Mang'], Person::query()->ansprechpersonFuer('oekologie')->pluck('name')->all());
    }

    #[TestDox('Alte Datensätze mit nur einem Fachbereich zählen weiter; FAK bleibt bei einem Bereich')]
    public function test_bestand_und_fak(): void
    {
        $person = Person::query()->create(['gremium' => 'leitung', 'name' => 'Alt Person', 'section' => 'ausbildung', 'consent' => true]);
        $this->assertSame(['ausbildung'], $person->fresh()->sections);
        $this->assertSame(['Alt Person'], Person::query()->ansprechpersonFuer('ausbildung')->pluck('name')->all());

        $fak = Person::query()->create(['gremium' => 'fak', 'name' => 'Fabian Fak', 'section' => 'spiritualitaet', 'sections' => ['oekologie'], 'consent' => true]);
        $this->assertNull($fak->fresh()->sections);
        $this->assertSame([], $fak->fresh()->ansprechBereiche());
        $this->assertSame([], Person::query()->ansprechpersonFuer('spiritualitaet')->pluck('name')->all());
    }

    #[TestDox('Im Backend lassen sich mehrere Fachbereiche wählen')]
    public function test_backend_mehrfachauswahl(): void
    {
        Filament::setCurrentPanel('admin');
        $verwaltung = User::query()->create(['name' => 'V', 'email' => 'v@dpsg-trier.de', 'status' => 'active']);
        $verwaltung->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);
        $this->actingAs($verwaltung);
        $person = Person::query()->create(['gremium' => 'leitung', 'name' => 'Till Mang', 'section' => 'ausbildung', 'consent' => true]);

        Livewire::test(EditPerson::class, ['record' => $person->getRouteKey()])
            ->assertFormFieldIsVisible('sections')
            ->assertFormFieldIsHidden('section')
            ->fillForm(['sections' => ['oekologie', 'ausbildung']])
            ->call('save')
            ->assertHasNoFormErrors();

        $person->refresh();
        $this->assertSame(['oekologie', 'ausbildung'], $person->sections);
        $this->assertSame('oekologie', $person->section);
    }
}
