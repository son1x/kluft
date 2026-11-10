<?php

namespace Tests\Feature\Material;

use App\Filament\Resources\Material\MaterialResource;
use App\Filament\Resources\Material\Pages\BelegungMaterial;
use App\Filament\Resources\Material\Pages\CreateReservierung;
use App\Filament\Resources\Material\Pages\ListReservierungen;
use App\Mail\MaterialMail;
use App\Models\Material;
use App\Models\MaterialReservierung;
use App\Models\User;
use App\Services\PrivacyRequests;
use App\Support\Features;
use App\Support\RoleCatalog;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Concerns\LoestArbeitsnachweis;
use Tests\TestCase;

/**
 * Materialverleih (docs/materialverleih.md).
 */
class MaterialverleihTest extends TestCase
{
    use LoestArbeitsnachweis, RefreshDatabase;

    private Material $jurte;

    private Material $kohten;

    protected function setUp(): void
    {
        parent::setUp();
        Features::setzen(Features::MATERIALVERLEIH, true);
        Features::setzen(Features::MATERIALVERLEIH_OEFFENTLICH, true);
        Filament::setCurrentPanel('admin');
        Mail::fake();
        $this->travelTo('2026-11-10 10:00');
        $this->jurte = Material::query()->create(['name' => 'Jurte 6 m', 'kategorie' => 'Zelte', 'menge' => 1, 'ort' => 'Keller']);
        $this->kohten = Material::query()->create(['name' => 'Kohte', 'kategorie' => 'Zelte', 'menge' => 3]);
    }

    private function user(string $email, string $role): User
    {
        $user = User::query()->create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'status' => 'active']);
        $user->roleAssignments()->create(['role' => $role, 'scope_type' => 'global']);

        return $user->fresh();
    }

    /** @param  array<string, mixed>  $attrs */
    private function reservierung(Material $m, array $attrs): MaterialReservierung
    {
        return $m->reservierungen()->create(['name' => 'Stamm X', 'email' => 'x@example.org', 'status' => 'zugesagt', ...$attrs]);
    }

    #[TestDox('Belegung: zugesagte und ausgegebene zählen am vollsten Tag; Anfragen und Absagen nicht')]
    public function test_belegung(): void
    {
        $this->reservierung($this->kohten, ['von' => '2026-12-01', 'bis' => '2026-12-05', 'menge' => 2]);
        $this->reservierung($this->kohten, ['von' => '2026-12-05', 'bis' => '2026-12-07', 'menge' => 1, 'status' => 'ausgegeben']);
        $this->reservierung($this->kohten, ['von' => '2026-12-01', 'bis' => '2026-12-31', 'menge' => 3, 'status' => 'angefragt']);
        $this->reservierung($this->kohten, ['von' => '2026-12-01', 'bis' => '2026-12-31', 'menge' => 3, 'status' => 'abgelehnt']);

        $this->assertSame(3, $this->kohten->belegt('2026-12-01', '2026-12-10'));
        $this->assertSame(0, $this->kohten->frei('2026-12-05', '2026-12-05'));
        $this->assertSame(2, $this->kohten->frei('2026-12-06', '2026-12-20'));
        $this->assertSame(3, $this->kohten->frei('2026-12-08', '2026-12-20'));
    }

    #[TestDox('Zugriff für Materialwart*in (eigene Rolle) und Verwaltung; Rolle ist vergebbar')]
    public function test_zugriff(): void
    {
        $this->assertArrayHasKey('material|global|', RoleCatalog::all());
        $this->actingAs($this->user('wart@example.org', 'material'))->get(MaterialResource::getUrl())->assertOk();
        $this->actingAs($this->user('buero@example.org', 'verwaltung'))->get(MaterialResource::getUrl('belegung', ['record' => $this->jurte]))->assertOk()->assertSee('Belegung: Jurte 6 m');
        $this->actingAs($this->user('autor@example.org', 'vorstand'))->get(MaterialResource::getUrl())->assertForbidden();
    }

    #[TestDox('Zusagen nur, wenn frei: Konflikt verhindert die Zusage, Mail bei Zusage; Rückgabe mit Mängeln')]
    public function test_zusagen(): void
    {
        $this->actingAs($this->user('wart@example.org', 'material'));
        $this->reservierung($this->jurte, ['von' => '2026-12-01', 'bis' => '2026-12-03']);
        $anfrage = $this->reservierung($this->jurte, ['von' => '2026-12-03', 'bis' => '2026-12-04', 'status' => 'angefragt', 'email' => 'kim@example.org']);
        $frei = $this->reservierung($this->jurte, ['von' => '2026-12-10', 'bis' => '2026-12-12', 'status' => 'angefragt', 'email' => 'ben@example.org']);

        Livewire::test(ListReservierungen::class)
            ->assertSet('activeTab', 'anfragen')
            ->callTableAction('zusagen', $anfrage)->assertNotified('Nicht zugesagt')
            ->callTableAction('zusagen', $frei);
        $this->assertSame(['angefragt', 'zugesagt'], [$anfrage->fresh()->status, $frei->fresh()->status]);
        Mail::assertQueued(MaterialMail::class, fn (MaterialMail $m) => $m->art === 'zugesagt' && $m->hasTo('ben@example.org'));
        Mail::assertNotQueued(MaterialMail::class, fn (MaterialMail $m) => $m->hasTo('kim@example.org'));

        Livewire::test(ListReservierungen::class)->set('activeTab', 'kommend')->callTableAction('ausgeben', $frei);
        Livewire::test(ListReservierungen::class)->set('activeTab', 'unterwegs')->callTableAction('zurueck', $frei, ['maengel' => 'Hering fehlt']);
        $this->assertSame(['zurueck', 'Hering fehlt'], [$frei->fresh()->status, $frei->fresh()->maengel]);

        // Im Backend direkt zugesagt: ebenfalls mit Prüfung.
        Livewire::test(CreateReservierung::class)
            ->fillForm(['material_id' => $this->jurte->id, 'von' => '2026-12-02', 'bis' => '2026-12-02', 'name' => 'Stamm Y', 'status' => 'zugesagt'])
            ->call('create')->assertHasErrors(['data.von']);
        Livewire::test(CreateReservierung::class)
            ->fillForm(['material_id' => $this->jurte->id, 'von' => '2026-12-20', 'bis' => '2026-12-22', 'name' => 'Stamm Y', 'email' => 'y@example.org', 'status' => 'zugesagt'])
            ->call('create')->assertHasNoFormErrors();
        Mail::assertQueued(MaterialMail::class, fn (MaterialMail $m) => $m->hasTo('y@example.org'));

        Livewire::test(BelegungMaterial::class, ['record' => $this->jurte->getRouteKey()])
            ->set('monat', '2026-12')->assertSee('Dezember 2026')->assertSee('Stamm Y')->call('blaettern', 1)->assertSet('monat', '2027-01');
    }

    #[TestDox('Website: Liste und Anfrage mit Spamschutz; Mail an die Materialwart*in; ohne Schalter 404')]
    public function test_website(): void
    {
        $wart = $this->user('wart@example.org', 'material');
        $this->get('/material')->assertOk()->assertSee('Jurte 6 m')->assertSee('Kohte')->assertSee('3 Stück');

        $daten = ['material_id' => $this->kohten->id, 'menge' => 2, 'von' => '2026-12-01', 'bis' => '2026-12-03', 'name' => 'Lea', 'email' => 'Lea@Example.org', 'gruppierung' => 'Stamm Z', 'zweck' => 'Wölflingslager'];
        $this->post('/material/anfrage', $daten + ['altcha' => $this->arbeitsnachweis(), '_t' => Crypt::encryptString((string) now()->subMinute()->timestamp)])
            ->assertRedirect(route('material.index').'#anfrage');
        $r = MaterialReservierung::query()->sole();
        $this->assertSame(['angefragt', 2, 'lea@example.org'], [$r->status, $r->menge, $r->email]);
        Mail::assertQueued(MaterialMail::class, fn (MaterialMail $m) => $m->art === 'anfrage' && $m->hasTo($wart->email));
        $this->assertStringContainsString('3 von 3 frei', (new MaterialMail($r, 'anfrage'))->render());

        // Ohne Arbeitsnachweis: gespeichert als Spamverdacht, ohne Mail.
        $this->post('/material/anfrage', ['email' => 'spam@example.org'] + $daten + ['_t' => Crypt::encryptString((string) now()->subMinute()->timestamp)]);
        $this->assertStringStartsWith('Spamverdacht', (string) MaterialReservierung::query()->where('email', 'spam@example.org')->sole()->notiz);
        Mail::assertQueued(MaterialMail::class, 1);
        MaterialReservierung::query()->where('email', 'spam@example.org')->delete();

        // Bot (zu schnell): stillschweigend verworfen.
        $this->post('/material/anfrage', $daten + ['_t' => Crypt::encryptString((string) now()->timestamp)]);
        $this->assertSame(1, MaterialReservierung::query()->count());

        Features::setzen(Features::MATERIALVERLEIH_OEFFENTLICH, false);
        $this->get('/material')->assertNotFound();
    }

    #[TestDox('Täglich: Rückgabe-Erinnerung am Vortag einmal; erledigte nach 6 Monaten gelöscht; Datenschutzauskunft')]
    public function test_taeglich(): void
    {
        $unterwegs = $this->reservierung($this->jurte, ['von' => '2026-11-08', 'bis' => '2026-11-11', 'status' => 'ausgegeben']);
        $this->reservierung($this->jurte, ['von' => '2026-04-01', 'bis' => '2026-04-02', 'status' => 'zurueck']);

        $this->artisan('material:taeglich')->expectsOutput('1 Erinnerungen, 1 gelöscht.');
        Mail::assertQueued(MaterialMail::class, fn (MaterialMail $m) => $m->art === 'rueckgabe');
        $this->artisan('material:taeglich')->expectsOutput('0 Erinnerungen, 0 gelöscht.');
        $this->assertNotNull($unterwegs->fresh()->erinnert_am);

        $privacy = app(PrivacyRequests::class);
        $this->assertCount(1, $privacy->find('X@example.org')['material']);
        $this->assertSame(1, $privacy->delete('x@example.org')['material']);
    }
}
