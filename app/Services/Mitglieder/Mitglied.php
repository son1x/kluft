<?php

namespace App\Services\Mitglieder;

use Illuminate\Support\Carbon;

/**
 * Ein Treffer aus der Mitgliederverwaltung – nur die Felder, die der
 * Abgleich braucht. Wird nicht gespeichert.
 */
final readonly class Mitglied
{
    public function __construct(
        public string $nummer,
        public string $vorname,
        public string $nachname,
        public ?Carbon $geburtsdatum,
        public ?string $email,
        public ?string $gruppierung,
        public bool $aktiv,
    ) {}
}
