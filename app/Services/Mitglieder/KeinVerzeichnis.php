<?php

namespace App\Services\Mitglieder;

/** Treiber „off“: kein Abgleich eingerichtet. */
class KeinVerzeichnis implements Mitgliederverzeichnis
{
    public function name(): string
    {
        return 'keine Mitgliederverwaltung';
    }

    public function suche(string $vorname, string $nachname): array
    {
        throw new MitgliederverzeichnisException('Der Mitgliederabgleich ist nicht eingerichtet (MITGLIEDER_DRIVER).');
    }

    public function pruefe(): void
    {
        $this->suche('', '');
    }
}
