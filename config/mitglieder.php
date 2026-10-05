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
        'version' => '2026-10',
        'text' => 'Für diese Veranstaltung prüfen wir anhand von Name und Geburtsjahr in der Mitgliederverwaltung der DPSG (NaMi), ob du Mitglied bist (z. B. für Versicherungsschutz und Teilnahmebeitrag). Gespeichert wird nur das Ergebnis mit Mitgliedsnummer und Stamm.',
    ],

];
