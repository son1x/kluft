<?php

namespace Tests\Feature\People;

use App\Filament\Resources\People\Pages\EditPerson;
use App\Filament\Resources\TeamPhotos\Pages\EditTeamPhoto;
use App\Models\Media;
use App\Models\Page;
use App\Models\Person;
use App\Models\TeamPhoto;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
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

    #[Group('dv-trier')]

    #[TestDox('Diözesanleitung zeigt Personen, Gruppenfoto und Bildunterschrift')]
    public function test_leitung_page_shows_people_photo_and_caption(): void
    {
        $response = $this->get('/dioezesanleitung');
        $this->assertMatchesRegularExpression('/>\s*TM\s*</', $response->getContent());
        $this->assertMatchesRegularExpression('/>\s*HF\s*</', $response->getContent());
        $response->assertOk()
            ->assertSeeInOrder(['Unsere Diözesanleitung', 'Diözesanvorstand', 'Till Mang', 'Referent*innen', 'Heike Franz'])
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

    #[Group('dv-trier')]

    #[TestDox('Initialen-Kreise mit Mustern; Ansprechperson auf der Bolivienseite')]
    public function test_badge_patterns_and_section_contact_on_bolivien_page(): void
    {
        Person::query()->where('name', 'Heike Franz')->update(['badge' => 'woelflinge']);
        Person::query()->create(['gremium' => 'leitung', 'name' => 'Bianca Boltze', 'role' => 'Referentin Inklusion', 'badge' => 'inklusion', 'consent' => true, 'sort' => 5]);
        Person::query()->create(['gremium' => 'leitung', 'section' => 'bolivien', 'name' => 'Jannis Odrosek', 'role' => 'Referent der Bolivienpartnerschaft', 'badge' => 'bolivien', 'consent' => true, 'sort' => 6]);
        Page::query()->updateOrCreate(['slug' => 'bolivien'], ['title' => 'Bolivienpartnerschaft', 'section' => 'bolivien', 'body' => '<p>Amistad</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
        Page::query()->updateOrCreate(['slug' => 'bolivien-spenden'], ['title' => 'Spenden', 'section' => 'bolivien', 'body' => '<p>IBAN</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
        Page::query()->updateOrCreate(['slug' => 'bolivien-projekt'], ['title' => 'Schulprojekt', 'section' => 'bolivien', 'body' => '<p>x</p>', 'status' => 'published', 'published_at' => now()->subDay()]);

        $this->get('/dioezesanleitung')->assertOk()
            ->assertSee('avatar-inklusion', false)
            ->assertSee('avatar-bolivien', false)
            ->assertSee('bg-stufe-woelflinge', false);

        $response = $this->get('/bolivien')->assertOk()
            ->assertSee('amistad-freundschaft.webp', false)
            ->assertSee('Ansprechperson')
            ->assertSee('Jannis Odrosek')
            ->assertSee('/dioezesanleitung#person-jannis-odrosek', false)
            ->assertSee('Informationen zu Spenden')
            ->assertSee('Schulprojekt');

        // Spenden nur dezent unten: nicht in der Sektionsnavigation und nicht als Kachel.
        $html = $response->getContent();
        $nav = substr($html, strpos($html, 'aria-label="Bolivienpartnerschaft"'), 1500);
        $this->assertStringNotContainsString('bolivien-spenden', $nav);
        $this->assertSame(1, substr_count($html, '/bolivien-spenden'));
    }

    #[Group('dv-trier')]

    #[TestDox('Personen ohne Einwilligung werden auf dem Gruppenfoto nicht markiert')]
    public function test_spots_of_people_without_consent_are_hidden(): void
    {
        Person::query()->where('name', 'Till Mang')->update(['consent' => false]);

        $this->get('/dioezesanleitung')->assertOk()
            ->assertDontSee('Till Mang')
            ->assertSee('Von links:');
    }

    #[TestDox('Nur die Verwaltung pflegt Personen; Markierungen werden gespeichert')]
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
        $this->get('/admin/gruppenfotos')->assertOk()->assertSee('Unsere Diözesanleitung');
        $this->get('/admin/gruppenfotos/create')->assertOk();
        $this->get('/admin/personen/create')->assertOk();
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

    #[TestDox('Porträt lässt sich direkt bei der Person hochladen und landet mit Bildnachweis in der Mediathek')]
    public function test_portrait_upload_creates_media_and_shows_round_photo(): void
    {
        Storage::fake('public');
        Filament::setCurrentPanel('admin');
        $verwaltung = User::query()->create(['name' => 'V', 'email' => 'v@dpsg-trier.de', 'status' => 'active']);
        $verwaltung->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);
        $this->actingAs($verwaltung);
        $person = Person::query()->create(['gremium' => 'leitung', 'name' => 'Silas Weiß', 'role' => 'Referent', 'consent' => true]);

        Livewire::test(EditPerson::class, ['record' => $person->getRouteKey()])
            ->fillForm(['portrait_upload' => [UploadedFile::fake()->image('silas.jpg', 800, 800)]])
            ->call('save')
            ->assertHasFormErrors(['portrait_credit' => 'required']);

        Livewire::test(EditPerson::class, ['record' => $person->getRouteKey()])
            ->fillForm(['portrait_upload' => [UploadedFile::fake()->image('silas.jpg', 800, 800)], 'portrait_credit' => 'Max Muster'])
            ->call('save')
            ->assertHasNoFormErrors();

        $photo = $person->fresh()->photo;
        $this->assertNotNull($photo);
        $this->assertSame('Porträt Silas Weiß', $photo->alt_text);
        $this->assertSame('Max Muster', $photo->credit);
        $this->assertTrue($photo->rights_confirmed);
        Storage::disk('public')->assertExists($photo->path);
    }
}
