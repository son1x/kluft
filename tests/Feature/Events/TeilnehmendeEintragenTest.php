<?php

namespace Tests\Feature\Events;

use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\RelationManagers\RegistrationsRelationManager;
use App\Mail\EventRegistrationMail;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\EventRegistrations;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Teilnehmende von Hand eintragen: einzeln und als Liste (eingefügt aus
 * Excel oder als .xlsx/.csv), ohne Double-Opt-In.
 */
class TeilnehmendeEintragenTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['mitglieder.driver' => 'off']);

        $this->event = Event::query()->create([
            'title' => 'Stufenkonferenz', 'slug' => 'stufenkonferenz', 'status' => 'open',
            'starts_at' => now()->addMonth(), 'capacity' => 3, 'waitlist_enabled' => true,
            'form_fields' => ['birth_year' => 'optional', 'plz' => 'optional', 'wohnort' => 'optional', 'diet' => 'optional'],
        ]);

        $user = User::query()->create(['name' => 'Büro', 'email' => 'buero@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global', 'scope_key' => null]);
        Filament::setCurrentPanel('admin');
        $this->actingAs($user->fresh());
    }

    private function tabelle(): Testable
    {
        return Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->event, 'pageClass' => EditEvent::class]);
    }

    #[TestDox('Einzeln: bestätigt ohne Double-Opt-In, mit Feldern der Veranstaltung, Protokoll; doppelt wird abgelehnt; E-Mail optional')]
    public function test_single(): void
    {
        $this->tabelle()
            ->callTableAction('addParticipant', data: [
                'first_name' => 'Mia', 'last_name' => 'Muster', 'email' => 'Mia@Example.org', 'birth_year' => 2010,
                'data' => ['plz' => '54290', 'wohnort' => 'Trier', 'diet' => 'vegan'], 'status' => 'auto', 'notify' => true,
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Mia Muster eingetragen (Bestätigt).');

        $mia = EventRegistration::query()->sole();
        $this->assertSame(['confirmed', 'mia@example.org', 2010, EventRegistrations::MANUELL], [$mia->status, $mia->email, $mia->birth_year, $mia->consent_text_version]);
        $this->assertSame(['plz' => '54290', 'wohnort' => 'Trier', 'diet' => 'vegan'], $mia->data);
        $this->assertNotNull($mia->confirmed_at);
        $this->assertNotNull($mia->cancel_token_hash);
        $this->assertSame($this->event->purgeAt()->toDateString(), $mia->purge_at->toDateString());
        Mail::assertQueued(EventRegistrationMail::class, fn ($mail) => $mail->kind === 'confirmed' && $mail->hasTo('mia@example.org'));
        $this->assertSame(1, AuditLog::query()->where('action', 'registration.manual')->where('user_id', auth()->id())->count());

        $this->tabelle()
            ->callTableAction('addParticipant', data: ['first_name' => 'mia', 'last_name' => 'MUSTER', 'status' => 'auto'])
            ->assertNotified('Nicht eingetragen: mia MUSTER ist schon angemeldet.');

        $this->tabelle()
            ->callTableAction('addParticipant', data: ['first_name' => 'Ben', 'last_name' => 'Berg', 'email' => '', 'status' => 'waitlist', 'notify' => true])
            ->assertHasNoTableActionErrors();
        $ben = EventRegistration::query()->where('last_name', 'Berg')->sole();
        $this->assertSame(['waitlist', ''], [$ben->status, $ben->email]);
        Mail::assertQueued(EventRegistrationMail::class, 1); // Ben ohne Adresse: keine Mail

        $this->tabelle()
            ->callTableAction('addParticipant', data: ['first_name' => 'X', 'last_name' => 'Y', 'email' => 'kaputt', 'status' => 'auto'])
            ->assertHasTableActionErrors(['email']);
    }

    #[TestDox('Liste aus Excel eingefügt: Kopfzeile ordnet Spalten zu, Platzzahl → Warteliste, Doppelte und fehlerhafte Zeilen werden gemeldet')]
    public function test_pasted_list(): void
    {
        $this->event->registrations()->create([
            'status' => 'confirmed', 'first_name' => 'Lea', 'last_name' => 'Klein', 'email' => 'lea@example.org',
            'consent_text_version' => 'v1', 'consent_at' => now(), 'confirmed_at' => now(),
        ]);

        $liste = implode("\n", [
            "Nachname\tVorname\tE-Mail\tJahrgang\tPLZ\tVerpflegung",
            "Muster\tMia\tmia@example.org\t2010\t54290\tVegetarisch",
            "Klein\tLea\tlea@example.org\t\t\t",
            "Berg\tBen\t\t2011\t\tvegan",
            "Sommer\tSina\t\t\t\t",
            "\tOhne\tx@example.org\t\t\t",
            "Kurz\tKai\t\t1850\t\t",
            "Winter\tWim\twim@example.org\t\t\t",
        ]);

        $this->tabelle()
            ->callTableAction('importParticipants', data: ['liste' => $liste, 'status' => 'auto', 'notify' => false])
            ->assertHasNoTableActionErrors()
            ->assertNotified();

        $neu = EventRegistration::query()->where('consent_text_version', EventRegistrations::MANUELL)->orderBy('id')->get();
        $this->assertSame(['Muster', 'Berg', 'Sommer', 'Winter'], $neu->pluck('last_name')->all());
        $this->assertSame(['confirmed', 'confirmed', 'waitlist', 'waitlist'], $neu->pluck('status')->all(), '3 Plätze, Lea war schon da');
        $this->assertSame(['plz' => '54290', 'diet' => 'vegetarisch'], $neu[0]->data);
        $this->assertSame(['diet' => 'vegan'], $neu[1]->data);
        $this->assertSame(2011, $neu[1]->birth_year);
        Mail::assertNothingQueued();
        $this->assertSame(4, AuditLog::query()->where('action', 'registration.manual')->count());
    }

    #[TestDox('Liste ohne Kopfzeile (Vorname, Nachname, E-Mail, Geburtsjahr) mit Semikolon; Spalte „Name“ wird geteilt')]
    public function test_list_formats(): void
    {
        $this->tabelle()->callTableAction('importParticipants', data: ['liste' => "Mia;Muster;mia@example.org;2010\nBen;Berg;;", 'status' => 'confirmed']);
        $this->assertSame(['Mia Muster', 'Ben Berg'], EventRegistration::query()->orderBy('id')->get()->map->fullName()->all());

        $this->tabelle()->callTableAction('importParticipants', data: ['liste' => "Name,E-Mail\n\"Lüdenscheidt, Lotta\",lotta@example.org\nAnna Maria Sommer,", 'status' => 'confirmed']);
        $this->assertTrue(EventRegistration::query()->where(['first_name' => 'Lotta', 'last_name' => 'Lüdenscheidt'])->exists());
        $this->assertTrue(EventRegistration::query()->where(['first_name' => 'Anna Maria', 'last_name' => 'Sommer'])->exists());
        $this->assertSame(4, EventRegistration::query()->where('status', 'confirmed')->count(), '„auch über die Platzzahl hinaus“');
    }

    #[TestDox('Liste mit Spalten „Stamm“ und „Gast“ (ja/leer); Einzeln mit Herkunft')]
    public function test_list_with_guests(): void
    {
        $this->event->update(['form_fields' => ['birth_year' => 'optional', 'stamm' => 'optional']]);
        $this->event->refresh();

        $this->tabelle()->callTableAction('importParticipants', data: [
            'liste' => "Vorname;Nachname;Stamm;Gast\nMia;Muster;Stamm Sankt Georg;\nLotta;Lang;DV Köln, Stamm Tonsur;ja\nKai;Kurz;;vielleicht",
            'status' => 'confirmed',
        ])->assertNotified();

        $this->assertNull(EventRegistration::query()->where('last_name', 'Muster')->sole()->data['herkunft'] ?? null);
        $lotta = EventRegistration::query()->where('last_name', 'Lang')->sole();
        $this->assertTrue($lotta->isGast());
        $this->assertSame('DV Köln, Stamm Tonsur', $lotta->data['stamm']);
        $this->assertFalse(EventRegistration::query()->where('last_name', 'Kurz')->exists(), '„vielleicht“ ist unklar');

        $this->tabelle()->callTableAction('addParticipant', data: [
            'first_name' => 'Vera', 'last_name' => 'VCP', 'data' => ['stamm' => 'VCP Trier', 'herkunft' => 'gast'], 'status' => 'auto',
        ])->assertHasNoTableActionErrors();
        $this->assertTrue(EventRegistration::query()->where('first_name', 'Vera')->sole()->isGast());
    }

    #[TestDox('Liste als Excel-Datei (.xlsx) hochladen; Datei wird danach gelöscht')]
    public function test_xlsx_upload(): void
    {
        $pfad = tempnam(sys_get_temp_dir(), 'tn').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($pfad);
        $writer->addRow(Row::fromValues(['Vorname', 'Nachname', 'E-Mail', 'Geburtsjahr', 'Wohnort']));
        $writer->addRow(Row::fromValues(['Mia', 'Muster', 'mia@example.org', 2010, 'Trier']));
        $writer->addRow(Row::fromValues(['Ben', 'Berg', '', 2011, '']));
        $writer->close();

        $this->tabelle()
            ->callTableAction('importParticipants', data: [
                'datei' => UploadedFile::fake()->createWithContent('teilnehmende.xlsx', (string) file_get_contents($pfad)),
                'status' => 'auto',
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified('2 Personen eingetragen (2 bestätigt).');

        $mia = EventRegistration::query()->where('last_name', 'Muster')->sole();
        $this->assertSame([2010, ['wohnort' => 'Trier']], [$mia->birth_year, $mia->data]);
        $this->assertSame([], Storage::disk('local')->files('tmp/teilnehmende'));
    }
}
