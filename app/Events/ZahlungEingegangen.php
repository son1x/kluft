<?php

namespace App\Events;

use App\Models\Zahlung;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Eine Zahlung ist vollständig bezahlt. Der Auslöser (z. B. eine Bestellung)
 * hört darauf und setzt seinen eigenen Stand – der Baustein kennt ihn nicht.
 */
class ZahlungEingegangen
{
    use Dispatchable;

    public function __construct(public Zahlung $zahlung) {}
}
