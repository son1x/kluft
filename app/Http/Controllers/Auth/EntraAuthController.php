<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\NewPendingUserNotification;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

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

    public function callback(Request $request): RedirectResponse
    {
        /** @var SocialiteUser $entraUser */
        $entraUser = Socialite::driver('microsoft')->user();

        $oid = $entraUser->getId();
        $upn = mb_strtolower((string) ($entraUser->user['userPrincipalName'] ?? $entraUser->getEmail()));
        $email = mb_strtolower((string) ($entraUser->getEmail() ?? $upn));
        $name = $entraUser->getName() ?? $upn;

        $user = User::query()->where('entra_oid', $oid)->first();

        if (! $user) {
            // Vorab von der Verwaltung angelegter Datensatz (per UPN/E-Mail,
            // noch ohne entra_oid) – jetzt bei der ersten Anmeldung verknüpfen.
            $user = User::query()
                ->whereNull('entra_oid')
                ->where(function ($query) use ($upn, $email) {
                    $query->where('upn', $upn)->orWhere('email', $email);
                })
                ->first();
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
}
