<?php

namespace Tests\Feature\Sections;

use App\Models\ContactTopic;
use App\Models\Download;
use App\Models\Page;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Instanzen\DvTrier\Seeders\DemoContentSeeder;
use Instanzen\DvTrier\Seeders\Inhalt\BereichPraevention;
use Instanzen\DvTrier\Seeders\RequiredPagesSeeder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * DV Trier: Bereich Prävention unter „Diözesanverband“ (Thema der
 * Hauptberuflichen, kein Fachbereich).
 */
#[Group('dv-trier')]
class PraeventionDvTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Seite unter „Diözesanverband“ mit Hilfe-Angeboten, nicht in der Fachbereichs-Übersicht, ohne Facharbeitskreis')]
    public function test_page(): void
    {
        $this->seed([RequiredPagesSeeder::class, DemoContentSeeder::class]);

        $dv = Page::query()->where('slug', 'dioezesanverband')->sole();
        $this->assertSame($dv->id, Page::query()->where('slug', 'praevention')->value('parent_id'));

        $this->get('/praevention')->assertOk()
            ->assertSeeInOrder(['Thema', 'Prävention', 'Schutzkonzept', 'Präventionsschulung', 'Hilfetelefon Sexueller Missbrauch', '0800 22 55 530'])
            ->assertSee('fach-praevention', false)
            ->assertDontSee('Der Facharbeitskreis');
        $this->get('/fachbereiche')->assertOk()->assertViewHas('items', fn ($items) => ! $items->pluck('key')->contains('praevention'));
        $this->get('/dioezesanverband')->assertOk()->assertSee('/praevention', false);
    }

    #[TestDox('Inhalts-Migration: Ansprechperson und Kontaktthema, ohne Gepflegtes zu überschreiben')]
    public function test_step(): void
    {
        $this->assertTrue(DB::table('migrations')->where('migration', '2026_10_06_130000_bereich_praevention')->exists());
        Page::query()->create(['title' => 'Diözesanverband', 'slug' => 'dioezesanverband', 'body' => '<p>x</p>', 'status' => 'published', 'published_at' => now()]);
        ContactTopic::query()->create(['label' => 'Allgemein', 'slug' => 'allgemein', 'sort' => 10, 'active' => true]);
        $axel = Person::query()->create(['gremium' => 'buero', 'name' => 'Axel Hemgesberg', 'role' => 'Bildungsreferent', 'consent' => true]);
        $andere = Person::query()->create(['gremium' => 'buero', 'name' => 'Axel Hemgesberg', 'role' => 'Doppelt', 'section' => 'beratung', 'consent' => true]);

        (new BereichPraevention)->run();
        (new BereichPraevention)->run();

        $this->assertSame('praevention', $axel->fresh()->section);
        $this->assertSame('beratung', $andere->fresh()->section, 'Vorhandene Zuordnung bleibt');
        $this->assertSame(['allgemein', 'praevention'], ContactTopic::query()->orderBy('sort')->pluck('slug')->all());
        $this->assertSame(1, Page::query()->where('slug', 'praevention')->count());

        $this->get('/praevention')->assertOk()->assertSeeInOrder(['Ansprechperson', 'Axel Hemgesberg']);
    }

    #[TestDox('Downloads mit Bereich „Prävention“ erscheinen als Dokumente auf der Seite')]
    public function test_documents(): void
    {
        Storage::fake(Download::DISK);
        $this->seed([RequiredPagesSeeder::class, DemoContentSeeder::class]);
        Storage::disk(Download::DISK)->put('downloads/kodex.pdf', '%PDF-1.4');
        Storage::disk(Download::DISK)->put('downloads/anderes.pdf', '%PDF-1.4');
        Download::query()->create(['title' => 'Verhaltenskodex', 'category' => 'praevention', 'section' => 'praevention', 'file_path' => 'downloads/kodex.pdf', 'file_name' => 'kodex.pdf', 'rights_confirmed' => true, 'published' => true]);
        Download::query()->create(['title' => 'Kennenlernspiele', 'category' => 'methoden', 'file_path' => 'downloads/anderes.pdf', 'file_name' => 'anderes.pdf', 'rights_confirmed' => true, 'published' => true]);

        $this->get('/praevention')->assertOk()
            ->assertSeeInOrder(['Dokumente', 'Verhaltenskodex', 'Alle Downloads'])
            ->assertDontSee('Kennenlernspiele');
        $this->get('/oekologie')->assertOk()->assertDontSee('Dokumente</h2>', false);
    }
}
