<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\EventRegistration;
use App\Services\EventRegistrations;
use Illuminate\Contracts\View\View;

/**
 * Bestätigung (Double-Opt-In) und Stornierung per Link aus der E-Mail
 * (F-EV-12/13). Der GET-Aufruf ändert nichts – so lösen Linkscanner in
 * Mailprogrammen nichts aus. Beim Bestätigen schickt der Browser das
 * Formular per JavaScript sofort selbst ab (ein Klick in der Mail reicht),
 * beim Stornieren muss man den Button bewusst drücken.
 */
class RegistrationController extends Controller
{
    public function showConfirm(string $token): View
    {
        $registration = EventRegistration::findByConfirmToken($token);

        return view('registrations.action', [
            'action' => 'bestaetigen',
            'registration' => $registration?->status === 'pending' ? $registration : null,
            'token' => $token,
        ]);
    }

    public function confirm(string $token, EventRegistrations $registrations): View
    {
        [$result, $registration] = $registrations->confirm($token);

        return view('registrations.result', ['result' => $result, 'registration' => $registration]);
    }

    public function showCancel(string $token): View
    {
        $registration = EventRegistration::findByCancelToken($token);

        return view('registrations.action', [
            'action' => 'stornieren',
            'registration' => in_array($registration?->status, ['confirmed', 'waitlist'], true) ? $registration : null,
            'token' => $token,
        ]);
    }

    public function cancel(string $token, EventRegistrations $registrations): View
    {
        $registration = EventRegistration::findByCancelToken($token);

        if (! $registration || ! in_array($registration->status, ['confirmed', 'waitlist'], true)) {
            return view('registrations.result', ['result' => 'invalid', 'registration' => null]);
        }

        $registrations->cancel($registration);

        return view('registrations.result', ['result' => 'cancelled', 'registration' => $registration]);
    }
}
