<?php

namespace Tests\Feature\Auslagen;

use App\Filament\Resources\Auslagen\AuslageResource;
use App\Filament\Resources\Auslagen\Pages\CreateAuslage;
use App\Filament\Resources\Auslagen\Pages\ListAuslagen;
use App\Mail\AuslageMail;
use App\Models\Auslage;
use App\Models\User;
use App\Support\Auslagen;
use App\Support\Features;
use App\Support\Zahlungen;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Auslagen und Fahrtkosten erstatten (docs/auslagen.md).
 */
class AuslagenTest extends TestCase
{
    use RefreshDatabase;

    private const IBAN = 'DE89370400440532013000';

    protected function setUp(): void
    {
        parent::setUp();
        Features::setzen(Features::AUSLAGEN, true);
        Filament::setCurrentPanel('admin');
        Mail::fake();
        Storage::fake(Auslage::DISK);
    }

    private function user(string $email, ?string $role = null): User
    {
        $user = User::query()->create(['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'status' => 'active']);
        if ($role !== null) {
            $user->roleAssignments()->create(['role' => $role, 'scope_type' => 'global']);
        } else {
            $user->roleAssignments()->create(['role' => 'autor', 'scope_type' => 'own']);
        }

        return $user->fresh();
    }

    /** Kassenrolle der Instanz: im Stamm „kasse“, sonst die Verwaltung. */
    private function kasse(): User
    {
        return $this->user('kasse@example.org', config('dpsg.site.level') === 'Stamm' ? 'kasse' : 'verwaltung');
    }

    #[TestDox('Einreichen mit Beleg; Bankverbindung verschlüsselt gemerkt; Mail an die Kasse; nur eigene sichtbar')]
    public function test_einreichen(): void
    {
        $kasse = $this->kasse();
        $kim = $this->user('kim@example.org');
        $this->actingAs($kim);

        Livewire::test(CreateAuslage::class)
            ->assertFormSet(['art' => 'betrag', 'konto_inhaber' => 'Kim', 'konto_merken' => true])
            ->fillForm(['anlass' => 'Bastelmaterial', 'datum' => today()->toDateString(), 'betrag' => '12,5',
                'belege' => [UploadedFile::fake()->image('quittung.jpg')], 'konto_iban' => 'DE89 3704 0044 0532 0130 00'])
            ->call('create')->assertHasNoFormErrors();

        $a = Auslage::query()->sole();
        $this->assertSame([1250, 'eingereicht', 'Kim', 'kim@example.org', self::IBAN], [$a->summe_cent, $a->status, $a->name, $a->email, $a->konto['iban']]);
        $this->assertCount(1, $a->belege);
        Storage::disk(Auslage::DISK)->assertExists($a->belege[0]);
        $this->assertStringNotContainsString('DE89', (string) $a->getRawOriginal('konto'));
        $this->assertSame(self::IBAN, $kim->fresh()->auslagen_konto['iban']);
        Mail::assertQueued(AuslageMail::class, fn (AuslageMail $m) => $m->art === 'eingereicht' && $m->hasTo($kasse->email));

        // Beim nächsten Mal vorbelegt; Fahrt ohne Beleg mit Kilometersatz.
        Livewire::test(CreateAuslage::class)
            ->assertFormSet(['konto_iban' => self::IBAN])
            ->fillForm(['art' => 'fahrt', 'anlass' => 'DL-Sitzung', 'datum' => today()->toDateString(), 'km' => '84', 'strecke' => 'Trier – Wittlich'])
            ->call('create')->assertHasNoFormErrors();
        $this->assertSame(2520, Auslage::query()->where('art', 'fahrt')->sole()->summe_cent);

        // Andere sehen sie nicht; die Kasse sieht alle.
        $this->actingAs($this->user('ben@example.org'));
        Livewire::test(ListAuslagen::class)->assertCountTableRecords(0);
        $this->get(AuslageResource::getUrl('view', ['record' => $a]))->assertNotFound();
        $this->actingAs($kasse);
        Livewire::test(ListAuslagen::class)->assertCountTableRecords(2)->set('activeTab', 'erledigt')->assertCountTableRecords(0);
    }

    #[TestDox('Ungültige IBAN und Betrag ohne Beleg werden abgelehnt')]
    public function test_pruefung(): void
    {
        $this->actingAs($this->user('kim@example.org'));
        Livewire::test(CreateAuslage::class)
            ->fillForm(['anlass' => 'X', 'datum' => today()->toDateString(), 'betrag' => '5', 'konto_inhaber' => 'Kim', 'konto_iban' => 'DE00123'])
            ->call('create')->assertHasFormErrors(['konto_iban', 'belege' => 'required']);
    }

    #[TestDox('Kasse: freigeben, ablehnen mit Grund, ausgezahlt; Mail an die Person; Bankverbindung danach weg; bearbeiten nur solange eingereicht')]
    public function test_kasse(): void
    {
        $kasse = $this->kasse();
        $kim = $this->user('kim@example.org');
        $konto = ['inhaber' => 'Kim', 'iban' => self::IBAN];
        $eins = Auslage::query()->create(['user_id' => $kim->id, 'name' => 'Kim', 'email' => 'kim@example.org', 'anlass' => 'Zelt', 'datum' => today(), 'art' => 'betrag', 'betrag_cent' => 4000, 'konto' => $konto]);
        $zwei = Auslage::query()->create(['user_id' => $kim->id, 'name' => 'Kim', 'email' => 'kim@example.org', 'anlass' => 'Pizza', 'datum' => today(), 'art' => 'betrag', 'betrag_cent' => 9000, 'konto' => $konto]);

        $this->actingAs($kasse);
        Livewire::test(ListAuslagen::class)
            ->callTableAction('freigeben', $eins)
            ->callTableAction('ablehnen', $zwei, ['grund' => 'Nicht abgesprochen']);
        $this->assertSame('freigegeben', $eins->fresh()->status);
        $this->assertSame(['abgelehnt', 'Nicht abgesprochen', null], [$zwei->fresh()->status, $zwei->fresh()->grund, $zwei->fresh()->konto]);
        Mail::assertQueued(AuslageMail::class, fn (AuslageMail $m) => $m->art === 'abgelehnt' && $m->hasTo('kim@example.org'));

        $this->actingAs($kim);
        $this->get(AuslageResource::getUrl('edit', ['record' => $eins]))->assertForbidden();
        $this->get(AuslageResource::getUrl('view', ['record' => $eins]))->assertOk()->assertSee('Freigegeben');

        $this->actingAs($kasse);
        Livewire::test(ListAuslagen::class)->set('activeTab', 'auszahlen')->callTableAction('ausgezahlt', $eins, ['am' => '2026-11-12']);
        $eins->refresh();
        $this->assertSame(['ausgezahlt', '2026-11-12', null], [$eins->status, $eins->ausgezahlt_am->toDateString(), $eins->konto]);
        Mail::assertQueued(AuslageMail::class, fn (AuslageMail $m) => $m->art === 'ausgezahlt');
        $this->assertStringContainsString('überwiesen', (new AuslageMail($eins, 'ausgezahlt'))->render());
    }

    #[TestDox('SEPA-Sammelüberweisung (pain.001.001.03) für freigegebene Auslagen; Kilometersatz einstellbar')]
    public function test_sepa(): void
    {
        Zahlungen::kontoSetzen('DPSG Förderverein', 'DE02120300000000202051', 'BYLADEM1001', null);
        $kim = $this->user('kim@example.org');
        $a = Auslage::query()->create(['user_id' => $kim->id, 'name' => 'Kim', 'email' => 'kim@example.org', 'anlass' => 'Grillkohle für´s Lager', 'datum' => '2026-11-01', 'art' => 'betrag', 'betrag_cent' => 1999, 'status' => 'freigegeben', 'konto' => ['inhaber' => 'Kim Müller', 'iban' => self::IBAN]]);
        Auslage::query()->create(['user_id' => $kim->id, 'name' => 'Kim', 'email' => 'kim@example.org', 'anlass' => 'Offen', 'datum' => '2026-11-01', 'art' => 'betrag', 'betrag_cent' => 500, 'konto' => ['inhaber' => 'Kim', 'iban' => self::IBAN]]);

        $xml = Auslagen::sepaXml(Auslage::query()->get());
        $doc = simplexml_load_string($xml);
        $this->assertNotFalse($doc);
        $this->assertSame('1', (string) $doc->CstmrCdtTrfInitn->GrpHdr->NbOfTxs);
        $this->assertSame('19.99', (string) $doc->CstmrCdtTrfInitn->GrpHdr->CtrlSum);
        $tx = $doc->CstmrCdtTrfInitn->PmtInf->CdtTrfTxInf;
        $this->assertSame('Kim Mueller', (string) $tx->Cdtr->Nm);
        $this->assertSame(self::IBAN, (string) $tx->CdtrAcct->Id->IBAN);
        $this->assertSame('Erstattung Grillkohle fuers Lager 01.11.2026 Nr '.$a->id, (string) $tx->RmtInf->Ustrd);
        $this->assertSame('DPSG Foerderverein', (string) $doc->CstmrCdtTrfInitn->PmtInf->Dbtr->Nm);

        $this->actingAs($this->kasse());
        Livewire::test(ListAuslagen::class)->callAction('kmSatz', ['euro' => '0.25']);
        $this->assertSame(25, Auslagen::kmSatzCent());
    }

    #[TestDox('Ausgezahlte nach 10 Jahren samt Belegen gelöscht, abgelehnte nach einem Jahr')]
    public function test_aufraeumen(): void
    {
        Storage::disk(Auslage::DISK)->put('auslagen/alt.jpg', 'x');
        $basis = ['name' => 'Kim', 'email' => 'kim@example.org', 'anlass' => 'X', 'datum' => '2015-01-01', 'art' => 'betrag', 'betrag_cent' => 100];
        Auslage::query()->create([...$basis, 'status' => 'ausgezahlt', 'ausgezahlt_am' => '2015-06-01', 'belege' => ['auslagen/alt.jpg']]);
        $jung = Auslage::query()->create([...$basis, 'status' => 'ausgezahlt', 'ausgezahlt_am' => today()->subYears(9)->toDateString()]);
        $abgelehnt = Auslage::query()->create([...$basis, 'status' => 'abgelehnt']);
        $abgelehnt->forceFill(['updated_at' => now()->subMonths(13)])->saveQuietly();

        $this->artisan('auslagen:aufraeumen')->expectsOutput('2 Auslagen gelöscht.');
        $this->assertSame([$jung->id], Auslage::query()->pluck('id')->all());
        Storage::disk(Auslage::DISK)->assertMissing('auslagen/alt.jpg');
    }

    #[TestDox('Ohne Funktion kein Zugriff')]
    public function test_aus(): void
    {
        Features::setzen(Features::AUSLAGEN, false);
        $this->actingAs($this->user('kim@example.org'))->get(AuslageResource::getUrl())->assertForbidden();
    }
}
