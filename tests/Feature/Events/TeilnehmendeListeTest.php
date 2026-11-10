<?php

namespace Tests\Feature\Events;

use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\RelationManagers\RegistrationsRelationManager;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\User;
use App\Services\TeilnehmendeListePdf;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Teilnehmendenliste für unterwegs (PDF).
 */
class TeilnehmendeListeTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        $this->event = Event::query()->create([
            'title' => 'Pfingstlager', 'slug' => 'pfingstlager', 'starts_at' => '2027-05-14 16:00', 'ends_at' => '2027-05-17 13:00', 'status' => 'open',
            'form_fields' => ['birth_year' => 'required', 'stamm' => 'optional', 'phone' => 'optional', 'notes' => 'optional'],
        ]);
        foreach ([['Kim', 'Zander', 'confirmed'], ['Ben', 'Adler', 'confirmed'], ['Lea', 'Berg', 'waitlist'], ['Tom', 'Weg', 'cancelled']] as [$v, $n, $s]) {
            $this->event->registrations()->create([
                'status' => $s, 'first_name' => $v, 'last_name' => $n, 'email' => mb_strtolower($v).'@example.org', 'birth_year' => 2015,
                'data' => ['stamm' => 'Stamm Sankt Georg', 'phone' => '0651 123'], 'guardian_name' => 'Eltern '.$n,
                'consent_text_version' => 'v1', 'consent_at' => now(),
            ]);
        }
    }

    #[TestDox('Felder: ohne lange Texte; Vorschlag mit Geburtsjahr, Stamm, Telefon')]
    public function test_felder(): void
    {
        $felder = TeilnehmendeListePdf::felder($this->event);
        $this->assertArrayHasKey('stamm', $felder);
        $this->assertArrayNotHasKey('notes', $felder);
        $this->assertSame(['birth_year', 'stamm', 'phone'], TeilnehmendeListePdf::standardFelder($this->event));
    }

    #[TestDox('PDF: bestätigte (optional Warteliste) alphabetisch, gewählte Spalten, Anwesenheit je Tag, Hinweis „vertraulich“')]
    public function test_pdf(): void
    {
        $liste = app(TeilnehmendeListePdf::class);
        $html = view('pdf.teilnehmende-liste', [
            'event' => $this->event, 'spalten' => ['Stamm'], 'tage' => ['Fr', 'Sa'], 'fonts' => '', 'logo' => null, 'mitSorge' => true,
            'personen' => collect([['name' => 'Adler, Ben', 'warteliste' => false, 'werte' => ['Stamm Sankt Georg'], 'sorge' => 'Eltern Adler']]),
        ])->render();
        $this->assertStringContainsString('Vertraulich', $html);
        $this->assertStringContainsString('Eltern Adler', $html);

        $pdf = $liste->pdf($this->event, ['birth_year', 'stamm'], true, false);
        $this->assertStringStartsWith('%PDF', $pdf);
        $pdf = $liste->pdf($this->event, ['stamm'], false, true);
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    #[TestDox('Backend: Aktion „Liste für unterwegs“ lädt das PDF herunter und wird protokolliert')]
    public function test_aktion(): void
    {
        $user = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);
        $this->actingAs($user->fresh());

        Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->event, 'pageClass' => EditEvent::class])
            ->mountTableAction('liste')
            ->assertTableActionDataSet(['felder' => ['birth_year', 'stamm', 'phone'], 'anwesenheit' => true, 'warteliste' => false])
            ->callMountedTableAction()
            ->assertFileDownloaded('Teilnehmende-pfingstlager-'.now()->format('Y-m-d').'.pdf');
        $this->assertSame(1, AuditLog::query()->where('action', 'event.liste')->count());
    }
}
