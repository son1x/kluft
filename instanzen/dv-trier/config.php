<?php

use Instanzen\DvTrier\Seeders\DvTrierSeeder;
use Instanzen\DvTrier\Seeders\InhaltSeeder;

/*
| Kluft-Instanz „dv-trier“: DPSG Diözesanverband Trier.
| Alles, was je Gliederung verschieden ist. config/dpsg.php lädt diese
| Datei, wenn KLUFT_INSTANZ=dv-trier gesetzt ist (Standard), und ersetzt
| damit die gleichnamigen Schlüssel der allgemeinen Konfiguration.
*/

return [

    /*
    | Wer diese Website betreibt. Texte setzen die Formen so zusammen:
    | „des <name_genitive>“, „der <short_name>“ (DPSG ist weiblich),
    | „im <region>“, „im <level>“, „des <level_genitive>“.
    */
    'site' => [
        'name' => 'DPSG Diözesanverband Trier',
        'name_genitive' => 'DPSG Diözesanverbands Trier',
        'short_name' => 'DPSG Trier',
        // Gebiet in Texten wie „Stämme im Bistum Trier“.
        'region' => 'Bistum Trier',
        'level' => 'Diözesanverband',
        'level_genitive' => 'Diözesanverbands',
        // Arbeitskreis einer Stufe auf dieser Ebene; Kürzel null = „Arbeitskreis“.
        'ak' => 'Diözesanarbeitskreis',
        'ak_short' => 'DAK',
        'domain' => 'dpsg-trier.de',
        'description' => 'Deutsche Pfadfinderschaft Sankt Georg im Bistum Trier: Neuigkeiten, Veranstaltungen, Stufen und Stämme zwischen Mosel, Eifel, Hunsrück und Saarland.',
        'logo' => 'images/logo-dv-trier.png',
        'logo_size' => [300, 223],
        // Leitungsgremium (Seite und Bezeichnung).
        'leitung_page' => 'dioezesanleitung',
        'leitung_label' => 'Diözesanleitung',
        // Erste Anlaufstelle (Kopfzeile, Startseite, Mails). `article` ist
        // der Artikel im Nominativ: das → „zum/beim/im“, die → „zur/bei der“.
        'office' => ['label' => 'Diözesanbüro', 'page' => 'dioezesanbuero', 'article' => 'das'],
        // Text im blauen „Mitmachen“-Band der Startseite.
        'join_text' => 'Du möchtest Pfadfinder*in werden oder als Leiter*in mitmachen? In über 60 Stämmen im ganzen Bistum Trier ist sicher auch einer in deiner Nähe. Das Diözesanbüro hilft dir weiter.',
        // Übergeordnete Gliederung (Footer „DPSG im Netz“); null beim DV,
        // dessen übergeordnete Ebene ist der Bundesverband (immer verlinkt).
        'parent' => null,
        // Weitere Links im Footer unter „DPSG im Netz“.
        'links' => [
            ['label' => 'Bistum Trier', 'url' => 'https://www.bistum-trier.de'],
        ],
        'hashtags' => '#dpsg #pfadfinden #dpsgtrier',
    ],

    /*
    | Startinhalt (php artisan db:seed beim ersten Deploy) und der Teil davon,
    | den jede DV-Installation schon über die früheren Daten-Migrationen hat
    | (läuft auch in den Tests).
    */
    'seeders' => [
        'start' => DvTrierSeeder::class,
        'basis' => InhaltSeeder::class,
    ],

    'directory' => ['path' => 'bezirke-staemme', 'title' => 'Bezirke & Stämme'],

    // Diözesanbüro hängt unter „Kontakt“, erscheint aber auch als Kachel
    // unten auf „Diözesanverband“.
    'overview_extra' => [
        'dioezesanverband' => ['dioezesanbuero'],
    ],

    // Feld „Stamm“ der Veranstaltungsanmeldung.
    'event_stamm_label' => 'Stamm / Bezirk / Verband',
    'event_herkunft' => [
        'hier' => 'aus dem DV Trier',
        'gast' => 'Gast aus einem anderen Diözesanverband oder Verband',
    ],

    'features' => [
        'georgsbote' => true,
        'roadmap' => true,
        'rechtstraeger' => true,
        'wp_import' => true,
        'downloads' => true,
    ],

    // Download-Seite unter „Diözesanverband“ (s. config/dpsg.php).
    'downloads' => [
        'slug' => 'downloads',
        'title' => 'Downloads',
        'categories' => [
            'abrechnung' => 'Abrechnungen (Diözesanebene)',
            'zuschuesse' => 'Zuschüsse & Förderung',
            'formulare' => 'Formulare',
            'methoden' => 'Methoden',
            'arbeitshilfen' => 'Arbeitshilfen',
            'praevention' => 'Prävention',
        ],
    ],

    /*
    | E-Mail-Newsletter (Anmeldung per Double-Opt-In über Brevo). `slug` ist
    | zugleich die Adresse (/georgsbrief) und die CMS-Seite mit dem
    | Einleitungstext; `logo` ohne Endung (.png und .webp), optional.
    */
    'newsletter' => [
        'name' => 'Georgsbrief',
        'slug' => 'georgsbrief',
        'title' => 'Georgsbrief – der Newsletter der DPSG Trier',
        'description' => 'Der Georgsbrief ist der Newsletter des DPSG Diözesanverbands Trier: Termine, Neuigkeiten und Infos direkt ins Postfach.',
        'text' => 'Der Georgsbrief ist unser Newsletter: Termine, Neuigkeiten und Infos aus dem Diözesanverband – direkt in dein Postfach.',
        'logo' => 'images/georgsbrief-logo',
        'logo_size' => [970, 251],
        'logo_alt' => 'Georgsbrief – der DPSG Trier Newsletter',
    ],

    'redirects' => [
        // Alte Formular-Seite der WordPress-Website.
        '/downloads/formulare' => '/downloads',
        // Frühere Adresse der Bestätigungsseite (evtl. schon in Brevo hinterlegt).
        '/georgsbote/danke' => '/georgsbrief/danke',
    ],

    /*
    | Kacheln unter „Mitmachen“ auf der Startseite: Slug einer CMS-Seite
    | (erscheint nur, wenn veröffentlicht), optional Bild (ohne Endung,
    | .png und .webp, mit Größe), Text des Knopfs und eine Kachel im Service-Block.
    */
    'home_highlights' => [
        'bolivien' => ['service' => ['label' => 'Bolivien', 'icon' => 'heroicon-o-globe-europe-africa']],
        'georgsbote' => ['service' => ['label' => 'Georgsbote', 'icon' => 'heroicon-o-book-open']],
        'georgsbrief' => ['image' => 'images/georgsbrief-logo', 'image_size' => [970, 251], 'button' => 'Jetzt abonnieren',
            'service' => ['label' => 'Georgsbrief', 'icon' => 'heroicon-o-envelope-open']],
    ],

    /*
    | Fachbereiche („Sektionen“) mit eigener Landingpage, Navigation und
    | Farbakzent (Pflichtenheft 6.2). Der Schlüssel ist zugleich der
    | Rollen-Scope (sektionsadmin|section|<schlüssel>) und
    | pages/posts.section. `color` = Fachbereichsfarbe aus dem CD
    | (Klasse .fach-<color> in app.css).
    */
    'sections' => [
        'bolivien' => [
            'label' => 'Bolivienpartnerschaft',
            'color' => 'internationales',
            'landing' => 'bolivien',
            'domains' => ['bolivienpartnerschaft.de', 'www.bolivienpartnerschaft.de'],
            // Partnerschaftslogo „Amistad – Freundschaft“ (ohne Endung; .webp/.png)
            'logo' => 'images/bolivien/amistad-freundschaft',
            'logo_alt' => 'Amistad – Freundschaft: ASB Cochabamba & DPSG Trier',
            'logo_size' => [744, 335],
            // Seiten, die nur dezent unten verlinkt werden (nicht in Navigation/Kacheln)
            'quiet_pages' => ['bolivien-spenden'],
        ],
        'internationale-gerechtigkeit' => [
            'label' => 'Internationale Gerechtigkeit',
            'color' => 'globale-gerechtigkeit',
            'icon' => 'globe-europe-africa',
            'landing' => 'internationale-gerechtigkeit',
        ],
        // Beraten-Online (Pflichtenheft 6.3): BeratungsPower & Coaching für
        // ehrenamtliche Leitungskräfte, eigene Domain wie Bolivien.
        'beratung' => [
            'label' => 'BeratungsPower & Coaching',
            // Kein Fachbereich, sondern ein Serviceangebot des DV: steht
            // unter „Diözesanverband“, nicht in der Fachbereichs-Übersicht.
            'kind' => 'Serviceangebot',
            'color' => 'oekologie',
            'landing' => 'beratung',
            'domains' => ['beraten-online.de', 'www.beraten-online.de'],
            // „Let's make a Better Place – Wir beraten euch!“ (ohne Endung; .webp/.png)
            'logo' => 'images/beratung/lets-make-a-better-place',
            'logo_alt' => 'Let’s make a Better Place – Wir beraten euch! BP BeratungsPower',
            'logo_size' => [303, 222],
        ],
        'inklusion' => [
            'label' => 'Inklusion',
            'color' => 'inklusion',
            'icon' => 'user-group',
            // Inklusions-Lilie der DPSG (dpsg.de → Logos, Material & Vorlagen;
            // für die Arbeit in der DPSG frei verfügbar).
            'logo' => 'images/fachbereiche/inklusion-lilie',
            'logo_alt' => 'Inklusions-Lilie der DPSG: Lilie mit bunten Punkten',
            'logo_size' => [600, 591],
            'landing' => 'inklusion',
        ],
        // Fachbereiche mit Facharbeitskreis (`fak` => true: Ehrenamtliche
        // im Gremium „fak“ erscheinen auf der Fachbereichsseite).
        'spiritualitaet' => [
            'label' => 'Spiritualität',
            'color' => 'spiritualitaet',
            'icon' => 'sparkles',
            'landing' => 'spiritualitaet',
            'fak' => true,
        ],
        'ausbildung' => [
            'label' => 'Ausbildung',
            'color' => 'ausbildung',
            'icon' => 'academic-cap',
            'landing' => 'ausbildung',
            'fak' => true,
        ],
        'oekologie' => [
            'label' => 'Ökologie',
            'color' => 'oekologie',
            'icon' => 'sun',
            'landing' => 'oekologie',
            'fak' => true,
        ],
        'praevention' => [
            'label' => 'Prävention',
            // Kein Fachbereich: Thema der Hauptberuflichen, unter „Diözesanverband“.
            'kind' => 'Thema',
            'color' => 'praevention',
            'icon' => 'shield-check',
            'landing' => 'praevention',
        ],
        'politische-bildung' => [
            'label' => 'Politische Bildung',
            'color' => 'politische-bildung',
            'icon' => 'megaphone',
            'landing' => 'politische-bildung',
            'fak' => true,
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

    // Persönliche Adressen nach dem Schema Initial.Nachname@<domain>
    // (DAK-Mitglieder haben keine eigene Adresse).
    'person_email_domain' => 'dpsg-trier.de',

    'gremien' => [
        'leitung' => ['label' => 'Diözesanleitung', 'page' => 'dioezesanleitung'],
        'buero' => ['label' => 'Diözesanbüro', 'page' => 'dioezesanbuero'],
        // Je Stufe ein Diözesanarbeitskreis; die Person erscheint auf der
        // Seite ihrer Stufe (Feld „Stufe“), daher keine feste Seite.
        'dak' => ['label' => 'Diözesanarbeitskreis (DAK) einer Stufe', 'page' => null],
        // Facharbeitskreis eines Fachbereichs; die Person erscheint auf der
        // Seite ihres Fachbereichs (Feld „Fachbereich“).
        'fak' => ['label' => 'Facharbeitskreis (FAK) eines Fachbereichs', 'page' => null],
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
    | WordPress-Migration (Pflichtenheft 6.9, Phase 1e). Kategorien → Stufe
    | bzw. Fachbereich und WP-Autor (Slug) → CMS-Konto (E-Mail) sind
    | optional; ohne Zuordnung „Allgemein“ bzw. ohne Autor.
    */
    'wp_import' => [
        'source' => env('WP_IMPORT_SOURCE', 'https://www.dpsg-trier.de'),
        'since_years' => 3,
        'categories' => [
            // 'woelflinge' => ['stage' => 'woelflinge'],
            // 'bolivien' => ['section' => 'bolivien'],
        ],
        'authors' => [
            // 'wp-autor-slug' => 'vorname.nachname@dpsg-trier.de',
        ],
    ],

];
