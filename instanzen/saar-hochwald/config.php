<?php

use Instanzen\SaarHochwald\Seeders\BasisSeeder;
use Instanzen\SaarHochwald\Seeders\BezirkSeeder;

/*
| Kluft-Instanz „saar-hochwald“: DPSG Bezirk Saar-Hochwald.
| Werte aus der bisherigen Bezirkswebsite (son1x/dpsg-bezirk-saar-hochwald,
| Release 1.0 und CMS-Umstellung). Format wie instanzen/dv-trier/config.php.
| Bilder und Favicon liegen unter public/ dieses Ordners und werden beim
| Deploy nach public/instanz/ kopiert (php artisan kluft:assets).
*/

return [

    'site' => [
        'name' => 'DPSG Bezirk Saar-Hochwald',
        'name_genitive' => 'DPSG Bezirks Saar-Hochwald',
        'short_name' => 'DPSG Saar-Hochwald',
        'region' => 'Bezirk Saar-Hochwald',
        'level' => 'Bezirk',
        'level_genitive' => 'Bezirks',
        'ak' => 'Bezirksarbeitskreis',
        'ak_short' => null,
        'domain' => 'dpsg-saar-hochwald.de',
        'description' => 'Deutsche Pfadfinderschaft Sankt Georg im Bezirk Saar-Hochwald: Neuigkeiten, Termine, Stufen und Stämme zwischen Saarlouis, Rehlingen-Siersburg und Perl.',
        'logo' => 'instanz/images/logo-saar-hochwald.png',
        'logo_size' => [811, 280],
        'favicon' => 'instanz/favicon.ico',
        'leitung_page' => 'bezirksleitung',
        'leitung_label' => 'Bezirksleitung',
        // Im Bezirk ist die Bezirksleitung zugleich die Anlaufstelle.
        'office' => ['label' => 'Bezirksleitung', 'page' => 'bezirksleitung', 'article' => 'die'],
        'join_text' => 'Du möchtest Pfadfinder*in werden oder als Leiter*in mitmachen? In unseren fünf Stämmen zwischen Saarlouis, Rehlingen-Siersburg und Perl ist sicher einer in deiner Nähe. Die Bezirksleitung hilft dir weiter.',
        'parent' => ['label' => 'DPSG Diözesanverband Trier', 'url' => 'https://www.dpsg-trier.de'],
        'links' => [
            ['label' => 'Bistum Trier', 'url' => 'https://www.bistum-trier.de'],
        ],
        'hashtags' => '#dpsg #pfadfinden #saarhochwald',
    ],

    'seeders' => [
        'start' => BezirkSeeder::class,
        'basis' => BasisSeeder::class,
    ],

    'directory' => ['path' => 'staemme', 'title' => 'Unsere Stämme'],

    'event_stamm_label' => 'Stamm',

    // Georgsbote, Roadmap, Rechtsträger und WordPress-Import gibt es hier nicht.
    'features' => [
        'georgsbote' => false,
        'roadmap' => false,
        'rechtstraeger' => false,
        'wp_import' => false,
    ],

    'newsletter' => [
        'name' => 'Newsletter',
        'slug' => 'newsletter',
        'title' => 'Newsletter – DPSG Bezirk Saar-Hochwald',
        'description' => 'Der Newsletter des DPSG Bezirks Saar-Hochwald: Termine, Neuigkeiten und Infos direkt ins Postfach.',
        'text' => 'Unser Newsletter: Termine, Neuigkeiten und Infos aus dem Bezirk Saar-Hochwald – direkt in dein Postfach.',
        'logo' => null,
    ],

    'home_highlights' => [
        'der-bezirk' => [],
        'praevention' => [],
        'newsletter' => ['button' => 'Jetzt abonnieren'],
    ],

    // Der Bezirk hat (noch) keine Fachbereiche mit eigener Landingpage.
    'sections' => [],

    'contact' => [
        'name' => 'DPSG Bezirk Saar-Hochwald',
        'office' => 'Bezirksleitung',
        'street' => 'Donatusstraße 34a',
        'city' => '66740 Saarlouis',
        'phone' => '0173 479 59 59',
        'phone_href' => '+491734795959',
        'email' => 'bezirksleitung@dpsg-saar-hochwald.de',
    ],

    'map' => [
        'build' => '20260930',
        'pmtiles' => 'maps/data/saar-hochwald-20260930-z13.pmtiles',
        // West, Süd, Ost, Nord – Bezirksgebiet (Landkreis Saarlouis bis Perl) mit Rand
        'bbox' => [6.2, 49.15, 7.15, 49.65],
        'center' => [6.65, 49.38],
    ],

    'person_badges' => [
        'biber' => 'Biber (Stufenfarbe)',
        'woelflinge' => 'Wölflinge (Stufenfarbe)',
        'jupfi' => 'Jungpfadfinder*innen (Stufenfarbe)',
        'pfadi' => 'Pfadfinder*innen (Stufenfarbe)',
        'rover' => 'Rover (Stufenfarbe)',
        'inklusion' => 'Ich-Du-Wir / Inklusion (weiß mit bunten Punkten)',
    ],

    'person_email_domain' => 'dpsg-saar-hochwald.de',

    'gremien' => [
        'leitung' => ['label' => 'Bezirksleitung', 'page' => 'bezirksleitung'],
        // Schlüssel „dak“ ist der Arbeitskreis einer Stufe (hier Bezirksarbeitskreis).
        'dak' => ['label' => 'Bezirksarbeitskreis einer Stufe', 'page' => null],
    ],

    'event_consent' => [
        'version' => '2026-10-04',
        'text' => 'Ich willige ein, dass der DPSG Bezirk Saar-Hochwald meine Angaben zur Organisation und Durchführung dieser Veranstaltung verarbeitet. Die E-Mails werden über unseren Dienstleister Brevo versendet. Die Daten werden spätestens :tage Tage nach Ende der Veranstaltung gelöscht; unbestätigte Anmeldungen nach 48 Stunden. Ich kann die Einwilligung jederzeit über den Link in der Bestätigungsmail oder per E-Mail an bezirksleitung@dpsg-saar-hochwald.de widerrufen.',
        'guardian_text' => 'Ich bin personensorgeberechtigt für die angemeldete Person und willige in ihre Teilnahme sowie die Verarbeitung ihrer Angaben ein.',
    ],

    // Der Bezirk hatte keine WordPress-Seite.
    'wp_import' => [
        'source' => env('WP_IMPORT_SOURCE'),
        'since_years' => 3,
        'categories' => [],
        'authors' => [],
    ],

];
