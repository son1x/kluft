<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\RedaktionCodeMail;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Einmalcode;
use App\Support\Features;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

/**
 * Anmeldung der Redaktion ohne Microsoft 365 (Funktion „Anmeldung per
 * E-Mail-Code“, Meilenstein Stämme): Adresse eingeben, sechsstelligen
 * Code aus der Mail eintippen. Gilt für namentliche Zugänge und für
 * Rollen-Zugänge über ein Funktionspostfach. Ob es zu einer Adresse einen
 * Zugang gibt, verrät die Seite nicht.
 */
class CodeLoginController extends Controller
{
    public const CODE_MINUTES = 10;

    private const SESSION_KEY = 'code_login.email';

    public function show(Request $request): View
    {
        abort_unless(Features::enabled(Features::CODE_LOGIN), 404);

        if ($request->boolean('neu')) {
            $request->session()->forget(self::SESSION_KEY);
        }

        return view('auth.code-login', [
            'email' => $request->session()->get(self::SESSION_KEY),
            'minutes' => self::CODE_MINUTES,
            'entra' => filled(config('services.microsoft.client_id')),
        ]);
    }

    public function send(Request $request): RedirectResponse
    {
        abort_unless(Features::enabled(Features::CODE_LOGIN), 404);
        $email = Einmalcode::normalize((string) $request->validate(['email' => ['required', 'email', 'max:255']])['email']);

        $user = $this->nutzer($email);
        if ($user) {
            $code = $this->codes()->ausstellen($email);
            if ($code !== null) {
                try {
                    Mail::to($email)->send(new RedaktionCodeMail($code, $user));
                } catch (Throwable $e) {
                    report($e);
                }
                AuditLogger::log('login.code.angefordert', $user, request: $request);
            }
        }

        $request->session()->put(self::SESSION_KEY, $email);

        return redirect()->route('auth.code');
    }

    public function verify(Request $request): RedirectResponse
    {
        abort_unless(Features::enabled(Features::CODE_LOGIN), 404);
        $code = (string) $request->validate(['code' => ['required', 'string', 'max:20']])['code'];
        $email = (string) $request->session()->get(self::SESSION_KEY);

        $user = $email !== '' ? $this->nutzer($email) : null;
        if (! $user || ! $this->codes()->pruefen($email, $code)) {
            AuditLogger::log('login.code.failed', $user, request: $request);

            return back()->withErrors(['code' => 'Der Code stimmt nicht oder ist abgelaufen. Bitte noch einmal versuchen oder einen neuen Code anfordern.']);
        }

        $request->session()->forget(self::SESSION_KEY);
        Auth::login($user, remember: false);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        AuditLogger::log('login.code', $user, request: $request);

        return redirect()->intended('/admin');
    }

    private function nutzer(string $email): ?User
    {
        $user = User::query()
            ->with('roleAssignments')
            ->whereRaw('lower(email) = ?', [$email])
            ->first();

        return $user?->canUseCodeLogin() ? $user : null;
    }

    private function codes(): Einmalcode
    {
        return new Einmalcode('redaktion', self::CODE_MINUTES, 5, 60);
    }
}
