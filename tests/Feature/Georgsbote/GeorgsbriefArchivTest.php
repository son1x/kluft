<?php

namespace Tests\Feature\Georgsbote;

use App\Filament\Resources\GeorgsboteIssues\Pages\CreateGeorgsboteIssue;
use App\Filament\Resources\GeorgsboteIssues\Pages\ListGeorgsboteIssues;
use App\Models\GeorgsboteIssue;
use App\Models\User;
use App\Support\GeorgsboteArchiv;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Georgsbrief-Archiv (Newsletter als PDF), Kurzinhalt und Archivliste.
 */
#[Group('dv-trier')]
class GeorgsbriefArchivTest extends TestCase
{
    use RefreshDatabase;

    private function issue(string $art, int $year, int $no, array $attrs = []): GeorgsboteIssue
    {
        Storage::disk('local')->put("georgsbote/{$art}-{$year}-{$no}.pdf", '%PDF-1.4 test');

        return GeorgsboteIssue::query()->create([
            'art' => $art, 'year' => $year, 'issue_no' => $no, 'title' => "Titel {$art} {$no}",
            'pdf_path' => "georgsbote/{$art}-{$year}-{$no}.pdf",
            'published' => true, 'privacy_checked' => true, ...$attrs,
        ]);
    }

    private function verwaltung(): User
    {
        $user = User::query()->create(['name' => 'Büro', 'email' => 'buero@dpsg-trier.de', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);

        return $user->fresh();
    }

    #[TestDox('Dateinamen: Georgsbrief mit Monat als Zahl oder Name, sonst Georgsbote')]
    public function test_erkennen(): void
    {
        $this->assertSame(['art' => 'brief', 'year' => 2019, 'issue_no' => 2], GeorgsboteIssue::erkennen('Georgsbrief_2019_02.pdf'));
        $this->assertSame(['art' => 'brief', 'year' => 2020, 'issue_no' => 3], GeorgsboteIssue::erkennen('Georgsbrief März 2020.pdf'));
        $this->assertSame(['art' => 'brief', 'year' => 2021, 'issue_no' => 11], GeorgsboteIssue::erkennen('georgsbrief-2021-11.pdf'));
        $this->assertSame(['art' => 'bote', 'year' => 2019, 'issue_no' => 2], GeorgsboteIssue::erkennen('Georgsbote_2019_2.pdf'));
        $this->assertNull(GeorgsboteIssue::erkennen('Georgsbrief 2019.pdf'));
        $this->assertNull(GeorgsboteIssue::erkennen('Georgsbrief_2019_13.pdf'));
    }

    #[TestDox('Gleiche Nummer im selben Jahr als Bote und als Brief möglich')]
    public function test_bote_und_brief_getrennt_eindeutig(): void
    {
        Storage::fake('local');
        $this->issue('bote', 2019, 2);
        $this->issue('brief', 2019, 2);

        $this->assertSame(2, GeorgsboteIssue::query()->where('year', 2019)->where('issue_no', 2)->count());
    }

    #[TestDox('Archiv: Reiter Georgsbote/Georgsbrief, Kurzinhalt sichtbar, Suche in Titel und Inhalt')]
    public function test_archiv_reiter_und_suche(): void
    {
        Storage::fake('local');
        $this->issue('bote', 2024, 1, ['title' => 'Europa', 'inhalt' => 'Schwerpunkt Europawahl und Kommunalwahl.']);
        $this->issue('bote', 2023, 1, ['title' => 'Fake News', 'inhalt' => 'Kindergrundsicherung.']);
        $this->issue('brief', 2019, 2, ['title' => 'Georgsbrief Februar 2019', 'inhalt' => 'Erster Georgsbrief, Modulwoche ausgebucht.']);
        $this->issue('brief', 2019, 3, ['title' => 'Georgsbrief März 2019', 'inhalt' => 'Ungeprüft.', 'privacy_checked' => false]);

        $this->get('/georgsbote')->assertOk()
            ->assertSee('Schwerpunkt Europawahl')
            ->assertSee('Georgsbote <span class="text-sm font-normal">(2)</span>', false)
            ->assertSee('Georgsbrief <span class="text-sm font-normal">(1)</span>', false)
            ->assertDontSee('Modulwoche ausgebucht');

        $this->get('/georgsbote?art=brief')->assertOk()
            ->assertSee('Georgsbrief Februar 2019')
            ->assertSee('Modulwoche ausgebucht')
            ->assertSee(url('/georgsbrief/2019/2.pdf'))
            ->assertDontSee('Ungeprüft')
            ->assertDontSee('Schwerpunkt Europawahl');

        $this->get('/georgsbote?q=kommunalwahl')->assertOk()
            ->assertSee('Schwerpunkt Europawahl')
            ->assertDontSee('Kindergrundsicherung');
        $this->get('/georgsbote?q=100%25')->assertOk()->assertSee('Keine Treffer');
    }

    #[TestDox('Georgsbrief-PDF nur veröffentlicht; Georgsbote-Adresse liefert keinen Brief')]
    public function test_brief_pdf(): void
    {
        Storage::fake('local');
        $brief = $this->issue('brief', 2019, 2);
        $this->issue('brief', 2019, 3, ['published' => false]);

        $this->get('/georgsbrief/2019/2.pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(1, $brief->fresh()->downloads);
        $this->get('/georgsbrief/2019/3.pdf')->assertNotFound();
        $this->get('/georgsbote/2019/2.pdf')->assertNotFound();
    }

    #[TestDox('Upload: Georgsbriefe werden erkannt, Inhalt aus der Archivliste, ohne Namen')]
    public function test_upload_mit_archivliste(): void
    {
        Storage::fake('local');
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->verwaltung());

        Livewire::test(ListGeorgsboteIssues::class)
            ->callAction('mehrere', [
                'files' => [
                    UploadedFile::fake()->create('Georgsbrief_2019_02.pdf', 50, 'application/pdf'),
                    UploadedFile::fake()->create('Georgsbote_2024_1.pdf', 50, 'application/pdf'),
                    UploadedFile::fake()->create('Georgsbrief_2018_05.pdf', 50, 'application/pdf'),
                ],
            ])
            ->assertHasNoActionErrors();

        $brief = GeorgsboteIssue::query()->where('art', 'brief')->where('year', 2019)->sole();
        $this->assertSame(2, $brief->issue_no);
        $this->assertSame('Georgsbrief Februar 2019', $brief->title);
        $this->assertStringContainsString('Erster Georgsbrief', $brief->inhalt);
        $this->assertFalse($brief->published);

        $this->assertSame('Europa', GeorgsboteIssue::query()->where('art', 'bote')->sole()->title);

        // Nicht in der Liste: Vorgabe-Titel, kein Inhalt.
        $ohne = GeorgsboteIssue::query()->where('year', 2018)->sole();
        $this->assertSame('Georgsbrief Mai 2018', $ohne->title);
        $this->assertNull($ohne->inhalt);
    }

    #[TestDox('Archivliste: vollständig (17 Boten, 37 Briefe), keine Namen von Interviewten')]
    public function test_archivliste(): void
    {
        $liste = require base_path('instanzen/dv-trier/dateien/georgsbote-archiv.php');
        $this->assertCount(17, $liste['bote']);
        $this->assertCount(37, $liste['brief']);

        $text = collect($liste['bote'])->flatten()->merge(collect($liste['brief'])->flatten())->implode(' ');
        foreach (['Kraus', 'Sebastian', '@'] as $verboten) {
            $this->assertStringNotContainsString($verboten, $text);
        }
        $this->assertSame('Wo ist hier der Weg zum Glück?', GeorgsboteArchiv::eintrag('bote', 2025, 1)['title']);
        $this->assertNull(GeorgsboteArchiv::eintrag('brief', 2019, 7));
    }

    #[TestDox('Infos aus der Archivliste: setzt Vorgabe-Titel und leeren Inhalt, lässt Eigenes stehen')]
    public function test_archivliste_uebernehmen(): void
    {
        Storage::fake('local');
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->verwaltung());
        $vorgabe = $this->issue('bote', 2017, 1, ['title' => 'Georgsbote 1/2017']);
        $eigen = $this->issue('bote', 2017, 2, ['title' => 'Eigener Titel', 'inhalt' => 'Eigener Inhalt']);

        Livewire::test(ListGeorgsboteIssues::class)->callAction('archivliste')->assertNotified();

        $this->assertSame('Die erste Ausgabe', $vorgabe->fresh()->title);
        $this->assertStringContainsString('DiVers 2017', $vorgabe->fresh()->inhalt);
        $this->assertSame('Eigener Titel', $eigen->fresh()->title);
        $this->assertSame('Eigener Inhalt', $eigen->fresh()->inhalt);
    }

    #[TestDox('Backend: Brief von Hand anlegen, Monat statt Ausgabe')]
    public function test_brief_anlegen(): void
    {
        Storage::fake('local');
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->verwaltung());

        Livewire::test(CreateGeorgsboteIssue::class)
            ->fillForm([
                'art' => 'brief', 'year' => 2023, 'issue_no' => 7, 'title' => 'Georgsbrief Juli 2023',
                'inhalt' => 'Podcast.', 'pdf_path' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('brief', GeorgsboteIssue::query()->sole()->art);

        Livewire::test(CreateGeorgsboteIssue::class)
            ->fillForm(['art' => 'brief', 'year' => 2023, 'issue_no' => 13, 'title' => 'X', 'pdf_path' => UploadedFile::fake()->create('y.pdf', 10, 'application/pdf')])
            ->call('create')
            ->assertHasFormErrors(['issue_no']);
    }
}
