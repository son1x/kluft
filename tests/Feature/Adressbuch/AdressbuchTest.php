<?php

namespace Tests\Feature\Adressbuch;

use App\Filament\Resources\Adressbuch\AdressbuchVerteilerResource;
use App\Filament\Resources\Adressbuch\Pages\EditAdressbuchVerteiler;
use App\Filament\Resources\Adressbuch\Pages\ListAdressbuchKontakte;
use App\Filament\Resources\Adressbuch\Pages\ListAdressbuchVerteiler;
use App\Filament\Resources\Videokonferenzen\Pages\CreateVideokonferenz;
use App\Mail\VideokonferenzMail;
use App\Models\AdressbuchKontakt;
use App\Models\AdressbuchVerteiler;
use App\Models\User;
use App\Models\Videokonferenz;
use App\Services\PrivacyRequests;
use App\Support\Adressbuch;
use App\Support\Features;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Adressbuch mit Verteilern (docs/adressbuch.md).
 */
class AdressbuchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Features::setzen(Features::ADRESSBUCH, true);
        Features::setzen(Features::VIDEOKONFERENZEN, true);
        config(['services.jitsi' => ['url' => 'https://meet.dpsg-trier.de', 'secret' => str_repeat('s', 40), 'sub' => 'meet.dpsg-trier.de']]);
        Filament::setCurrentPanel('admin');
    }

    private function user(string $email, ?string $role = 'vorstand'): User
    {
        $user = User::query()->create(['name' => $email, 'email' => $email, 'status' => 'active']);
        if ($role) {
            $user->roleAssignments()->create(['role' => $role, 'scope_type' => 'global']);
        }

        return $user->fresh();
    }

    #[TestDox('Adressen einfügen: Outlook-Format, „Nachname, Vorname“, Excel-Spalte; keine Doppelten')]
    public function test_zerlegen_und_uebernehmen(): void
    {
        $eintraege = Adressbuch::zerlegen("Müller, Anna <Anna.Mueller@example.org>; \"Ben Becker\" <ben@example.org>\nc@example.org\nDora Dahl\td@example.org\nben@example.org\nkein Eintrag");
        $this->assertSame([
            ['name' => 'Anna Müller', 'email' => 'anna.mueller@example.org'],
            ['name' => 'Ben Becker', 'email' => 'ben@example.org'],
            ['name' => null, 'email' => 'c@example.org'],
            ['name' => 'Dora Dahl', 'email' => 'd@example.org'],
        ], $eintraege);

        $anna = $this->user('anna@dpsg-trier.de');
        AdressbuchKontakt::query()->create(['email' => 'C@Example.org']);
        $v = AdressbuchVerteiler::query()->create(['name' => 'DAK', 'user_id' => $anna->id]);
        $this->assertSame(['neu' => 3, 'uebernommen' => 4], Adressbuch::uebernehmen($v, $eintraege, $anna));
        $this->assertSame(4, AdressbuchKontakt::query()->count());
        $this->assertSame(['neu' => 0, 'uebernommen' => 0], Adressbuch::uebernehmen($v, $eintraege, $anna));
    }

    #[TestDox('Private Verteiler sieht nur, wer sie angelegt hat (und die Verwaltung); bearbeiten nur Eigene')]
    public function test_sichtbarkeit(): void
    {
        $anna = $this->user('anna@dpsg-trier.de');
        $ben = $this->user('ben@dpsg-trier.de', 'stufenadmin');
        $verwaltung = $this->user('v@dpsg-trier.de', 'verwaltung');
        $gemeinsam = AdressbuchVerteiler::query()->create(['name' => 'Gemeinsam', 'user_id' => $anna->id]);
        $privat = AdressbuchVerteiler::query()->create(['name' => 'Annas privat', 'privat' => true, 'user_id' => $anna->id]);

        $this->actingAs($ben);
        Livewire::test(ListAdressbuchVerteiler::class)->assertCanSeeTableRecords([$gemeinsam])->assertCanNotSeeTableRecords([$privat]);
        $this->assertFalse(Adressbuch::darfBearbeiten($ben, $gemeinsam));
        $this->get('/admin/verteiler/'.$privat->id.'/bearbeiten')->assertNotFound();

        $this->actingAs($verwaltung);
        Livewire::test(ListAdressbuchVerteiler::class)->assertCanSeeTableRecords([$gemeinsam, $privat]);

        $this->actingAs($anna);
        Livewire::test(EditAdressbuchVerteiler::class, ['record' => $privat->getRouteKey()])->assertOk();

        // Verteiler-Adressen nur, wenn sichtbar.
        $privat->kontakte()->attach(AdressbuchKontakt::query()->create(['email' => 'geheim@example.org'])->id);
        $this->assertSame([], Adressbuch::adressen([$privat->id], $ben));
        $this->assertSame(['geheim@example.org'], Adressbuch::adressen([$privat->id], $anna));
    }

    #[TestDox('Verteiler-Aktionen: Adressen einfügen und als Liste kopieren')]
    public function test_aktionen(): void
    {
        $anna = $this->user('anna@dpsg-trier.de');
        $this->actingAs($anna);
        $v = AdressbuchVerteiler::query()->create(['name' => 'Leiterrunde', 'user_id' => $anna->id]);

        Livewire::test(ListAdressbuchVerteiler::class)
            ->callTableAction('einfuegen', $v, ['text' => "Anna Müller <a@example.org>\nb@example.org"])
            ->assertHasNoTableActionErrors();
        $this->assertSame(['a@example.org', 'b@example.org'], $v->fresh()->adressen());

        Livewire::test(ListAdressbuchVerteiler::class)->mountTableAction('kopieren', $v)->assertHasNoTableActionErrors();
        $this->assertSame('Anna Müller <a@example.org>; b@example.org', AdressbuchVerteilerResource::liste($v->fresh()));
    }

    #[TestDox('Videokonferenz: Teilnehmende aus Verteiler, ohne Doppelte, Adressbuch-Vorschläge')]
    public function test_videokonferenz_mit_verteiler(): void
    {
        Mail::fake();
        $anna = $this->user('anna@dpsg-trier.de');
        $this->actingAs($anna);
        $v = AdressbuchVerteiler::query()->create(['name' => 'DAK', 'user_id' => $anna->id]);
        Adressbuch::uebernehmen($v, Adressbuch::zerlegen("a@example.org\nb@example.org"), $anna);

        Livewire::test(CreateVideokonferenz::class)
            ->fillForm([
                'titel' => 'DAK-Treffen',
                'beginn' => now()->addDay()->setTime(19, 0)->format('Y-m-d H:i'),
                'dauer_minuten' => 60,
                'teilnehmende_emails' => ['a@example.org', 'c@example.org'],
                'teilnehmende_verteiler' => [$v->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(['a@example.org', 'c@example.org', 'b@example.org'], Videokonferenz::query()->firstOrFail()->teilnehmende_emails);
        Mail::assertQueued(VideokonferenzMail::class, 3);
        $this->assertContains('a@example.org', Adressbuch::vorschlaege($anna));
    }

    #[TestDox('Datenschutzauskunft findet, exportiert und löscht Kontakte samt Verteiler-Zugehörigkeit')]
    public function test_datenschutz(): void
    {
        $v = AdressbuchVerteiler::query()->create(['name' => 'DAK']);
        $k = AdressbuchKontakt::query()->create(['name' => 'Erika Muster', 'email' => 'erika@example.org']);
        $v->kontakte()->attach($k->id);

        $privacy = app(PrivacyRequests::class);
        $this->assertCount(1, $privacy->find('Erika@Example.org')['adressbuch']);
        $zip = new \ZipArchive;
        $zip->open($privacy->export('erika@example.org'));
        $json = json_decode((string) $zip->getFromName('auskunft.json'), true);
        $this->assertSame(['DAK'], $json['adressbuch'][0]['verteiler']);

        $this->assertSame(1, $privacy->delete('erika@example.org')['adressbuch']);
        $this->assertSame(0, AdressbuchKontakt::query()->count());
        $this->assertSame(0, $v->kontakte()->count());
    }

    #[TestDox('Ohne Funktion oder ohne passende Rolle kein Zugriff')]
    public function test_zugriff(): void
    {
        $this->actingAs($this->user('autor@dpsg-trier.de', 'autor'))->get('/admin/adressbuch')->assertForbidden();
        $anna = $this->user('anna@dpsg-trier.de');
        $this->actingAs($anna);
        Livewire::test(ListAdressbuchKontakte::class)->assertOk();
        Features::setzen(Features::ADRESSBUCH, false);
        $this->get('/admin/adressbuch')->assertForbidden();
        $this->get('/admin/verteiler')->assertForbidden();
    }
}
