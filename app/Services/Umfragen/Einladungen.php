<?php

namespace App\Services\Umfragen;

use App\Mail\UmfrageEinladungMail;
use App\Models\EventRegistration;
use App\Models\Umfrage;
use App\Models\UmfrageEinladung;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Nachfrage an Teilnehmende (docs/umfragen.md): persönliche Links an alle
 * bestätigten Anmeldungen der Veranstaltung, je Person höchstens einmal.
 */
class Einladungen
{
    public function einladen(Umfrage $umfrage): int
    {
        if (! $umfrage->event_id || $umfrage->ist_vorlage) {
            return 0;
        }
        $schon = $umfrage->einladungen()->pluck('event_registration_id')->all();
        $n = 0;
        $registrations = EventRegistration::query()->with('event')->where('event_id', $umfrage->event_id)->where('status', 'confirmed')
            ->where('email', '!=', '')->whereNotIn('id', $schon ?: [0])->get();
        foreach ($registrations as $registration) {
            $token = Str::random(40);
            UmfrageEinladung::query()->create([
                'umfrage_id' => $umfrage->id, 'event_registration_id' => $registration->id,
                'token_hash' => UmfrageEinladung::hash($token), 'eingeladen_am' => now(),
            ]);
            try {
                Mail::to($registration->email)->send(new UmfrageEinladungMail($umfrage, $registration, $token));
                $n++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $n;
    }

    /** Einladung zu einem Token (nur für diese Umfrage). */
    public function finden(Umfrage $umfrage, ?string $token): ?UmfrageEinladung
    {
        if (blank($token)) {
            return null;
        }

        return $umfrage->einladungen()->with('registration')->where('token_hash', UmfrageEinladung::hash((string) $token))->first();
    }
}
