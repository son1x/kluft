<?php

/*
| Kluft: ein Code für mehrere Gliederungen. Die Umgebungsvariable
| KLUFT_INSTANZ wählt den Ordner instanzen/<schlüssel>/ (Standard
| dv-trier); dessen config.php ersetzt die gleichnamigen Schlüssel unten.
| Hier steht nur, was für alle Gliederungen gleich ist.
*/
$instanz = (string) env('KLUFT_INSTANZ', 'dv-trier');
$instanzDatei = dirname(__DIR__).'/instanzen/'.$instanz.'/config.php';

if (! preg_match('/^[a-z0-9-]+$/', $instanz) || ! is_file($instanzDatei)) {
    throw new RuntimeException('KLUFT_INSTANZ „'.$instanz.'“ unbekannt: instanzen/'.$instanz.'/config.php fehlt.');
}

return array_replace([

    // Schlüssel der aktiven Instanz (Ordnername unter instanzen/).
    'instanz' => $instanz,

    // Startseiten-Kacheln; Format siehe instanzen/dv-trier/config.php.
    'home_highlights' => [],

    /*
    | Schaltbare Funktionen (App\Support\Features). Abgeschaltet gibt es
    | weder Routen noch Menüpunkte noch Backend-Bereiche.
    */
    'features' => [
        'georgsbote' => false,     // Archiv der Verbandszeitschrift (PDF)
        'roadmap' => false,        // /roadmap: Projektstand nach Pflichtenheft
        'rechtstraeger' => false,  // Schaubild auf der Seite „rechtstraeger“
        'wp_import' => false,      // Übernahme aus einer WordPress-Website
    ],

    // E-Mail-Newsletter über Brevo; null = keiner. Format siehe instanzen/dv-trier.
    'newsletter' => null,

    // Feste 301-Weiterleitungen alter Adressen (Pfad => Ziel).
    'redirects' => [],

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

    // Fachbereiche mit eigener Landingpage; Format siehe instanzen/dv-trier.
    'sections' => [],

    // Übersichtsseite der Fachbereiche (Hauptmenüpunkt mit Unterpunkten).
    'sections_page' => 'fachbereiche',

    // CMS-Seite mit dem Kontaktformular (F-CMS-09).
    'contact_page' => 'kontakt',

    /*
    | Formular-Baukasten für Veranstaltungsanmeldungen (F-EV-03). Name und
    | E-Mail sind immer Pflicht; alles hier wird je Veranstaltung als
    | "nicht abfragen / optional / Pflicht" eingestellt. Gesundheitsdaten
    | sind bewusst nicht enthalten (Pflichtenheft 8.2, 9.1).
    */
    'event_fields' => [
        'birth_year' => ['label' => 'Geburtsjahr', 'type' => 'year'],
        'stamm' => ['label' => 'Stamm oder Bezirk', 'type' => 'stamm'],
        'gremium' => ['label' => 'Funktion / Gremium (z. B. Bezirksvorstand, Delegierte*r, Gast)', 'type' => 'text'],
        'phone' => ['label' => 'Telefonnummer für Rückfragen', 'type' => 'tel'],
        'diet' => ['label' => 'Verpflegung', 'type' => 'select', 'options' => [
            'alles' => 'Esse alles',
            'vegetarisch' => 'Vegetarisch',
            'vegan' => 'Vegan',
        ]],
        'uebernachtung' => ['label' => 'Übernachtung', 'type' => 'select', 'options' => [
            'komplett' => 'Ganze Zeit mit Übernachtung',
            'teilweise' => 'Teilweise – bitte in den Anmerkungen angeben',
            'ohne' => 'Ohne Übernachtung',
        ]],
        // Nur bei mehrtägigen Veranstaltungen: „ganze Zeit“ oder einzelne Tage.
        'tage' => ['label' => 'Anwesenheit (Tage)', 'type' => 'days', 'hint' => 'Nur bei mehrtägigen Veranstaltungen'],
        'comment' => ['label' => 'Anmerkungen', 'type' => 'textarea'],
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

], require $instanzDatei);
