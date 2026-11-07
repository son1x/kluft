<?php

/*
| Updates für eigenständige Kluft-Installationen (z. B. je Stamm), s.
| docs/updates.md. DV und Bezirke, die per GitHub deployt werden, lassen
| das aus („off“) – dort bringt jeder Push den neuen Stand.
*/

return [

    // Testsystem (docs/livegang.md): leer = aus, „offen“ = Band und noindex,
    // „geschlossen“ = zusätzlich nur nach Anmeldung. live_url = Ziel des Hinweises.
    'testsystem' => env('KLUFT_TESTSYSTEM', ''),
    'live_url' => env('KLUFT_LIVE_URL'),

    // Arbeitsnachweis in den Nachrichtenformularen (App\Support\Arbeitsnachweis,
    // docs/nachrichten.md): PBKDF2-Durchläufe je Versuch und Anfang des
    // Schlüssels (Hex). Im Mittel 16^Länge Versuche; „00“ mit 5000 dauert im
    // Browser etwa eine Sekunde.
    'arbeitsnachweis' => [
        'kosten' => (int) env('KLUFT_ARBEITSNACHWEIS_KOSTEN', 5000),
        'praefix' => env('KLUFT_ARBEITSNACHWEIS_PRAEFIX', '00'),
    ],

    'updates' => [
        // off = keine Updates über Kluft selbst (Deploy per GitHub)
        // manual = verfügbare Updates anzeigen, Installation per Knopf
        // auto = zusätzlich nachts automatisch installieren
        'mode' => env('KLUFT_UPDATES', 'off'),

        // Öffentliche Adresse der signierten Release-Beschreibung (manifest.json);
        // die Signatur liegt daneben als manifest.json.sig.
        'manifest_url' => env('KLUFT_UPDATE_URL'),

        // Öffentlicher Ed25519-Schlüssel (base64), mit dem jedes Release
        // signiert sein muss. Ohne Schlüssel wird nichts installiert.
        'public_key' => env('KLUFT_UPDATE_PUBLIC_KEY'),

        // Arbeitsverzeichnis für Downloads, Entpacken und Rücksicherung.
        'path' => storage_path('app/private/updates'),
    ],

];
