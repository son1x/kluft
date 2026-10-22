<?php

namespace App\Services\Zahlungen;

use App\Models\Zahlung;

/**
 * Austauschbarer Zahlungsweg (Überweisung, später z. B. Stripe Checkout).
 */
interface Zahlungsweg
{
    /** Schlüssel wie in Zahlung::WEGE, z. B. „ueberweisung“. */
    public function schluessel(): string;

    /** Was die zahlende Person braucht: Kontodaten, QR-Code oder Bezahl-Link. */
    public function anfordern(Zahlung $zahlung): Zahlungsaufforderung;
}
