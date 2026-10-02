<?php

namespace Tests\Feature\People;

use App\Filament\Resources\TeamPhotos\Pages\EditTeamPhoto;
use App\Models\Media;
use App\Models\Page;
use App\Models\Person;
use App\Models\TeamPhoto;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Personenvorstellung und Gruppenfoto (Diözesanleitung, Diözesanbüro).
 */
class PeopleTest extends TestCase
{
    use RefreshDatabase;

    private TeamPhoto $photo;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        foreach (['dioezesanleitung' => 'Diözesanleitung', 'dioezesanbuero' => 'Diözesanbüro'] as $slug => $title) {
            Page::query()->updateOrCreate(['slug' => $slug], ['title' => $title, 'body' => '<p>Intro</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
        }

        $till = Person::query()->create(['gremium' => 'leitung', 'subgroup' => 'Diözesanvorstand', 'name' => 'Till Mang', 'role' => 'Diözesanvorstand', 'consent' => true, 'bio' => 'Gut Pfad!', 'sort' => 1]);
        $heike = Person::query()->create(['gremium' => 'leitung', 'subgroup' => 'Referent*innen', 'name' => 'Heike Franz', 'role' => 'Referentin der Wölflingsstufe', 'stage' => 'woelflinge', 'consent' => true, 'sort' => 2]);
        Person::query()->create(['gremium' => 'leitung', 'name' => 'Geheim Ohnezustimmung', 'consent' => false, 'sort' => 3]);
        $axel = Person::query()->create(['gremium' => 'buero', 'subgroup' => 'Bildungsreferent*innen', 'name' => 'Axel Hemgesberg', 'role' => 'Bildungsreferent', 'consent' => true]);

        $media = Media::query()->create(['path' => 'media/gruppe.jpg', 'mime' => 'image/jpeg', 'size' => 1, 'alt_text' => 'Gruppenfoto', 'credit' => 'DPSG Trier']);
        $this->photo = TeamPhoto::query()->create(['gremium' => 'leitung', 'media_id' => $media->id, 'title' => 'Unsere Diözesanleitung', 'note' => 'Heike fehlt.']);
        $this->photo->spots()->create(['person_id' => $till->id, 'x' => 30, 'y' => 40]);
        $this->photo->spots()->create(['person_id' => $axel->id, 'x' => 10, 'y' => 40]);
    }

    public function test_leitung_page_shows_people_photo_and_caption(): void
    {
        $this->get('/dioezesanleitung')->assertOk()
            ->assertSeeInOrder(['Unsere Diözesanleitung', 'Diözesanvorstand', 'Till Mang', 'Referent*innen', 'Heike Franz'])
            ->assertSee('>TM<', false)
            ->assertSee('>HF<', false)
            ->assertSee('bg-stufe-woelflinge', false)
            ->assertSee('Gut Pfad!')
            // Bildunterschrift „von links“ nach x-Position, Büro-Person verlinkt auf ihre Seite
            ->assertSeeInOrder(['Von links:', 'Axel Hemgesberg', 'Till Mang'])
            ->assertSee('href="#person-till-mang"', false)
            ->assertSee('/dioezesanbuero#person-axel-hemgesberg', false)
            ->assertSee('Heike fehlt.')
            ->assertDontSee('Geheim Ohnezustimmung');

        $this->get('/dioezesanbuero')->assertOk()
            ->assertSee('id="person-axel-hemgesberg"', false)
            ->assertDontSee('Till Mang');
    }

    public function test_spots_of_people_without_consent_are_hidden(): void
    {
        Person::query()->where('name', 'Till Mang')->update(['consent' => false]);

        $this->get('/dioezesanleitung')->assertOk()
            ->assertDontSee('Till Mang')
            ->assertSee('Von links:');
    }

    public function test_only_verwaltung_manages_people_and_spots_are_saved(): void
    {
        Filament::setCurrentPanel('admin');
        $autor = User::query()->create(['name' => 'A', 'email' => 'a@dpsg-trier.de', 'status' => 'active']);
        $autor->roleAssignments()->create(['role' => 'stufenadmin', 'scope_type' => 'stage', 'scope_key' => 'rover']);
        $this->actingAs($autor)->get('/admin/personen')->assertForbidden();
        $this->actingAs($autor)->get('/admin/gruppenfotos')->assertForbidden();

        $buero = User::query()->create(['name' => 'B', 'email' => 'b@dpsg-trier.de', 'status' => 'active']);
        $buero->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);
        $this->actingAs($buero)->get('/admin/personen')->assertOk()->assertSee('Till Mang');
        $this->get("/admin/gruppenfotos/{$this->photo->id}/edit")->assertOk();

        $heike = Person::query()->where('name', 'Heike Franz')->first();
        $till = Person::query()->where('name', 'Till Mang')->first();
        Livewire::test(EditTeamPhoto::class, ['record' => $this->photo->getRouteKey()])
            ->set('data.spots', [
                ['person_id' => $till->id, 'x' => 55.5, 'y' => 41.2],
                ['person_id' => $heike->id, 'x' => 70, 'y' => 44],
                ['person_id' => 99999, 'x' => 10, 'y' => 10], // unbekannte Person
                ['person_id' => $heike->id, 'x' => 150, 'y' => 44], // doppelt/ungültig
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $spots = $this->photo->fresh()->spots;
        $this->assertSame(['Till Mang', 'Heike Franz'], $spots->map(fn ($s) => $s->person->name)->all());
        $this->assertSame(55.5, $spots->first()->x);
    }
}
