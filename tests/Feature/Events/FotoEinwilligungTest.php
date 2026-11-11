<?php

namespace Tests\Feature\Events;

use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\RelationManagers\FotosRelationManager;
use App\Filament\Resources\Events\RelationManagers\RegistrationsRelationManager;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\PrivacyRequests;
use App\Services\TeilnehmendeListePdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Freiwillige Foto-Einwilligung je Person – nur wenn bei der Veranstaltung
 * eingeschaltet (docs/datenschutz-ueberblick.md).
 */
class FotoEinwilligungTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function event(bool $foto = true): Event
    {
        return Event::query()->create([
            'title' => 'Familientag', 'slug' => 'familientag', 'starts_at' => now()->addMonth(), 'status' => 'open', 'adults_only' => true,
            'mehrere_personen' => true, 'foto_einwilligung' => $foto, 'form_fields' => ['birth_year' => 'optional'],
        ]);
    }

    /** @param  array<string, mixed>  $extra */
    private function anmelden(array $extra = []): TestResponse
    {
        return $this->post('/veranstaltungen/familientag/anmeldung', [
            'first_name' => 'Kim', 'last_name' => 'Muster', 'email' => 'kim@example.org', 'consent' => '1', ...$extra,
        ]);
    }

    #[TestDox('Ausgeschaltet: keine Frage im Formular, nichts gespeichert')]
    public function test_aus(): void
    {
        $this->event(false);
        $this->get('/veranstaltungen/familientag')->assertOk()->assertDontSee('name="foto"', false);
        $this->anmelden()->assertSessionHasNoErrors();
        $this->assertNull(EventRegistration::query()->sole()->foto_einwilligung);
    }

    #[TestDox('Eingeschaltet: Ja/Nein je Person, auch für weitere Personen; Antwort ist Pflicht')]
    public function test_an(): void
    {
        $event = $this->event();
        $this->get('/veranstaltungen/familientag')->assertOk()->assertSee('name="foto"', false)->assertSee('ein „Nein“ hat keine Nachteile', false);

        $this->anmelden(['weitere' => [['first_name' => 'Ben', 'last_name' => 'Muster']]])
            ->assertSessionHasErrors(['foto', 'weitere.0.foto']);
        $this->anmelden(['foto' => 'ja', 'weitere' => [['first_name' => 'Ben', 'last_name' => 'Muster', 'foto' => 'nein']]])
            ->assertSessionHasNoErrors();

        $personen = EventRegistration::query()->orderBy('id')->get();
        $this->assertSame([true, false], $personen->pluck('foto_einwilligung')->all());

        // Auskunft nennt die Antwort.
        $zip = new \ZipArchive;
        $zip->open(app(PrivacyRequests::class)->export('kim@example.org'));
        $auskunft = json_decode((string) $zip->getFromName('auskunft.json'), true);
        $this->assertEqualsCanonicalizing(['ja', 'nein'], array_column($auskunft['veranstaltungsanmeldungen'], 'foto_einwilligung'));

        // Backend: Spalte in der Liste, Hinweis im Fotos-Reiter.
        $personen->each->update(['status' => 'confirmed', 'confirmed_at' => now()]);
        $user = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'admin', 'scope_type' => 'global']);
        $this->actingAs($user->fresh());

        Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class])
            ->assertTableColumnVisible('foto_einwilligung')->assertSee('Nein');
        Livewire::test(FotosRelationManager::class, ['ownerRecord' => $event, 'pageClass' => EditEvent::class])
            ->assertSee('1 Person ohne Foto-Einwilligung: Ben Muster');

        // Liste für unterwegs markiert, wer nicht fotografiert werden möchte.
        $html = view('pdf.teilnehmende-liste', ['event' => $event, 'spalten' => [], 'tage' => [], 'fonts' => '', 'logo' => null, 'mitSorge' => false,
            'personen' => collect([['name' => 'Muster, Ben', 'warteliste' => false, 'keineFotos' => true, 'werte' => [], 'sorge' => null]])])->render();
        $this->assertStringContainsString('Keine Fotos', $html);
        $this->assertStringStartsWith('%PDF', app(TeilnehmendeListePdf::class)->pdf($event, [], false, false));
    }
}
