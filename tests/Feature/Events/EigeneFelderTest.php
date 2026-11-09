<?php

namespace Tests\Feature\Events;

use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\ParticipantExport;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Formular-Baukasten → „Eigene Felder“: Fragen, die es unter den festen
 * Feldern nicht gibt (Zahl, Text, Auswahl, Mehrfachauswahl), im Formular,
 * bei der Prüfung, in den Anmeldedaten und im Export.
 */
class EigeneFelderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    /** @return array<string, mixed> */
    private static function baukasten(): array
    {
        return [
            'phone' => 'optional',
            'comment' => 'optional',
            'eigene' => [
                ['schluessel' => 'eigen_anzahl', 'label' => 'Anzahl der Kinder', 'typ' => 'zahl', 'pflicht' => true, 'hinweis' => 'Ungefähr reicht.'],
                ['schluessel' => 'eigen_aktion', 'label' => 'Welche Aktion?', 'typ' => 'mehrfach', 'pflicht' => true, 'optionen' => "Karte\nVideo\n\nKarte"],
                ['schluessel' => 'eigen_versand', 'label' => 'Versand', 'typ' => 'auswahl', 'pflicht' => true, 'optionen' => "Per Post\nSelbst drucken"],
                ['schluessel' => 'eigen_adresse', 'label' => 'Adresse', 'typ' => 'textarea', 'pflicht' => false],
                ['label' => 'Ohne Antworten', 'typ' => 'auswahl', 'optionen' => ''],
                ['label' => '', 'typ' => 'text'],
            ],
        ];
    }

    private function event(): Event
    {
        return Event::query()->create([
            'title' => 'Weihnachtsaktion', 'slug' => 'weihnachtsaktion', 'starts_at' => now()->addMonth(), 'status' => 'open',
            'adults_only' => true, 'form_fields' => self::baukasten(),
        ]);
    }

    #[TestDox('Eigene Felder stehen vor den Anmerkungen; leere und Auswahl ohne Antworten fallen weg; Schlüssel bleiben fest')]
    public function test_felder(): void
    {
        $event = $this->event();
        $felder = $event->formFields();

        $this->assertSame(['phone', 'eigen_anzahl', 'eigen_aktion', 'eigen_versand', 'eigen_adresse', 'comment'], array_keys($felder));
        $this->assertSame(['Karte' => 'Karte', 'Video' => 'Video'], $felder['eigen_aktion']['options']);
        $this->assertSame(['select', 'zahl', 'textarea'], [$felder['eigen_versand']['type'], $felder['eigen_anzahl']['type'], $felder['eigen_adresse']['type']]);
        $this->assertSame('Ungefähr reicht.', $felder['eigen_anzahl']['hint']);

        // Beim Speichern bekommen Felder ohne Schlüssel einen eigenen.
        $eigene = $event->fresh()->form_fields['eigene'];
        $this->assertSame('eigen_anzahl', $eigene[0]['schluessel']);
        $this->assertMatchesRegularExpression('/^eigen_[a-z0-9]{8}$/', $eigene[4]['schluessel']);
    }

    #[TestDox('Anmeldung mit eigenen Feldern: Prüfung, gespeicherte Angaben, Anzeige und Export')]
    public function test_anmeldung(): void
    {
        $event = $this->event();
        $this->get('/veranstaltungen/weihnachtsaktion')->assertOk()
            ->assertSeeInOrder(['Anzahl der Kinder *', 'type="number"', 'Ungefähr reicht.', 'Welche Aktion? *', 'name="eigen_aktion[]" value="Karte"', 'Versand *', 'Selbst drucken', 'Adresse', 'Anmerkungen'], false);

        $basis = ['first_name' => 'Mia', 'last_name' => 'Muster', 'email' => 'mia@example.org', 'consent' => '1'];
        $this->post('/veranstaltungen/weihnachtsaktion/anmeldung', [...$basis, 'eigen_anzahl' => 'viele', 'eigen_aktion' => ['Torte'], 'eigen_versand' => 'Brieftaube'])
            ->assertSessionHasErrors(['eigen_anzahl' => 'Bitte gib eine ganze Zahl an.', 'eigen_aktion.0', 'eigen_versand']);
        $this->post('/veranstaltungen/weihnachtsaktion/anmeldung', [...$basis, 'eigen_anzahl' => '-3'])
            ->assertSessionHasErrors(['eigen_anzahl' => 'Bitte gib eine Zahl von 0 bis 100.000 an.', 'eigen_aktion', 'eigen_versand']);

        $this->post('/veranstaltungen/weihnachtsaktion/anmeldung', [...$basis, 'eigen_anzahl' => '14', 'eigen_aktion' => ['Karte', 'Video'],
            'eigen_versand' => 'Selbst drucken', 'eigen_adresse' => "Weberbach 71\n54290 Trier"])->assertSessionHasNoErrors();

        $r = EventRegistration::query()->sole();
        $this->assertSame(['14', ['Karte', 'Video'], 'Selbst drucken', "Weberbach 71\n54290 Trier"],
            [(string) $r->data['eigen_anzahl'], $r->data['eigen_aktion'], $r->data['eigen_versand'], $r->data['eigen_adresse']]);
        $this->assertSame('Karte, Video', $event->fieldValueLabel($event->formFields()['eigen_aktion'], $r->data['eigen_aktion']));

        $r->update(['status' => 'confirmed', 'confirmed_at' => now()]);
        $export = app(ParticipantExport::class);
        $this->assertContains('Welche Aktion?', $export->headings($event));
        $this->assertContains('Karte, Video', $export->rows($event)[0]);
    }

    #[TestDox('Im Backend: eigene Felder anlegen (Repeater), Art und Antwortmöglichkeiten werden geprüft')]
    public function test_backend(): void
    {
        $user = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'admin', 'scope_type' => 'global']);
        Filament::setCurrentPanel('admin');
        $this->actingAs($user->fresh());
        $repeater = Repeater::fake();

        Livewire::test(CreateEvent::class)
            ->fillForm(['title' => 'Aktion', 'slug' => 'aktion', 'starts_at' => now()->addMonth(), 'status' => 'open', 'retention_days' => 60,
                'form_fields' => ['eigene' => [['label' => 'Welche Aktion?', 'typ' => 'mehrfach', 'optionen' => '', 'pflicht' => true]]]])
            ->call('create')
            ->assertHasFormErrors(['form_fields.eigene.0.optionen' => 'required']);

        Livewire::test(CreateEvent::class)
            ->fillForm(['title' => 'Aktion', 'slug' => 'aktion', 'starts_at' => now()->addMonth(), 'status' => 'open', 'retention_days' => 60,
                'form_fields' => ['eigene' => [
                    ['label' => 'Anzahl', 'typ' => 'zahl', 'pflicht' => true],
                    ['label' => 'Welche Aktion?', 'typ' => 'mehrfach', 'optionen' => "Karte\nVideo", 'pflicht' => false],
                ]]])
            ->call('create')
            ->assertHasNoFormErrors();
        $repeater();

        $felder = Event::query()->sole()->eigeneFelder();
        $this->assertCount(2, $felder);
        $this->assertSame(['Anzahl', 'Welche Aktion?'], array_column($felder, 'label'));
        $this->assertTrue(array_values($felder)[0]['required']);
        $this->assertSame(['Karte' => 'Karte', 'Video' => 'Video'], array_values($felder)[1]['options']);
    }

    private function migration(): object
    {
        $migration = require base_path('instanzen/dv-trier/migrations/2026_11_09_160000_weihnachtspostkartenaktion_2026.php');
        $migration->auchInTests = true;

        return $migration;
    }

    #[TestDox('DV Trier: Anmeldung zur Wö- und Biber-Weihnachtspostkartenaktion (Inhaltsmigration)')]
    public function test_weihnachtspostkartenaktion(): void
    {
        $this->travelTo('2026-11-10 12:00');
        $this->migration()->up();

        $event = Event::query()->where('slug', 'weihnachtspostkartenaktion-2026')->sole();
        $this->assertSame(['stamm', 'phone', 'eigen_anzahlwb', 'eigen_adresse', 'eigen_aktion', 'eigen_vorlage', 'eigen_fragen'], array_values(array_diff(array_keys($event->formFields()), ['herkunft'])));
        $this->assertTrue($event->acceptsRegistrations());
        $this->get('/veranstaltungen/weihnachtspostkartenaktion-2026')->assertOk()
            ->assertSee('Weberbach 71')->assertSee('08.12.2026')->assertSee('Versand der Kartenvorlage');

        $this->post('/veranstaltungen/weihnachtspostkartenaktion-2026/anmeldung', [
            'first_name' => 'Kim', 'last_name' => 'Leitung', 'email' => 'kim@example.org', 'consent' => '1',
            'stamm' => 'Stamm Sankt Georg', 'eigen_anzahlwb' => '18', 'eigen_adresse' => "Hauptstraße 1\n54290 Trier",
            'eigen_aktion' => ['Weihnachtskarte für einen Stamm im Diözesanverband gestalten'], 'eigen_vorlage' => 'Per Post an die Adresse oben',
        ])->assertSessionHasNoErrors();
        $this->assertSame('18', (string) EventRegistration::query()->sole()->data['eigen_anzahlwb']);

        // Erneut ausgeführt: nichts doppelt.
        $this->migration()->up();
        $this->assertSame(1, Event::query()->where('slug', 'weihnachtspostkartenaktion-2026')->count());

        $this->travelTo('2026-11-24 08:00');
        $this->assertFalse($event->fresh()->acceptsRegistrations());
    }
}
