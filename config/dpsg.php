<?php

return [

    /*
    | Stufen in pädagogischer Reihenfolge. Farbklassen stehen bewusst NICHT
    | hier, sondern als Literale in den Blade-Views, weil Tailwind nur
    | resources/ nach Klassennamen durchsucht.
    */
    'stages' => [
        'biber' => ['label' => 'Biber', 'age' => '4 bis 7 Jahre', 'from' => 4, 'to' => 7, 'mascot' => 'images/stufen/biber.png',
            'tagline' => 'Neugierig durch die Welt gehen'],
        'woelflinge' => ['label' => 'Wölflinge', 'age' => '6 bis 10 Jahre', 'from' => 6, 'to' => 10, 'mascot' => 'images/stufen/woelflinge.png',
            'tagline' => 'Forscher*innen, Entdecker*innen und Kundschafter*innen'],
        'jupfi' => ['label' => 'Jungpfadfinder*innen', 'age' => '10 bis 13 Jahre', 'from' => 10, 'to' => 13, 'mascot' => 'images/stufen/jupfi.png',
            'tagline' => 'Stück für Stück die Welt erobern'],
        'pfadi' => ['label' => 'Pfadfinder*innen', 'age' => '13 bis 15 Jahre', 'from' => 13, 'to' => 15, 'mascot' => 'images/stufen/pfadi.png',
            'tagline' => 'Das eigene Leben selbst in die Hand nehmen'],
        'rover' => ['label' => 'Rover', 'age' => '15 bis 20 Jahre', 'from' => 15, 'to' => 20, 'mascot' => 'images/stufen/rover.svg',
            'tagline' => 'Entscheidungen für die eigene Zukunft treffen'],
    ],

    // Übersichtsseite der Stufen (zeigt Altersleiste und Stufenkacheln).
    'stages_page' => 'stufen',

    /*
    | Sektionen mit eigener Landingpage, Navigation und Farbakzent
    | (Pflichtenheft 6.2). Der Schlüssel ist zugleich der Rollen-Scope
    | (sektionsadmin|section|<schlüssel>) und pages/posts.section.
    */
    'sections' => [
        'bolivien' => [
            'label' => 'Bolivienpartnerschaft',
            'landing' => 'bolivien',
            'domains' => ['bolivienpartnerschaft.de', 'www.bolivienpartnerschaft.de'],
            // Partnerschaftslogo „Amistad – Freundschaft“ (ohne Endung; .webp/.png)
            'logo' => 'images/bolivien/amistad-freundschaft',
            'logo_alt' => 'Amistad – Freundschaft: ASB Cochabamba & DPSG Trier',
            // Seiten, die nur dezent unten verlinkt werden (nicht in Navigation/Kacheln)
            'quiet_pages' => ['bolivien-spenden'],
        ],
    ],

    'contact' => [
        'name' => 'DPSG Diözesanverband Trier',
        'office' => 'Diözesanbüro',
        'street' => 'Weberbach 71',
        'city' => '54290 Trier',
        'phone' => '0651 4 629 829 0',
        'phone_href' => '+4965146298290',
        'email' => 'info@dpsg-trier.de',
    ],

    /*
    | Selbstgehostete Karte (F-MAP-04). Bei neuem Build-Datum ändert sich
    | der Dateiname, sodass der Deploy die neue Datei hochlädt.
    */
    'map' => [
        'build' => '20260930',
        'pmtiles' => 'maps/data/trier-20260930-z13.pmtiles',
        // West, Süd, Ost, Nord – Ausdehnung des Bistums Trier mit Rand
        'bbox' => [6.0, 49.05, 8.15, 50.85],
        'center' => [7.05, 49.85],
    ],

    /*
    | Gremien mit Personenvorstellung (Pflichtenheft 6.1 F-CMS-12). Die
    | Personen erscheinen auf der jeweiligen Seite unter dem Seitentext.
    */
    // Gestaltung der Initialen-Kreise: Stufenfarben plus Muster.
    'person_badges' => [
        'biber' => 'Biber (Stufenfarbe)',
        'woelflinge' => 'Wölflinge (Stufenfarbe)',
        'jupfi' => 'Jungpfadfinder*innen (Stufenfarbe)',
        'pfadi' => 'Pfadfinder*innen (Stufenfarbe)',
        'rover' => 'Rover (Stufenfarbe)',
        'inklusion' => 'Ich-Du-Wir / Inklusion (weiß mit bunten Punkten)',
        'bolivien' => 'Bolivienpartnerschaft (Streifen)',
    ],

    'gremien' => [
        'leitung' => ['label' => 'Diözesanleitung', 'page' => 'dioezesanleitung'],
        'buero' => ['label' => 'Diözesanbüro', 'page' => 'dioezesanbuero'],
    ],

    /*
    | Formular-Baukasten für Veranstaltungsanmeldungen (F-EV-03). Name und
    | E-Mail sind immer Pflicht; alles hier wird je Veranstaltung als
    | "nicht abfragen / optional / Pflicht" eingestellt. Gesundheitsdaten
    | sind bewusst nicht enthalten (Pflichtenheft 8.2, 9.1).
    */
    'event_fields' => [
        'birth_year' => ['label' => 'Geburtsjahr', 'type' => 'year'],
        'stamm' => ['label' => 'Stamm', 'type' => 'stamm'],
        'phone' => ['label' => 'Telefonnummer für Rückfragen', 'type' => 'tel'],
        'diet' => ['label' => 'Verpflegung', 'type' => 'select', 'options' => [
            'alles' => 'Esse alles',
            'vegetarisch' => 'Vegetarisch',
            'vegan' => 'Vegan',
        ]],
        'comment' => ['label' => 'Anmerkungen', 'type' => 'textarea'],
    ],

    /*
    | Versionierter Einwilligungstext der Veranstaltungsanmeldung (9.2
    | "Einwilligungsnachweis"). Bei inhaltlicher Änderung die Version
    | hochzählen – jede Anmeldung speichert die Version, der sie zustimmte.
    */
    'event_consent' => [
        'version' => '2026-10-01',
        'text' => 'Ich willige ein, dass der DPSG Diözesanverband Trier meine Angaben zur Organisation und Durchführung dieser Veranstaltung verarbeitet. Die E-Mails werden über unseren Dienstleister Brevo versendet. Die Daten werden spätestens :tage Tage nach Ende der Veranstaltung gelöscht; unbestätigte Anmeldungen nach 48 Stunden. Ich kann die Einwilligung jederzeit über den Link in der Bestätigungsmail oder per E-Mail an info@dpsg-trier.de widerrufen.',
        'guardian_text' => 'Ich bin personensorgeberechtigt für die angemeldete Person und willige in ihre Teilnahme sowie die Verarbeitung ihrer Angaben ein.',
    ],

    /*
    | Backups (Pflichtenheft 10.2, 11.4): täglich, verschlüsselt, 14 Tage.
    | Das Passwort gehört NICHT auf den Server allein, sondern zusätzlich
    | offline in den Passwortmanager – ohne es ist kein Backup lesbar.
    | Optional Ablage außerhalb des Webspace per WebDAV (z. B. Nextcloud).
    */
    'backup' => [
        'password' => env('BACKUP_PASSWORD'),
        'keep_days' => 14,
        'webdav_url' => env('BACKUP_WEBDAV_URL'),
        'webdav_user' => env('BACKUP_WEBDAV_USER'),
        'webdav_password' => env('BACKUP_WEBDAV_PASSWORD'),
    ],

    // Unter diesem Alter ist die Einwilligung der Personensorgeberechtigten
    // nötig (F-EV-14, § 8 Abs. 8 KDG).
    'guardian_consent_age' => 16,

];
