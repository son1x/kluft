<?php

namespace Tests\Feature\Auth;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Mail\RedaktionCodeMail;
use App\Models\AuditLog;
use App\Models\FeatureSwitch;
use App\Models\User;
use App\Notifications\RolesGrantedNotification;
use App\Services\Mitglieder\Mitglied;
use App\Services\Mitglieder\Mitgliederverzeichnis;
use App\Services\Mitglieder\MitgliederverzeichnisException;
use App\Services\Mitglieder\MitgliedPruefung;
use App\Support\Features;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Eigene Benutzerverwaltung ohne Microsoft 365 (Meilenstein Stämme):
 * Anmeldung mit Einmalcode, Zugänge für Personen oder Rollen
 * (Funktionspostfach), NaMi-Prüfung auf Wunsch.
 */
class CodeLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    private function codeLogin(bool $an): void
    {
        FeatureSwitch::query()->updateOrCreate(['key' => Features::CODE_LOGIN], ['enabled' => $an]);
        Cache::forget('features.schalter');
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function zugang(string $email, array $attrs = [], bool $mitRolle = true): User
    {
        $user = User::query()->create(['name' => 'Jana Beispiel', 'email' => $email, 'status' => 'active', 'login' => 'email', ...$attrs]);
        if ($mitRolle) {
            $user->roleAssignments()->create(['role' => 'autor', 'scope_type' => 'own', 'scope_key' => null]);
        }

        return $user->fresh();
    }

    private function codeAnfordern(string $email): ?string
    {
        $code = null;
        Mail::fake();
        $this->post(route('auth.code.send'), ['email' => $email])->assertRedirect(route('auth.code'));
        Mail::assertSent(RedaktionCodeMail::class, function (RedaktionCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return $code;
    }

    #[TestDox('Funktion aus: keine Code-Anmeldung, /admin/login geht zu Microsoft 365')]
    public function test_funktion_aus(): void
    {
        $this->codeLogin(false);

        $this->get('/redaktion/anmelden')->assertNotFound();
        $this->get('/admin/login')->assertRedirect(route('auth.entra.redirect'));
    }

    #[TestDox('Funktion an: /admin/login führt zur Code-Anmeldung; Code anfordern, eingeben, im Backend')]
    public function test_anmelden_mit_code(): void
    {
        $this->codeLogin(true);
        $user = $this->zugang('jana@example.org');

        $this->get('/admin/login')->assertRedirect(route('auth.code'));
        $this->get(route('auth.code'))->assertOk()->assertSee('Code schicken');

        $code = $this->codeAnfordern('  Jana@Example.org ');
        $this->assertNotNull($code);
        $this->get(route('auth.code'))->assertSee('jana@example.org')->assertSee('Code aus der E-Mail');

        $this->post(route('auth.code.verify'), ['code' => $code])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertTrue(AuditLog::query()->where('action', 'login.code')->where('user_id', $user->id)->exists());
        $this->get('/admin')->assertOk();
    }

    #[TestDox('Kein Code für Microsoft-365-Konten, Wartende, Konten ohne Rolle, den Notfallzugang und Unbekannte – die Antwort ist immer gleich')]
    public function test_kein_code_fuer_andere(): void
    {
        $this->codeLogin(true);
        $this->zugang('m365@example.org', ['login' => 'entra']);
        $this->zugang('wartet@example.org', ['status' => 'pending']);
        $this->zugang('ohne@example.org', [], mitRolle: false);
        $this->zugang('notfall@example.org', ['is_local_admin' => true]);

        Mail::fake();
        foreach (['m365@example.org', 'wartet@example.org', 'ohne@example.org', 'notfall@example.org', 'unbekannt@example.org'] as $email) {
            $this->post(route('auth.code.send'), ['email' => $email])->assertRedirect(route('auth.code'));
            $this->get(route('auth.code'))->assertSee('Wenn es für <strong>'.$email.'</strong> einen Zugang gibt', false);
        }
        Mail::assertNothingSent();

        $this->post(route('auth.code.verify'), ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    #[TestDox('Falscher Code: Fehler; nach fünf Fehlversuchen hilft auch der richtige nicht mehr')]
    public function test_versuche_begrenzt(): void
    {
        $this->codeLogin(true);
        $this->zugang('jana@example.org');
        $code = $this->codeAnfordern('jana@example.org');
        $falsch = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('auth.code.verify'), ['code' => $falsch])->assertSessionHasErrors('code');
        }
        $this->post(route('auth.code.verify'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertTrue(AuditLog::query()->where('action', 'login.code.failed')->exists());
    }

    #[TestDox('Gesperrtes Konto kommt auch mit gültigem Code nicht hinein')]
    public function test_gesperrt_nach_anforderung(): void
    {
        $this->codeLogin(true);
        $user = $this->zugang('jana@example.org');
        $code = $this->codeAnfordern('jana@example.org');
        $user->update(['status' => 'disabled']);

        $this->post(route('auth.code.verify'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    #[TestDox('Rollen-Zugang: Code an das Funktionspostfach, Mail nennt die Rolle, Anmeldung klappt')]
    public function test_rollen_zugang(): void
    {
        $this->codeLogin(true);
        $rolle = $this->zugang('vorstand@example.org', ['name' => 'Stammesvorstand', 'kind' => 'rolle']);

        $code = null;
        Mail::fake();
        $this->post(route('auth.code.send'), ['email' => 'vorstand@example.org']);
        Mail::assertSent(RedaktionCodeMail::class, function (RedaktionCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return $mail->hasTo('vorstand@example.org') && str_contains($mail->render(), 'Zugang „Stammesvorstand“');
        });

        $this->post(route('auth.code.verify'), ['code' => $code])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($rolle);
    }

    #[TestDox('Benutzerverwaltung: Rollen-Zugang anlegen; Willkommensmail erklärt die Anmeldung per Code')]
    public function test_rollen_zugang_anlegen(): void
    {
        $this->codeLogin(true);
        Notification::fake();
        $admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.org', 'status' => 'active']);
        $admin->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global', 'scope_key' => null]);
        $this->actingAs($admin->fresh());

        $undo = Repeater::fake();
        Livewire::test(CreateUser::class)
            ->assertSee('Art des Zugangs')
            ->fillForm(['kind' => 'rolle', 'name' => 'Wölflingsleitung', 'login' => 'email', 'email' => 'Woelflinge@Example.org', 'status' => 'active', 'roleAssignments' => [['assignment' => 'stufenadmin|stage|woelflinge']]])
            ->call('create')
            ->assertHasNoFormErrors();
        $undo();

        $neu = User::query()->where('email', 'woelflinge@example.org')->firstOrFail();
        $this->assertSame('rolle', $neu->kind);
        $this->assertSame('email', $neu->login);
        $this->assertNull($neu->member_number);
        Notification::assertSentTo($neu, RolesGrantedNotification::class, function (RolesGrantedNotification $n) use ($neu) {
            $text = implode("\n", $n->toMail($neu)->outroLines);

            return str_contains($text, 'bekommst einen Code per E-Mail') && str_contains($text, 'Zugang „Wölflingsleitung“ gehört zu diesem Postfach');
        });
    }

    #[TestDox('Funktion aus: Benutzerformular wie bisher nur mit Microsoft 365')]
    public function test_formular_ohne_funktion(): void
    {
        $this->codeLogin(false);
        $admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.org', 'status' => 'active']);
        $admin->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global', 'scope_key' => null]);
        $this->actingAs($admin->fresh());

        Livewire::test(CreateUser::class)->assertSee('M365-Konto')->assertDontSee('Art des Zugangs');
    }

    #[TestDox('NaMi-Prüfung: eindeutiger Treffer, Nummer muss passen, mehrere Treffer, nicht gefunden')]
    public function test_mitglied_pruefung(): void
    {
        $verzeichnis = new class implements Mitgliederverzeichnis
        {
            public function name(): string
            {
                return 'NaMi 2';
            }

            public function suche(string $vorname, string $nachname): array
            {
                $m = fn (string $nr, bool $aktiv) => new Mitglied($nr, $vorname, $nachname, null, null, 'Stamm Don Bosco 210208', $aktiv);

                return match ($nachname) {
                    'Beispiel' => [$m('123456', true)],
                    'Doppelt' => [$m('111', true), $m('222', false)],
                    default => [],
                };
            }

            public function pruefe(): void {}
        };
        $pruefung = new MitgliedPruefung($verzeichnis);
        $pruefer = new User;

        $ok = $pruefung->pruefe($pruefer, 'Jana Beispiel', null);
        $this->assertTrue($ok['aktiv']);
        $this->assertSame('123456', $ok['nummer']);
        $this->assertSame('Aktives Mitglied, Stamm Don Bosco 210208 (Nr. 123456)', $ok['text']);

        $this->assertFalse($pruefung->pruefe($pruefer, 'Jana Beispiel', '999')['aktiv']);
        $this->assertStringContainsString('Mehrere Treffer', $pruefung->pruefe($pruefer, 'Jana Doppelt', null)['text']);
        $inaktiv = $pruefung->pruefe($pruefer, 'Jana Doppelt', '222');
        $this->assertFalse($inaktiv['aktiv']);
        $this->assertStringStartsWith('Kein aktives Mitglied', $inaktiv['text']);
        $this->assertStringStartsWith('Nicht in NaMi 2 gefunden', $pruefung->pruefe($pruefer, 'Max Unbekannt', null)['text']);

        $this->expectException(MitgliederverzeichnisException::class);
        $pruefung->pruefe($pruefer, 'Einwort', null);
    }
}
