<?php

namespace Tests\Feature\Content;

use App\Models\Gruppenstunde;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Gruppenstunden aus „Verwaltung → Gruppenstunden“ auf Startseite und
 * Stufenseite, auch mit mehreren Gruppen einer Stufe.
 */
class GruppenstundenTest extends TestCase
{
    use RefreshDatabase;

    private function seiten(): void
    {
        foreach (['start' => null, 'stufe-woelflinge' => 'woelflinge', 'stufe-rover' => 'rover'] as $slug => $stage) {
            Page::query()->create(['title' => ucfirst($slug), 'slug' => $slug, 'stage' => $stage, 'body' => '<p>x</p>',
                'status' => 'published', 'published_at' => now()->subDay()]);
        }
    }

    #[TestDox('Freitext aus dem Einrichtungsassistenten wird in Tag und Uhrzeit zerlegt')]
    public function test_aus_text(): void
    {
        $this->assertSame(['wochentag' => 2, 'beginn' => '16:00', 'ende' => '17:00', 'hinweis' => null], Gruppenstunde::ausText('dienstags, 16:00–17:00 Uhr'));
        $this->assertSame(['wochentag' => 4, 'beginn' => '18:00', 'ende' => '19:30', 'hinweis' => null], Gruppenstunde::ausText('Donnerstag 18.00 - 19.30'));
        $this->assertSame('nach Absprache', Gruppenstunde::ausText('nach Absprache')['hinweis']);
    }

    #[TestDox('Ohne Einträge kein Widget; mit Einträgen Startseite und Stufenseite, zwei Gruppen einer Stufe')]
    public function test_anzeige(): void
    {
        $this->seiten();
        $this->get('/')->assertOk()->assertDontSee('Unsere Gruppenstunden');

        Gruppenstunde::query()->create(['stage' => 'woelflinge', 'name' => 'Meute Akela', 'wochentag' => 4, 'beginn' => '16:30', 'ende' => '18:00', 'sort' => 1]);
        Gruppenstunde::query()->create(['stage' => 'woelflinge', 'name' => 'Meute Baloo', 'wochentag' => 5, 'beginn' => '16:00', 'ende' => '17:30', 'ort' => 'Pfarrheim', 'sort' => 2]);
        Gruppenstunde::query()->create(['stage' => 'rover', 'wochentag' => 4, 'beginn' => '18:00', 'active' => false]);

        $this->get('/')->assertOk()
            ->assertSee('Unsere Gruppenstunden')
            ->assertSeeInOrder(['Meute Akela', 'donnerstags, 16:30–18:00 Uhr', 'Meute Baloo', 'freitags, 16:00–17:30 Uhr', 'Pfarrheim'])
            ->assertDontSee('/stufe-rover" class="font-serif', false);

        $this->get('/stufe-woelflinge')->assertOk()->assertSee('Unsere Gruppen')->assertSee('Meute Baloo');
        $this->get('/stufe-rover')->assertOk()->assertDontSee('donnerstags, 18:00');
    }
}
