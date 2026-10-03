<?php

namespace App\Http\Controllers\Auth;

use App\Filament\Resources\Users\UserResource;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\NewPendingUserNotification;
use App\Services\EntraDirectory;
use App\Support\AuditLogger;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Throwable;

/**
 * Entra-ID-/M365-Login der Redaktion, s. Pflichtenheft 5.1 und 11.2.
 *
 * Entra übernimmt NUR die Authentifizierung. Rollen/Scopes kommen aus
 * role_assignments (s. App\Models\RoleAssignment) und werden von der
 * Verwaltung im Backend vergeben – auch VOR der ersten Anmeldung der
 * jeweiligen Person (s. 5.6, F-USR-02/03).
 */
class EntraAuthController extends Controller
{
    public function redirectToEntra(): RedirectResponse
    {
        return Socialite::driver('microsoft')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    /**
     * Verzeichnissuche verbinden (F-USR-02): eigener, zweiter Schritt mit
     * User.ReadBasic.All, damit der normale Login nie von dieser
     * Berechtigung abhängt. Mit erteilter Administratorzustimmung kehrt
     * Microsoft ohne Rückfrage sofort zurück.
     */
    public function connectDirectory(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('create', User::class), 403);

        if (blank($user->entra_oid)) {
            FilamentNotification::make()->warning()
                ->title('Verzeichnissuche nur mit M365-Anmeldung')
                ->body('Mit dem Notfall-Konto ist keine Suche im Verzeichnis möglich. Bitte die E-Mail-Adresse von Hand eintragen.')
                ->send();

            return redirect(UserResource::getUrl('create'));
        }

        $request->session()->put('entra.flow', 'verzeichnis');

        /** @var AbstractProvider $driver */
        $driver = Socialite::driver('microsoft');

        return $driver
            ->setScopes(['User.Read', 'User.ReadBasic.All'])
            ->with(['login_hint' => $user->upn ?? $user->email])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse|Response
    {
        if ($request->session()->pull('entra.flow') === 'verzeichnis' && $request->user() instanceof User) {
            return $this->directoryCallback($request, $request->user());
        }

        /** @var SocialiteUser $entraUser */
        $entraUser = Socialite::driver('microsoft')->user();

        $oid = $entraUser->getId();
        $upn = mb_strtolower((string) ($entraUser->user['userPrincipalName'] ?? $entraUser->getEmail()));
        $email = mb_strtolower((string) ($entraUser->getEmail() ?? $upn));
        $name = $entraUser->getName() ?? $upn;

        // Der Notfall-Account (is_local_admin) wird nie mit einem
        // Microsoft-Konto verknüpft – sonst trüge eine normale M365-Anmeldung
        // stillschweigend Super-Admin-Rechte (Pflichtenheft 5.2: getrennter Zugang).
        $user = User::query()->where('entra_oid', $oid)->where('is_local_admin', false)->first();

        if (! $user) {
            // Vorab von der Verwaltung angelegter Datensatz (per UPN/E-Mail,
            // noch ohne entra_oid) – jetzt bei der ersten Anmeldung verknüpfen.
            $user = User::query()
                ->whereNull('entra_oid')
                ->where('is_local_admin', false)
                ->where(function ($query) use ($upn, $email) {
                    $query->whereRaw('lower(upn) = ?', [$upn])->orWhereRaw('lower(email) = ?', [$email]);
                })
                ->first();
        }

        if (! $user && User::query()->where('is_local_admin', true)->whereRaw('lower(email) = ?', [$email])->exists()) {
            AuditLogger::log('login.entra.blocked_breakglass_email', request: $request);

            return response()->view('auth.entra-conflict', ['email' => $email], 409);
        }

        if ($user) {
            $user->forceFill([
                'entra_oid' => $oid,
                'upn' => $upn,
                'name' => $name,
                'email' => $email,
                'last_login_at' => now(),
            ])->save();
        } else {
            // Konto ohne vorherige Freischaltung: anlegen, aber „wartet auf
            // Freischaltung“ – kein Zugriff, bis die Verwaltung eine Rolle
            // zuweist (s. 5.1). Kein Passwort/TOTP, da ausschließlich Entra.
            $user = User::query()->create([
                'entra_oid' => $oid,
                'upn' => $upn,
                'name' => $name,
                'email' => $email,
                'status' => 'pending',
                'is_local_admin' => false,
                'last_login_at' => now(),
            ]);

            Notification::send(
                User::query()->whereHas(
                    'roleAssignments',
                    fn ($query) => $query->whereIn('role', ['verwaltung', 'admin'])
                )->get(),
                new NewPendingUserNotification($user)
            );
        }

        Auth::login($user, remember: false);
        $request->session()->regenerate();

        AuditLogger::log('login.entra', $user, request: $request);

        return redirect()->intended('/admin');
    }

    private function directoryCallback(Request $request, User $user): RedirectResponse
    {
        $back = redirect(UserResource::getUrl('create'));

        try {
            if ($request->filled('error')) {
                throw new \RuntimeException((string) $request->query('error'));
            }
            /** @var SocialiteUser $entraUser */
            $entraUser = Socialite::driver('microsoft')->user();
        } catch (Throwable $e) {
            AuditLogger::log('entra.verzeichnis.fehlgeschlagen', $user, request: $request);
            FilamentNotification::make()->danger()
                ->title('Verzeichnissuche nicht verbunden')
                ->body('Microsoft hat die Verbindung abgelehnt ('.mb_substr($e->getMessage(), 0, 80).'). Ist die Berechtigung User.ReadBasic.All mit Administratorzustimmung erteilt?')
                ->persistent()
                ->send();

            return $back;
        }

        // Nur das eigene Konto: wer sich bei Microsoft als jemand anderes
        // meldet, bekommt keinen Verzeichniszugriff in dieser Sitzung.
        if ($entraUser->getId() !== $user->entra_oid) {
            AuditLogger::log('entra.verzeichnis.anderes_konto', $user, request: $request);
            FilamentNotification::make()->danger()
                ->title('Anderes Microsoft-Konto')
                ->body('Bitte mit dem Konto verbinden, mit dem du hier angemeldet bist.')
                ->send();

            return $back;
        }

        app(EntraDirectory::class)->remember((string) $entraUser->token, (int) ($entraUser->expiresIn ?: 3600));
        AuditLogger::log('entra.verzeichnis.verbunden', $user, request: $request);

        return $back;
    }
}
