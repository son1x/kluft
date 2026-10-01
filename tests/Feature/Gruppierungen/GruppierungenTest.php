<?php

namespace Tests\Feature\Gruppierungen;

use App\Filament\Resources\Gruppierungen\Pages\CreateGruppierung;
use App\Filament\Resources\Gruppierungen\Pages\EditGruppierung;
use App\Models\Gruppierung;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Bezirke- und Stämme-Verzeichnis, Pflichtenheft 6.11 und
 * Abnahmekriterium 18.
 */
class GruppierungenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Die Migration legt die echten Bezirke an; Tests arbeiten mit eigenen Daten.
        Gruppierung::query()->delete();
    }

    public function test_migration_creates_the_ten_bezirke_without_inventing_positions(): void
    {
        $this->seed(\Database\Seeders\GruppierungenSeeder::class);

        $this->assertSame(10, Gruppierung::query()->where('typ', 'bezirk')->count());
        $this->assertSame(0, Gruppierung::query()->where('typ', 'stamm')->count());
        $this->assertSame(0, Gruppierung::query()->whereNotNull('lat')->count());
    }

    public function test_import_from_dpsg_trier_is_complete_numbered_and_idempotent(): void
    {
        $this->seed(\Database\Seeders\GruppierungenSeeder::class);
        $this->seed(\Database\Seeders\StaemmeSeeder::class);

        $this->assertSame(10, Gruppierung::query()->where('typ', 'bezirk')->whereNotNull('nummer')->count());
        $this->assertSame(68, Gruppierung::query()->where('typ', 'stamm')->count());
        $this->assertSame(0, Gruppierung::query()->where('typ', 'stamm')->whereNull('parent_id')->count());

        $stamm = Gruppierung::query()->where('nummer', '21-01-08')->firstOrFail();
        $this->assertSame('koblenz', $stamm->bezirk->slug);
        $this->assertTrue($stamm->hasApproximatePosition());

        // Erneuter Lauf: keine Duplikate, im Backend gepflegte Angaben bleiben.
        $stamm->update(['website_url' => 'https://neu.example', 'name' => 'Umbenannt']);
        $this->seed(\Database\Seeders\StaemmeSeeder::class);
        $this->assertSame(68, Gruppierung::query()->where('typ', 'stamm')->count());
        $this->assertSame('https://neu.example', $stamm->fresh()->website_url);
        $this->assertSame('Umbenannt', $stamm->fresh()->name);
    }

    public function test_bezirke_and_staemme_are_ordered_by_dpsg_number(): void
    {
        $b2 = $this->bezirk('zweiter', ['nummer' => '21-02-00', 'name' => 'Zweiter']);
        $b1 = $this->bezirk('erster', ['nummer' => '21-01-00', 'name' => 'Erster']);
        $this->stamm('z-stamm', $b1, ['nummer' => '21-01-03', 'name' => 'Aaa Stamm 03']);
        $this->stamm('a-stamm', $b1, ['nummer' => '21-01-01', 'name' => 'Zzz Stamm 01']);
        $this->stamm('ohne-nummer', $b1, ['name' => 'Mmm ohne Nummer']);

        $this->get('/bezirke-staemme')->assertOk()->assertSeeInOrder([
            '21-01-00', 'Bezirk Erster',
            '21-01-01', 'Zzz Stamm 01',
            '21-01-03', 'Aaa Stamm 03',
            'Mmm ohne Nummer',
            '21-02-00', 'Bezirk Zweiter',
        ]);
    }

    public function test_imported_position_is_marked_approximate_until_set_in_backend(): void
    {
        $stamm = $this->stamm('x', null, ['nummer' => '21-01-01', 'lat' => 49.7, 'lng' => 6.6]);

        $this->get('/bezirke-staemme')->assertSee('Standort ungefähr (Ortsmitte)');
        $this->assertTrue($this->getJson('/api/gruppierungen.geojson')->json('features.0.properties.ungefaehr'));
        $this->assertSame('21-01-01', $this->getJson('/api/gruppierungen.geojson')->json('features.0.properties.nummer'));

        $stamm->update(['position_gesetzt_von' => $this->verwaltung()->id]);
        $this->get('/bezirke-staemme')->assertDontSee('Standort ungefähr (Ortsmitte)');
        $this->assertFalse($this->getJson('/api/gruppierungen.geojson')->json('features.0.properties.ungefaehr'));
    }

    public function test_backend_validates_dpsg_number_format(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->verwaltung());
        $stamm = $this->stamm('x', null);

        Livewire::test(EditGruppierung::class, ['record' => $stamm->getRouteKey()])
            ->fillForm(['nummer' => '21/1/8'])
            ->call('save')
            ->assertHasFormErrors(['nummer']);

        Livewire::test(EditGruppierung::class, ['record' => $stamm->getRouteKey()])
            ->fillForm(['nummer' => '21-01-08'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('21-01-08', $stamm->fresh()->nummer);
    }

    private function bezirk(string $slug, array $attrs = []): Gruppierung
    {
        return Gruppierung::query()->create(['typ' => 'bezirk', 'name' => ucfirst($slug), 'slug' => $slug, ...$attrs]);
    }

    private function stamm(string $slug, ?Gruppierung $bezirk, array $attrs = [], array $stufen = []): Gruppierung
    {
        $g = Gruppierung::query()->create(['typ' => 'stamm', 'name' => "Stamm {$slug}", 'slug' => $slug, 'parent_id' => $bezirk?->id, ...$attrs]);
        $g->syncStufen($stufen);

        return $g;
    }

    private function verwaltung(): User
    {
        $user = User::query()->create(['name' => 'Büro', 'email' => 'buero@dpsg-trier.de', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);

        return $user->fresh();
    }

    public function test_geojson_contains_only_active_entries_with_position_and_respects_filters(): void
    {
        $trier = $this->bezirk('trier');
        $saar = $this->bezirk('saar');
        $this->stamm('mit-pin', $trier, ['lat' => 49.75, 'lng' => 6.64], ['woelflinge', 'rover']);
        $this->stamm('ohne-pin', $trier, [], ['woelflinge']);
        $this->stamm('inaktiv', $trier, ['lat' => 49.7, 'lng' => 6.6, 'active' => false]);
        $this->stamm('saar-pin', $saar, ['lat' => 49.3, 'lng' => 6.9], ['biber']);

        $all = $this->getJson('/api/gruppierungen.geojson')->assertOk()->json('features');
        $this->assertEqualsCanonicalizing(['mit-pin', 'saar-pin'], array_column(array_column($all, 'properties'), 'slug'));

        $feature = collect($all)->firstWhere('properties.slug', 'mit-pin');
        $this->assertSame([6.64, 49.75], $feature['geometry']['coordinates']);
        $this->assertSame(['woelflinge', 'rover'], $feature['properties']['stufen']);

        $this->assertSame(['mit-pin'], array_column(array_column($this->getJson('/api/gruppierungen.geojson?bezirk=trier')->json('features'), 'properties'), 'slug'));
        $this->assertSame(['saar-pin'], array_column(array_column($this->getJson('/api/gruppierungen.geojson?stufe=biber')->json('features'), 'properties'), 'slug'));
        // Unbekannte Filterwerte werden ignoriert statt Fehler zu erzeugen.
        $this->getJson('/api/gruppierungen.geojson?bezirk=gibtsnicht&stufe=<x>')->assertOk()->assertJsonCount(2, 'features');
    }

    public function test_contact_person_only_published_with_consent_and_unsafe_links_are_dropped(): void
    {
        $this->stamm('a', null, ['lat' => 49.7, 'lng' => 6.6, 'ansprechperson' => 'Erika Muster', 'ansprechperson_einwilligung' => false, 'website_url' => 'javascript:alert(1)']);
        $this->stamm('b', null, ['lat' => 49.8, 'lng' => 6.7, 'ansprechperson' => 'Max Muster', 'ansprechperson_einwilligung' => true, 'website_url' => 'https://stamm-b.example']);

        $features = collect($this->getJson('/api/gruppierungen.geojson')->json('features'))->keyBy('properties.slug');
        $this->assertNull($features['a']['properties']['ansprechperson']);
        $this->assertNull($features['a']['properties']['website']);
        $this->assertSame('Max Muster', $features['b']['properties']['ansprechperson']);
        $this->assertSame('https://stamm-b.example', $features['b']['properties']['website']);

        $this->get('/bezirke-staemme')->assertOk()->assertDontSee('Erika Muster')->assertSee('Max Muster')->assertDontSee('javascript:alert', false);
    }

    public function test_geojson_cache_is_invalidated_on_change(): void
    {
        $stamm = $this->stamm('x', null, ['lat' => 49.7, 'lng' => 6.6]);
        $this->getJson('/api/gruppierungen.geojson')->assertJsonCount(1, 'features');

        $stamm->update(['active' => false]);
        $this->getJson('/api/gruppierungen.geojson')->assertJsonCount(0, 'features');

        $stamm->update(['active' => true]);
        $stamm->syncStufen(['pfadi']);
        $this->assertSame(['pfadi'], $this->getJson('/api/gruppierungen.geojson')->json('features.0.properties.stufen'));
    }

    public function test_directory_page_works_without_javascript_and_shows_placeholders(): void
    {
        $trier = $this->bezirk('trier');
        $this->bezirk('eifel');
        $this->stamm('st-georg', $trier, ['ort' => 'Trier', 'kontakt_email' => 'st-georg@dpsg-trier.de'], ['jupfi']);

        $this->get('/bezirke-staemme')
            ->assertOk()
            ->assertSee('Bezirk Trier')
            ->assertSee('Stamm st-georg')
            ->assertSee('st-georg@dpsg-trier.de')
            ->assertSee('Die Stämme dieses Bezirks werden noch erfasst.')
            ->assertSee('Standort noch nicht erfasst')
            ->assertSee('Standorte werden gerade erfasst.');

        $this->get('/bezirke-staemme?bezirk=eifel')->assertOk()->assertSee('Bezirk Eifel')->assertDontSee('Stamm st-georg');
        $this->get('/bezirke-staemme?stufe=biber')->assertOk()->assertDontSee('Stamm st-georg')->assertSee('Für diese Auswahl ist noch keine Gruppierung erfasst.');
    }

    public function test_page_does_not_reference_external_map_hosts(): void
    {
        $html = $this->get('/bezirke-staemme')->assertOk()->getContent();

        $this->assertStringContainsString('/maps/data/', $html);
        $this->assertDoesNotMatchRegularExpression('#https?://(?!localhost|127\.0\.0\.1)[^"\']*(tile|maps?\.|protomaps|openstreetmap)#i', $html);
    }

    public function test_only_verwaltung_manages_entries_and_position_author_is_tracked(): void
    {
        Filament::setCurrentPanel('admin');
        $autor = User::query()->create(['name' => 'Autor', 'email' => 'autor@dpsg-trier.de', 'status' => 'active']);
        $autor->roleAssignments()->create(['role' => 'autor', 'scope_type' => 'own']);
        $this->actingAs($autor)->get('/admin/gruppierungen')->assertForbidden();

        $verwaltung = $this->verwaltung();
        $this->actingAs($verwaltung)->get('/admin/gruppierungen')->assertOk();
        $bezirk = $this->bezirk('trier');

        Livewire::test(CreateGruppierung::class)
            ->fillForm([
                'typ' => 'stamm',
                'parent_id' => $bezirk->id,
                'name' => 'Stamm Neu',
                'slug' => 'stamm-neu',
                'stufen' => ['biber', 'rover'],
                'lat' => 49.75,
                'lng' => 6.64,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $stamm = Gruppierung::query()->where('slug', 'stamm-neu')->firstOrFail();
        $this->assertSame($verwaltung->id, $stamm->position_gesetzt_von);
        $this->assertSame(['biber', 'rover'], $stamm->stufen());
        $this->actingAs($verwaltung)->get("/admin/gruppierungen/{$stamm->id}/edit")->assertOk()->assertSee('data-map-picker', false);
    }

    public function test_contact_name_requires_consent_in_backend(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->verwaltung());
        $stamm = $this->stamm('x', null);

        Livewire::test(EditGruppierung::class, ['record' => $stamm->getRouteKey()])
            ->fillForm(['ansprechperson' => 'Erika Muster', 'ansprechperson_einwilligung' => false])
            ->call('save')
            ->assertHasFormErrors(['ansprechperson_einwilligung']);
    }
}
