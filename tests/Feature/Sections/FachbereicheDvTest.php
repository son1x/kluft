<?php

namespace Tests\Feature\Sections;

use App\Models\ContactTopic;
use App\Models\Page;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Instanzen\DvTrier\Seeders\DemoContentSeeder;
use Instanzen\DvTrier\Seeders\Inhalt\FachbereicheSpiritualitaetAusbildungOekologie;
use Instanzen\DvTrier\Seeders\RequiredPagesSeeder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * DV Trier: Fachbereiche Spiritualität, Ausbildung und Ökologie mit
 * Facharbeitskreis (FAK).
 */
#[Group('dv-trier')]
class FachbereicheDvTest extends TestCase
{
    use RefreshDatabase;

    private const NEU = ['spiritualitaet' => 'Spiritualität', 'ausbildung' => 'Ausbildung', 'oekologie' => 'Ökologie'];

    #[TestDox('Neue Installation: Seiten unter „Fachbereiche“, Übersicht und Menü')]
    public function test_fresh_install(): void
    {
        $this->seed([RequiredPagesSeeder::class, DemoContentSeeder::class]);

        $fach = Page::query()->where('slug', 'fachbereiche')->sole();
        foreach (self::NEU as $slug => $label) {
            $page = Page::query()->where('slug', $slug)->sole();
            $this->assertSame([$fach->id, $slug, true], [$page->parent_id, $page->section, $page->show_in_nav]);
            $this->get('/'.$slug)->assertOk()->assertSee($label)->assertSee('Der Facharbeitskreis')->assertSee('fach-'.config("dpsg.sections.{$slug}.color"), false);
        }
        $this->get('/fachbereiche')->assertOk()->assertSeeInOrder(['Inklusion', 'Spiritualität', 'Ausbildung', 'Ökologie']);
        $this->get('/inklusion')->assertOk()->assertDontSee('Der Facharbeitskreis');
    }

    #[TestDox('Laufende Installation: Inhalts-Migration ergänzt Seiten und Kontaktthemen, ohne Bearbeitetes zu überschreiben')]
    public function test_instance_migration_on_existing_installation(): void
    {
        $this->assertTrue(DB::table('migrations')->where('migration', '2026_10_05_120000_fachbereiche_spiritualitaet_ausbildung_oekologie')->exists(),
            'Die Inhalts-Migration der Instanz läuft mit migrate.');

        $fach = Page::query()->create(['title' => 'Fachbereiche', 'slug' => 'fachbereiche', 'body' => '<p>x</p>', 'status' => 'published', 'published_at' => now()]);
        Page::query()->create(['title' => 'Ökologie', 'slug' => 'oekologie', 'body' => '<p>Eigener Text</p>', 'status' => 'published', 'published_at' => now()]);
        ContactTopic::query()->create(['label' => 'Allgemein', 'slug' => 'allgemein', 'sort' => 10, 'active' => true]);

        $schritt = new FachbereicheSpiritualitaetAusbildungOekologie;
        $schritt->run();
        $schritt->run(); // wiederholbar

        $this->assertSame($fach->id, Page::query()->where('slug', 'spiritualitaet')->value('parent_id'));
        $this->assertSame('<p>Eigener Text</p>', Page::query()->where('slug', 'oekologie')->value('body'));
        $this->assertSame(['allgemein', 'spiritualitaet', 'ausbildung', 'oekologie'], ContactTopic::query()->orderBy('sort')->pluck('slug')->all());
        $this->assertSame('ausbildung', ContactTopic::query()->where('slug', 'ausbildung')->value('section'));
    }

    #[TestDox('FAK-Mitglieder erscheinen auf ihrer Fachbereichsseite, ohne Adresse und nicht als Ansprechperson')]
    public function test_fak_members(): void
    {
        $this->seed([RequiredPagesSeeder::class, DemoContentSeeder::class]);
        $referentin = Person::query()->create(['gremium' => 'leitung', 'name' => 'Rita Referentin', 'section' => 'spiritualitaet', 'consent' => true]);
        $fak = Person::query()->create(['gremium' => 'fak', 'name' => 'Fabian Fak', 'role' => 'Im FAK seit 2025', 'section' => 'spiritualitaet', 'email' => 'f.fak@example.org', 'consent' => true]);

        $this->assertNull($fak->fresh()->email);
        $this->assertNull($fak->publicEmail());
        $this->assertSame('FAK Spiritualität', $fak->gremiumLabel());
        $this->assertStringEndsWith('/spiritualitaet#person-fabian-fak', $fak->pageUrl());

        $this->get('/spiritualitaet')->assertOk()
            ->assertSeeInOrder(['Ansprechperson', 'Rita Referentin', 'Der Facharbeitskreis', 'Fabian Fak', 'Lust, im FAK Spiritualität mitzuarbeiten?', $referentin->publicEmail()])
            ->assertDontSee('f.fak@example.org');
        $this->get('/ausbildung')->assertOk()->assertDontSee('Fabian Fak');
    }
}
