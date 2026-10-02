<?php

namespace Tests\Feature\Georgsbote;

use App\Filament\Resources\GeorgsboteIssues\Pages\CreateGeorgsboteIssue;
use App\Models\AuditLog;
use App\Models\GeorgsboteIssue;
use App\Models\User;
use App\Services\BrevoNewsletter;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Georgsbote, Pflichtenheft 6.6 und Abnahmekriterium 4.
 */
class GeorgsboteTest extends TestCase
{
    use RefreshDatabase;

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

    #[TestDox('Archiv zeigt nur veröffentlichte und datenschutzgeprüfte Ausgaben')]
    public function test_archive_lists_only_published_and_checked_issues(): void
    {
        Storage::fake('local');
        $this->issue(2025, 2, ['title' => 'Sommerlager']);
        $this->issue(2025, 1, ['title' => 'Ungeprüft', 'privacy_checked' => false]);
        $this->issue(2024, 3, ['title' => 'Entwurf', 'published' => false]);
        $this->issue(2026, 1, ['title' => 'Zukunft', 'published_at' => now()->addMonth()]);

        $this->get('/georgsbote')->assertOk()
            ->assertSee('Sommerlager')
            ->assertDontSee('Ungeprüft')
            ->assertDontSee('Entwurf')
            ->assertDontSee('Zukunft');
    }

    #[TestDox('PDF nur für veröffentlichte Ausgaben; Abrufe werden gezählt')]
    public function test_pdf_is_served_only_for_published_issues_and_counted(): void
    {
        Storage::fake('local');
        $issue = $this->issue(2025, 2);
        $this->issue(2025, 3, ['published' => false]);

        $this->get('/georgsbote/2025/2.pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(1, $issue->fresh()->downloads);

        $this->get('/georgsbote/2025/3.pdf')->assertNotFound();
        $this->get('/georgsbote/2025/9.pdf')->assertNotFound();
    }

    #[TestDox('Ausgabe lässt sich ohne Datenschutzprüfung nicht veröffentlichen')]
    public function test_issue_cannot_be_published_without_privacy_check(): void
    {
        Storage::fake('local');
        Filament::setCurrentPanel('admin');
        $verwaltung = $this->verwaltung();
        $this->actingAs($verwaltung);

        $pdf = UploadedFile::fake()->create('ausgabe.pdf', 100, 'application/pdf');

        Livewire::test(CreateGeorgsboteIssue::class)
            ->fillForm(['year' => 2025, 'issue_no' => 1, 'title' => 'Test', 'pdf_path' => $pdf, 'privacy_checked' => false, 'published' => true])
            ->call('create')
            ->assertHasFormErrors(['published']);

        Livewire::test(CreateGeorgsboteIssue::class)
            ->fillForm(['year' => 2025, 'issue_no' => 1, 'title' => 'Test', 'pdf_path' => $pdf, 'privacy_checked' => true, 'published' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $issue = GeorgsboteIssue::query()->firstOrFail();
        $this->assertTrue($issue->published);
        $this->assertSame($verwaltung->id, $issue->privacy_checked_by);
        Storage::disk('local')->assertExists($issue->pdf_path);
    }

    #[TestDox('Nur die Verwaltung pflegt das Archiv')]
    public function test_only_verwaltung_manages_archive(): void
    {
        $autor = User::query()->create(['name' => 'Autor', 'email' => 'autor@dpsg-trier.de', 'status' => 'active']);
        $autor->roleAssignments()->create(['role' => 'autor', 'scope_type' => 'own']);

        $this->actingAs($autor)->get('/admin/georgsbote')->assertForbidden();
        $this->actingAs($this->verwaltung())->get('/admin/georgsbote')->assertOk();
    }

    #[TestDox('Newsletter-Anmeldung ohne Brevo-Zugang bleibt folgenlos')]
    public function test_newsletter_signup_without_brevo_config_does_nothing(): void
    {
        Http::fake();
        config(['services.brevo.api_key' => null]);

        $this->get('/georgsbrief')->assertSee('Die Online-Anmeldung wird gerade eingerichtet');
        $this->post('/georgsbrief/anmelden', ['email' => 'a@example.org', 'einwilligung' => '1'])
            ->assertSessionHas('newsletter_status', 'inaktiv');

        Http::assertNothingSent();
    }

    #[TestDox('Newsletter-Anmeldung per Bestätigungsmail, ohne Adresse bei uns zu speichern')]
    public function test_newsletter_signup_requests_double_opt_in_and_stores_no_address(): void
    {
        config(['services.brevo' => ['api_key' => 'test-key', 'newsletter_list_id' => '7', 'doi_template_id' => '12', 'doi_redirect_url' => 'https://test.dpsg-trier.de/georgsbrief/danke']]);
        Http::fake([BrevoNewsletter::ENDPOINT => Http::response(null, 201)]);

        $this->post('/georgsbrief/anmelden', ['email' => 'Erika@Example.org', 'vorname' => 'Erika', 'einwilligung' => '1'])
            ->assertRedirect()
            ->assertSessionHas('newsletter_status', 'angefragt');

        Http::assertSent(fn ($request) => $request->url() === BrevoNewsletter::ENDPOINT
            && $request->hasHeader('api-key', 'test-key')
            && $request['email'] === 'erika@example.org'
            && $request['includeListIds'] === [7]
            && $request['templateId'] === 12
            && $request['redirectionUrl'] === 'https://test.dpsg-trier.de/georgsbrief/danke');

        // Abnahmekriterium 4: keine E-Mail-Adresse im CMS gespeichert.
        $this->assertTrue(AuditLog::query()->where('action', 'like', 'newsletter.doi:angefragt:%')->exists());
        foreach (Schema::getTableListing(schemaQualified: false) as $table) {
            foreach (DB::table($table)->get() as $row) {
                $this->assertStringNotContainsStringIgnoringCase('erika@example.org', json_encode($row), "Adresse in Tabelle {$table} gefunden");
            }
        }
    }

    #[TestDox('Newsletter-Anmeldung braucht Einwilligung und ignoriert Bots')]
    public function test_newsletter_signup_requires_consent_and_ignores_bots(): void
    {
        config(['services.brevo' => ['api_key' => 'k', 'newsletter_list_id' => '1', 'doi_template_id' => '1', 'doi_redirect_url' => null]]);
        Http::fake();

        $this->post('/georgsbrief/anmelden', ['email' => 'a@example.org'])->assertSessionHasErrors('einwilligung');
        $this->post('/georgsbrief/anmelden', ['email' => 'a@example.org', 'einwilligung' => '1', 'website' => 'spam'])
            ->assertSessionHas('newsletter_status', 'angefragt');

        Http::assertNothingSent();
    }
}
