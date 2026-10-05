<?php

namespace App\Services\Mitglieder;

/**
 * Zugang zur Mitgliederverwaltung. Heute NaMi 2, ab 2027 NaMi 3 auf
 * hitobito-Basis; der Treiber kommt aus config mitglieder.driver.
 */
interface Mitgliederverzeichnis
{
    /** Anzeigename, z. B. „NaMi 2“. */
    public function name(): string;

    /**
     * Personen mit diesem Nachnamen, deren Vorname so beginnt.
     *
     * @return list<Mitglied>
     *
     * @throws MitgliederverzeichnisException
     */
    public function suche(string $vorname, string $nachname): array;

    /**
     * Verbindung prüfen (Anmeldung und eine leere Suche), ohne Daten auszugeben.
     *
     * @throws MitgliederverzeichnisException
     */
    public function pruefe(): void;
}
