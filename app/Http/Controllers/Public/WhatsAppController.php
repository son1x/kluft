<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\WhatsApp\WhatsAppSettings;
use App\Services\WhatsApp\WhatsAppSubscriptions;
use App\Support\PhoneNumber;
use App\Support\SpamGuard;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Anmeldeseite des WhatsApp-Newsletters (docs/whatsapp_newsletter.md, 4):
 * Standard ist QR-Code/Link mit START, optional das Formular (Weg B).
 * Solange WhatsApp nicht mit Meta verbunden ist, gibt es die Seite nicht.
 */
class WhatsAppController extends Controller
{
    public function show(WhatsAppSettings $settings): View
    {
        abort_unless($settings->configured(), 404);

        $number = (string) config('whatsapp.public_number');

        return view('whatsapp.index', [
            'link' => PhoneNumber::waMe($number, 'START'),
            'display' => (string) config('whatsapp.public_number_display'),
            'formEnabled' => (bool) config('whatsapp.form_doi'),
        ]);
    }

    public function subscribe(Request $request, WhatsAppSettings $settings, WhatsAppSubscriptions $subscriptions): RedirectResponse
    {
        abort_unless($settings->configured() && config('whatsapp.form_doi'), 404);
        $back = fn () => redirect()->route('whatsapp.index')->with('whatsapp_status', 'angefragt')->withFragment('formular');

        if (($spam = SpamGuard::check($request)) !== null) {
            return $spam === 'bot' ? $back() : back()->with('whatsapp_status', 'abgelaufen');
        }

        $data = $request->validate([
            'nummer' => ['required', 'string', 'max:30'],
            'einwilligung' => ['accepted'],
            'alter' => ['accepted'],
        ], [
            'einwilligung.accepted' => 'Bitte bestätige die Einwilligung.',
            'alter.accepted' => 'Der WhatsApp-Newsletter ist erst ab 16 Jahren möglich.',
        ]);

        $phone = PhoneNumber::normalize($data['nummer']);
        if ($phone === null) {
            return back()->withInput()->withErrors(['nummer' => 'Bitte eine gültige Mobilnummer eingeben, z. B. 0151 23456789.']);
        }

        // Antwort immer gleich – verrät nicht, ob die Nummer schon bekannt ist.
        $subscriptions->requestByForm($phone);

        return $back();
    }
}
