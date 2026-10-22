<?php

namespace Tests\Feature\Zahlungen;

use App\Events\ZahlungEingegangen;
use App\Filament\Resources\Zahlungen\Pages\ListZahlungen;
use App\Filament\Resources\Zahlungen\ZahlungResource;
use App\Mail\ZahlungsaufforderungMail;
use App\Models\User;
use App\Services\Zahlungen\GiroCode;
use App\Support\Features;
use App\Support\Zahlungen;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Baustein „Zahlungen“ (docs/zahlungen.md).
 */
class ZahlungenTest extends TestCase
{
    use RefreshDatabase;

    private const IBAN = 'DE89 3704 0044 0532 0130 00';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Filament::setCurrentPanel('admin');
    }

    private function user(string $rolle): User
    {
        $user = User::query()->create(['name' => $rolle, 'email' => $rolle.'@example.org', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => $rolle, 'scope_type' => 'global', 'scope_key' => null]);

        return $user;
    }

    #[TestDox('IBAN-Prüfsumme und GiroCode nach EPC069-12')]
    public function test_iban_und_girocode(): void
    {
        $this->assertTrue(Zahlungen::ibanGueltig(self::IBAN));
        $this->assertFalse(Zahlungen::ibanGueltig('DE89 3704 0044 0532 0130 01'));
        $this->assertFalse(Zahlungen::ibanGueltig('kein Konto'));

        $daten = GiroCode::nutzdaten("DPSG Stamm\nBeispiel", self::IBAN, null, 1250, 'RH-2026-0007');
        $this->assertSame(['BCD', '002', '1', 'SCT', '', 'DPSG Stamm Beispiel', 'DE89370400440532013000', 'EUR12.50', '', '', 'RH-2026-0007'], explode("\n", $daten));
        $this->assertStringStartsWith("\x89PNG", base64_decode(GiroCode::png($daten)));
    }

    #[TestDox('Anlegen mit Referenz, Teilzahlung, Eingang meldet ZahlungEingegangen, Erstattung')]
    public function test_ablauf(): void
    {
        Event::fake([ZahlungEingegangen::class]);
        $zahlung = Zahlungen::anlegen(null, 2000, 'RH', 'Halstuch und Aufnäher');

        $this->assertMatchesRegularExpression('/^RH-\d{4}-\d{4}$/', $zahlung->referenz);
        $this->assertSame('offen', $zahlung->status);

        $zahlung->eingang(500, now());
        $this->assertSame('teilweise', $zahlung->status);
        $this->assertSame(1500, $zahlung->offenCent());
        Event::assertNotDispatched(ZahlungEingegangen::class);

        $zahlung->eingang(1500, now());
        $this->assertSame('bezahlt', $zahlung->status);
        Event::assertDispatched(ZahlungEingegangen::class, fn ($e) => $e->zahlung->is($zahlung));

        $zahlung->erstattung(2000, now());
        $this->assertSame('erstattet', $zahlung->fresh()->status);
    }

    #[TestDox('Zahlungsaufforderung per Mail mit Kontodaten und GiroCode; ohne Bankverbindung keine')]
    public function test_aufforderung(): void
    {
        $zahlung = Zahlungen::anlegen(null, 1250, 'VA', 'Sommerlager');
        $this->assertNull(Zahlungen::konto());

        try {
            Zahlungen::auffordern($zahlung, 'erika@example.org', 'Erika');
            $this->fail('Ohne Bankverbindung darf keine Aufforderung rausgehen.');
        } catch (\RuntimeException) {
            Mail::assertNothingQueued();
        }

        Zahlungen::kontoSetzen('DPSG Stamm Beispiel', self::IBAN, null, null);
        Zahlungen::auffordern($zahlung, 'erika@example.org', 'Erika');

        Mail::assertQueued(ZahlungsaufforderungMail::class, function (ZahlungsaufforderungMail $mail) use ($zahlung) {
            return $mail->hasTo('erika@example.org')
                && $mail->aufforderung->verwendungszweck === $zahlung->referenz
                && $mail->aufforderung->iban === 'DE89370400440532013000'
                && $mail->aufforderung->qrPngBase64 !== null;
        });
        $this->assertNotNull($zahlung->fresh()->aufgefordert_am);

        $html = (new ZahlungsaufforderungMail('Erika', 'Sommerlager', Zahlungen::weg($zahlung)->anfordern($zahlung), '31.10.2026'))->render();
        $this->assertStringContainsString('DE89 3704 0044 0532 0130 00', $html);
        $this->assertStringContainsString($zahlung->referenz, $html);
        $this->assertStringContainsString('GiroCode', $html);
    }

    #[TestDox('Backend: nur mit Funktion und für Kasse/Verwaltung; Eingang und Bankverbindung')]
    public function test_backend(): void
    {
        Features::setzen(Features::ZAHLUNGEN, false);
        $admin = $this->user('admin');
        $this->actingAs($admin)->get(ZahlungResource::getUrl('index'))->assertForbidden();

        Features::setzen(Features::ZAHLUNGEN, true);
        $this->actingAs($this->user('autor'))->get(ZahlungResource::getUrl('index'))->assertForbidden();

        $zahlung = Zahlungen::anlegen(null, 1000, 'ZA', 'Kostenbeitrag');
        $this->actingAs($admin);
        Livewire::test(ListZahlungen::class)
            ->assertCanSeeTableRecords([$zahlung])
            ->callAction('konto', ['inhaber' => 'DPSG Stamm Beispiel', 'iban' => self::IBAN, 'bic' => ''])
            ->assertHasNoActionErrors();
        $this->assertSame('DE89370400440532013000', Zahlungen::konto()['iban'] ?? null);

        Livewire::test(ListZahlungen::class)
            ->callTableAction('eingang', $zahlung, ['betrag' => '10,00', 'am' => now()->toDateString()])
            ->assertHasNoTableActionErrors();
        $this->assertSame('bezahlt', $zahlung->fresh()->status);

        Livewire::test(ListZahlungen::class)
            ->callAction('konto', ['inhaber' => 'X', 'iban' => 'DE00 1234', 'bic' => ''])
            ->assertHasActionErrors(['iban']);
    }
}
