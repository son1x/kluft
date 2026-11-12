<?php

namespace Tests\Feature\Content;

use App\Filament\Resources\StartseitenHinweise\Pages\CreateStartseitenHinweis;
use App\Filament\Resources\StartseitenHinweise\StartseitenHinweisResource;
use App\Models\Page;
use App\Models\StartseitenHinweis;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Hinweise auf der Startseite: mehrere, sortiert, an/aus, mit Zeitraum.
 */
class StartseitenHinweiseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Page::query()->create(['title' => 'Start', 'slug' => 'start', 'body' => '<p>Willkommen</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
    }

    private function hinweis(array $daten): StartseitenHinweis
    {
        return StartseitenHinweis::query()->create(['titel' => 'Hinweis', 'aktiv' => true, 'farbe' => 'rot', ...$daten]);
    }

    #[TestDox('Startseite: sichtbare Hinweise in Reihenfolge, vor dem Einstieg; aus- oder abgelaufene nicht')]
    public function test_startseite(): void
    {
        $this->hinweis(['titel' => 'Zweiter', 'sort' => 2]);
        $this->hinweis(['titel' => 'Aufarbeitung', 'sort' => 1, 'text' => 'Die DPSG arbeitet auf.', 'link_url' => 'https://dpsg.de/aufarbeitung', 'link_text' => 'Mehr auf dpsg.de']);
        $this->hinweis(['titel' => 'Ausgeschaltet', 'aktiv' => false]);
        $this->hinweis(['titel' => 'Abgelaufen', 'bis' => now()->subDay()]);
        $this->hinweis(['titel' => 'Zukünftig', 'ab' => now()->addDay()]);
        $this->hinweis(['titel' => 'Heute', 'ab' => now(), 'bis' => now(), 'sort' => 3, 'link_url' => '/kontakt', 'link_text' => 'Kontakt']);

        $this->get('/')->assertOk()
            ->assertSeeInOrder(['aria-label="Hinweise"', 'Aufarbeitung', 'Die DPSG arbeitet auf.', 'Mehr auf dpsg.de', 'Zweiter', 'Heute', 'Willkommen'], false)
            ->assertSee('href="https://dpsg.de/aufarbeitung"', false)
            ->assertSee('target="_blank" rel="noopener"', false)
            ->assertSee('öffnet eine andere Website in einem neuen Tab')
            ->assertSee('href="'.url('/kontakt').'"', false)
            ->assertDontSee('Ausgeschaltet')->assertDontSee('Abgelaufen')->assertDontSee('Zukünftig');
    }

    #[TestDox('Zwei Arten: Kasten (auffällig) und Zeile (dezent), in der gewählten Reihenfolge')]
    public function test_zeile(): void
    {
        $this->hinweis(['titel' => 'Wichtig', 'sort' => 1]);
        $this->hinweis(['titel' => 'Digitales Treffen', 'art' => 'zeile', 'farbe' => 'blau', 'sort' => 2, 'text' => 'Am 25.11. um 19:30 Uhr', 'link_url' => '/veranstaltungen', 'link_text' => 'Zur Anmeldung']);

        $antwort = $this->get('/')->assertOk()
            ->assertSeeInOrder(['Wichtig', 'Digitales Treffen', 'Am 25.11. um 19:30 Uhr', 'Zur Anmeldung'])
            ->assertDontSee('öffnet eine andere Website');
        // Die Zeile ist schmal (text-sm, py-2), der Kasten groß (p-6, text-xl).
        $this->assertMatchesRegularExpression('/<article class="[^"]*py-2 text-sm[^"]*">\s*<svg[^>]*>.*?Digitales Treffen/s', $antwort->getContent());
        $this->assertMatchesRegularExpression('/<article class="[^"]*p-6[^"]*">\s*<div class="min-w-0">\s*<h2[^>]*text-xl[^>]*>Wichtig/s', $antwort->getContent());
    }

    #[TestDox('Ohne Hinweise kein leerer Kasten')]
    public function test_ohne(): void
    {
        $this->get('/')->assertOk()->assertDontSee('aria-label="Hinweise"', false);
    }

    #[TestDox('Backend: pflegen darf, wer Website-Bausteine pflegt; Link nur https oder Pfad, Knopftext dann Pflicht')]
    public function test_backend(): void
    {
        Filament::setCurrentPanel('admin');
        $autor = User::query()->create(['name' => 'Autorin', 'email' => 'autorin@example.org', 'status' => 'active']);
        $autor->roleAssignments()->create(['role' => 'autor', 'scope_type' => 'own']);
        $this->actingAs($autor->fresh());
        $this->assertFalse(StartseitenHinweisResource::canViewAny());

        $buero = User::query()->create(['name' => 'Büro', 'email' => 'buero@example.org', 'status' => 'active']);
        $buero->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);
        $this->actingAs($buero->fresh());
        $this->assertTrue(StartseitenHinweisResource::canViewAny());

        Livewire::test(CreateStartseitenHinweis::class)
            ->fillForm(['titel' => 'Test', 'link_url' => 'http://unsicher.example.org'])
            ->call('create')->assertHasFormErrors(['link_url', 'link_text']);
        Livewire::test(CreateStartseitenHinweis::class)
            ->fillForm(['titel' => 'Büro geschlossen', 'art' => 'zeile', 'text' => 'Vom 23.12. bis 6.1.', 'link_url' => '/kontakt', 'link_text' => 'Kontakt', 'farbe' => 'blau', 'bis' => now()->addMonth()->toDateString()])
            ->call('create')->assertHasNoFormErrors();
        $this->assertSame(['blau', 'zeile'], [StartseitenHinweis::query()->sole()->farbe, StartseitenHinweis::query()->sole()->art]);
    }

    #[Group('dv-trier')]
    #[TestDox('DV Trier: Hinweis zur Aufarbeitung wird angelegt (Inhaltsmigration), erneut ausgeführt nicht doppelt')]
    public function test_aufarbeitung(): void
    {
        if (! is_file(base_path('instanzen/dv-trier/migrations/2026_11_12_140100_startseiten_hinweis_aufarbeitung.php')) || config('dpsg.instanz') !== 'dv-trier') {
            $this->markTestSkipped('Nur DV Trier.');
        }
        $migration = require base_path('instanzen/dv-trier/migrations/2026_11_12_140100_startseiten_hinweis_aufarbeitung.php');
        $migration->auchInTests = true;
        $migration->up();
        $migration->up();

        $this->assertSame(1, StartseitenHinweis::query()->count());
        $this->get('/')->assertOk()->assertSee('Aufarbeitung sexualisierter und spiritueller Gewalt in der DPSG')->assertSee('0800 22 55 530')
            ->assertSee('href="https://dpsg.de/de/verbandsleben/themen/kinder-und-jugendschutz/aufarbeitung"', false);
    }
}
