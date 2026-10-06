<?php

/*
| Abgleich der Veranstaltungs-Teilnehmenden mit der Mitgliederverwaltung
| der DPSG (docs/mitgliederabgleich.md). Der Treiber wechselt mit der
| Umstellung von NaMi 2 auf NaMi 3 (hitobito, geplant Frühjahr 2027) –
| der Rest des CMS bleibt gleich.
|
|   off       – kein Abgleich (Standard)
|   nami      – NaMi 2 (nami.dpsg.de, Login mit Mitgliedsnummer + Passwort)
|   hitobito  – NaMi 3 (JSON:API mit Service-Token)
*/

return [

    'driver' => env('MITGLIEDER_DRIVER', 'off'),

    'nami' => [
        'url' => env('NAMI_URL', 'https://nami.dpsg.de'),
        // Pfad der Service-API; wie in der NaMi-App (digital-scouts/dpsg-nami-app).
        'path' => env('NAMI_PATH', '/ica/rest/api/1/1/service/nami'),
        // Anmeldung: „persoenlich“ – wer im Backend prüft, meldet sich mit dem
        // eigenen NaMi-Konto an (Passwort wird nicht gespeichert, Ergebnis
        // folgt den eigenen NaMi-Rechten); „konto“ – technisches Konto aus
        // NAMI_USER/NAMI_PASSWORD, auch für die automatische Prüfung neuer
        // Anmeldungen. Ohne NAMI_USER ist „persoenlich“ der Standard.
        'auth' => env('NAMI_AUTH') ?: (env('NAMI_USER') ? 'konto' : 'persoenlich'),
        'user' => env('NAMI_USER'),          // Mitgliedsnummer des technischen Kontos
        'password' => env('NAMI_PASSWORD'),
    ],

    'hitobito' => [
        'url' => env('HITOBITO_URL'),        // z. B. https://nami.dpsg.de (NaMi 3)
        'token' => env('HITOBITO_TOKEN'),    // Service-Token der eigenen Ebene
        // Attribut mit der Mitgliedsnummer; leer = hitobito-Personen-ID.
        'member_number_attribute' => env('HITOBITO_MEMBER_NUMBER_ATTRIBUTE'),
    ],

    'timeout' => 20,

    // Höchstens so viele Abfragen je Lauf der Warteschlange (Cron: 50 s).
    'per_run' => 30,

    // Zusatz zum Einwilligungstext bei Veranstaltungen mit Abgleich. Bei
    // inhaltlicher Änderung die Version hochzählen.
    'consent' => [
        'version' => '2026-10-2',
        'text' => 'Für diese Veranstaltung prüfen wir anhand von Name und Geburtsjahr – und, falls du sie angibst, deiner Mitgliedsnummer – in der Mitgliederverwaltung der DPSG (NaMi), ob du Mitglied bist (z. B. für Versicherungsschutz und Teilnahmebeitrag). Gäste aus anderen Diözesanverbänden oder Verbänden werden nicht abgefragt. Gespeichert wird nur das Ergebnis mit Mitgliedsnummer und Stamm.',
    ],

];
