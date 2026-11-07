<?php

namespace Tests\Feature\Georgsbote;

use App\Filament\Resources\GeorgsboteIssues\Pages\EditGeorgsboteIssue;
use App\Filament\Resources\GeorgsboteIssues\Pages\ListGeorgsboteIssues;
use App\Models\AuditLog;
use App\Models\GeorgsboteIssue;
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
 * Titelbilder aus Seite 1 des PDFs (F-GB-01, docs/auftrag-titelbilder.md).
 * Das Rendern selbst passiert im Browser (pdf.js) und wird hier nicht getestet.
 */
#[Group('dv-trier')]
class GeorgsboteTitelbildTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function issue(int $year, int $no, array $attrs = []): GeorgsboteIssue
    {
        Storage::disk('local')->put("georgsbote/{$year}-{$no}.pdf", '%PDF-1.4 test');

        return GeorgsboteIssue::query()->create([
            'year' => $year, 'issue_no' => $no, 'title' => "Ausgabe {$no}",
            'pdf_path' => "georgsbote/{$year}-{$no}.pdf",
            'published' => true, 'privacy_checked' => true, ...$attrs,
        ]);
    }

    private function verwaltung(): User
    {
        $user = User::query()->create(['name' => 'Büro', 'email' => 'buero@dpsg-trier.de', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);

        return $user->fresh();
    }

    private function autor(): User
    {
        $user = User::query()->create(['name' => 'Autor', 'email' => 'autor@dpsg-trier.de', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'autor', 'scope_type' => 'own']);

        return $user->fresh();
    }

    private function hochladen(GeorgsboteIssue $issue, UploadedFile $bild, bool $ueberschreiben = false)
    {
        return $this->post(route('georgsbote.titelbild', $issue), ['bild' => $bild, 'ueberschreiben' => $ueberschreiben ? '1' : '0'], ['Accept' => 'application/json']);
    }

    #[TestDox('PDF für die Redaktion: Gäste zur Anmeldung, ohne Recht 403, Verwaltung auch unveröffentlicht, ohne Zählung')]
    public function test_vorschau_pdf(): void
    {
        $issue = $this->issue(2019, 2, ['published' => false, 'privacy_checked' => false]);
        $url = route('georgsbote.vorschau-pdf', $issue);

        $this->get($url)->assertRedirect('/admin/login');
        $this->actingAs($this->autor())->get($url)->assertForbidden();

        $this->actingAs($this->verwaltung())->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(0, $issue->fresh()->downloads);
    }

    #[TestDox('Gültiges WebP oder PNG wird gespeichert und als Titelbild gesetzt')]
    public function test_titelbild_wird_gespeichert(): void
    {
        $this->actingAs($this->verwaltung());
        $bote = $this->issue(2019, 2);
        $zweiter = $this->issue(2019, 3);

        $antwort = $this->hochladen($bote, UploadedFile::fake()->image('titel.webp', 800, 1131))
            ->assertOk()->assertJson(['ok' => true]);

        $pfad = $bote->fresh()->cover_path;
        $this->assertMatchesRegularExpression('#^georgsbote/titel-2019-2-[a-z0-9]{10}\.webp$#', $pfad);
        Storage::disk('public')->assertExists($pfad);
        $this->assertSame($bote->fresh()->coverUrl(), $antwort->json('cover_url'));
        $this->assertTrue(AuditLog::query()->where('action', 'georgsbote.titelbild')->where('subject_id', $bote->id)->exists());

        $this->hochladen($zweiter, UploadedFile::fake()->image('titel.png', 800, 1131))->assertOk();
        $this->assertStringEndsWith('.png', $zweiter->fresh()->cover_path);
    }

    #[TestDox('Vorhandenes Titelbild bleibt ohne „überschreiben“ und wird mit „überschreiben“ ersetzt')]
    public function test_vorhandenes_titelbild_nur_mit_ueberschreiben(): void
    {
        $this->actingAs($this->verwaltung());
        Storage::disk('public')->put('georgsbote/von-hand.jpg', 'alt');
        $bote = $this->issue(2018, 1, ['cover_path' => 'georgsbote/von-hand.jpg']);

        $this->hochladen($bote, UploadedFile::fake()->image('titel.webp', 800, 1131))
            ->assertStatus(409)->assertJson(['ok' => false]);
        $this->assertSame('georgsbote/von-hand.jpg', $bote->fresh()->cover_path);
        Storage::disk('public')->assertExists('georgsbote/von-hand.jpg');
        $this->assertCount(1, Storage::disk('public')->files('georgsbote'));

        $this->hochladen($bote, UploadedFile::fake()->image('titel.webp', 800, 1131), ueberschreiben: true)
            ->assertOk()->assertJson(['ok' => true]);
        $neu = $bote->fresh()->cover_path;
        $this->assertNotSame('georgsbote/von-hand.jpg', $neu);
        Storage::disk('public')->assertExists($neu);
        Storage::disk('public')->assertMissing('georgsbote/von-hand.jpg');
    }

    #[TestDox('SVG, PDF, zu große und zu breite Bilder werden abgelehnt')]
    public function test_ungueltige_bilder_werden_abgelehnt(): void
    {
        $this->actingAs($this->verwaltung());
        $bote = $this->issue(2017, 1);
        $svg = UploadedFile::fake()->createWithContent('titel.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><script>alert(1)</script></svg>');

        $this->hochladen($bote, $svg)->assertStatus(422)->assertJson(['ok' => false]);
        $this->hochladen($bote, UploadedFile::fake()->create('titel.pdf', 100, 'application/pdf'))->assertStatus(422);
        $this->hochladen($bote, UploadedFile::fake()->image('titel.png', 800, 1131)->size(2049))->assertStatus(422);
        $this->hochladen($bote, UploadedFile::fake()->image('titel.png', 2001, 1000))->assertStatus(422);
        $this->post(route('georgsbote.titelbild', $bote), [], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertNull($bote->fresh()->cover_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    #[TestDox('Georgsbriefe bekommen kein Titelbild (422)')]
    public function test_georgsbrief_bekommt_kein_titelbild(): void
    {
        $this->actingAs($this->verwaltung());
        $brief = $this->issue(2019, 2, ['art' => GeorgsboteIssue::BRIEF]);

        $this->hochladen($brief, UploadedFile::fake()->image('titel.webp', 800, 1131))->assertStatus(422)->assertJson(['ok' => false]);
        $this->assertNull($brief->fresh()->cover_path);
    }

    #[TestDox('Upload nur mit Recht: Gast zur Anmeldung, Autor 403')]
    public function test_upload_nur_mit_recht(): void
    {
        $bote = $this->issue(2019, 2);

        $this->post(route('georgsbote.titelbild', $bote), ['bild' => UploadedFile::fake()->image('titel.webp', 800, 1131)])
            ->assertRedirect('/admin/login');
        $this->actingAs($this->autor());
        $this->hochladen($bote, UploadedFile::fake()->image('titel.webp', 800, 1131))->assertForbidden();
        $this->assertNull($bote->fresh()->cover_path);
    }

    #[TestDox('„Titelbilder erzeugen“ erscheint nur, wenn Georgsboten ohne Titelbild da sind')]
    public function test_kopfaktion_nur_bei_fehlenden_titelbildern(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->verwaltung());
        // Georgsbriefe ohne Titelbild zählen nicht.
        $this->issue(2019, 2, ['art' => GeorgsboteIssue::BRIEF]);
        $bote = $this->issue(2019, 1);
        $zweiter = $this->issue(2018, 1);
        $this->issue(2017, 1, ['cover_path' => 'georgsbote/von-hand.jpg']);

        Livewire::test(ListGeorgsboteIssues::class)
            ->assertActionVisible('titelbilder')
            ->mountAction('titelbilder')
            ->assertActionMounted('titelbilder')
            ->assertMountedActionModalSee('2 Georgsboten ohne Titelbild');

        // Knopf „Titelbilder jetzt erzeugen“ in der Meldung nach dem Hochladen.
        Livewire::test(ListGeorgsboteIssues::class)
            ->dispatch('titelbilder-oeffnen')
            ->assertActionMounted('titelbilder');

        $bote->update(['cover_path' => 'georgsbote/titel.webp']);
        $zweiter->update(['cover_path' => 'georgsbote/titel2.webp']);

        Livewire::test(ListGeorgsboteIssues::class)->assertActionHidden('titelbilder');
    }

    #[TestDox('„Titelbild aus PDF erzeugen“ gibt es auf der Bearbeiten-Seite nur bei Georgsboten')]
    public function test_bearbeiten_aktion_nur_bei_georgsboten(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->verwaltung());
        $bote = $this->issue(2019, 1);
        $brief = $this->issue(2019, 2, ['art' => GeorgsboteIssue::BRIEF]);

        Livewire::test(EditGeorgsboteIssue::class, ['record' => $bote->getRouteKey()])->assertActionVisible('titelbild');
        Livewire::test(EditGeorgsboteIssue::class, ['record' => $brief->getRouteKey()])->assertActionHidden('titelbild');
    }

    #[TestDox('Nach dem Erzeugen übernimmt die Bearbeiten-Seite das neue Titelbild, „Speichern“ leert es nicht')]
    public function test_bearbeiten_seite_uebernimmt_neues_titelbild(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->verwaltung());
        $bote = $this->issue(2019, 1);

        $seite = Livewire::test(EditGeorgsboteIssue::class, ['record' => $bote->getRouteKey()]);
        Storage::disk('public')->put('georgsbote/titel-2019-1-abc.webp', 'bild');
        $bote->update(['cover_path' => 'georgsbote/titel-2019-1-abc.webp']);

        $seite->dispatch('titelbilder-erzeugt', erzeugt: 1, fehler: [])
            ->assertNotified('Erzeugt: 1, Fehler: 0')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('georgsbote/titel-2019-1-abc.webp', $bote->fresh()->cover_path);
    }

    #[TestDox('Seiten ohne Georgsbote laden den Renderer nicht')]
    public function test_renderer_nur_auf_georgsbote_seiten(): void
    {
        $this->actingAs($this->verwaltung());
        $this->issue(2019, 1);

        $this->get('/admin/georgsbote')->assertOk()->assertSee('titelbild-', false);
        $this->get('/admin')->assertOk()->assertDontSee('titelbild-', false);
    }
}
