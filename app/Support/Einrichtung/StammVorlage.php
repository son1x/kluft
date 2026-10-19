<?php

namespace App\Support\Einrichtung;

use Database\Seeders\Einrichtung\StammStartSeeder;

/**
 * Konfiguration einer Stammes-Website aus den Angaben des
 * Einrichtungsassistenten (instanzen/<schlüssel>/angaben.json). Gleiches
 * Format wie instanzen/dv-trier/config.php; was ein Stamm nicht braucht
 * (Verzeichnis, Karte, Fachbereiche, Georgsbote …), bleibt aus.
 */
class StammVorlage
{
    /**
     * @param  array<string, mixed>  $a
     * @return array<string, mixed>
     */
    public static function config(array $a): array
    {
        $stamm = trim((string) ($a['stamm'] ?? 'Beispiel'));
        $ort = trim((string) ($a['ort'] ?? ''));
        $email = trim((string) ($a['email'] ?? ''));
        $domain = trim((string) ($a['domain'] ?? 'localhost'));
        $telefon = trim((string) ($a['telefon'] ?? ''));
        $name = 'DPSG Stamm '.$stamm;
        $bezirk = trim((string) ($a['bezirk'] ?? ''));
        $dioezese = trim((string) ($a['dioezese'] ?? ''));

        // Link zur nächsthöheren Ebene im Fuß; dpsg.de steht dort ohnehin.
        $eltern = match (true) {
            $bezirk !== '' && filled($a['bezirk_url'] ?? null) => ['label' => 'DPSG Bezirk '.$bezirk, 'url' => (string) $a['bezirk_url']],
            $dioezese !== '' && filled($a['dioezese_url'] ?? null) => ['label' => 'DPSG Diözesanverband '.$dioezese, 'url' => (string) $a['dioezese_url']],
            (bool) preg_match('/^trier$/i', $dioezese) => ['label' => 'DPSG Diözesanverband Trier', 'url' => 'https://www.dpsg-trier.de'],
            default => null,
        };

        return [
            'site' => [
                'name' => $name,
                'name_genitive' => 'DPSG Stammes '.$stamm,
                'short_name' => 'Stamm '.$stamm,
                'region' => $ort !== '' ? $ort : 'Stamm '.$stamm,
                'level' => 'Stamm',
                'level_genitive' => 'Stammes',
                // Leitende einer Stufe; alle Leitenden zusammen sind die Leitungsrunde.
                'ak' => 'Leitungsteam',
                'ak_short' => 'Leitungsteam',
                'ak_article' => 'das',
                'ak_text' => 'Die Gruppenstunden, Fahrten und Lager der Stufe planen ehrenamtliche Leiter*innen. Zusammen mit allen anderen Leitenden des Stammes bilden sie die Leitungsrunde.',
                'ak_subgroups' => ['leitung' => 'Aus dem Stammesvorstand', 'dak' => 'Im Leitungsteam'],
                'domain' => $domain,
                'description' => 'Deutsche Pfadfinderschaft Sankt Georg, Stamm '.$stamm.($ort !== '' ? ' in '.$ort : '').': Gruppenstunden, Termine und Neuigkeiten.',
                'logo' => (string) ($a['logo'] ?? 'instanz/images/logo.svg'),
                'logo_size' => array_values(array_map('intval', (array) ($a['logo_size'] ?? [600, 200]))),
                'favicon' => 'favicon.ico',
                'leitung_page' => 'stammesvorstand',
                'leitung_label' => 'Stammesvorstand',
                'office' => ['label' => 'Stammesvorstand', 'page' => 'stammesvorstand', 'article' => 'der'],
                'join_text' => 'Du möchtest Pfadfinder*in werden oder als Leiter*in mitmachen? Komm zu einer Gruppenstunde vorbei – sag uns vorher kurz Bescheid.',
                'parent' => $eltern,
                'links' => [],
                'hashtags' => '#dpsg #pfadfinden',
            ],

            // Ausbaustufe zu Beginn (App\Support\Ausbau); ältere Installationen: Vollausbau.
            'ausbau' => (int) ($a['ausbau'] ?? 5),

            'seeders' => ['start' => StammStartSeeder::class, 'basis' => null],

            // Angaben des Assistenten, z. B. für den Startinhalt.
            'einrichtung' => $a,

            // Kein Verzeichnis und keine Karte: Die Website gehört einem Stamm.
            'directory' => ['path' => null, 'title' => null],
            'map' => ['build' => null, 'pmtiles' => null, 'bbox' => [5.8, 47.2, 15.1, 55.1], 'center' => [10.4, 51.1]],

            'event_stamm_label' => 'Stamm',
            'event_herkunft' => [
                'hier' => 'aus dem Stamm '.$stamm,
                'gast' => 'Gast aus einem anderen Stamm oder Verband',
            ],

            // Standard der Funktionen; „funktionen“ in angaben.json überstimmt ihn
            // (z. B. Roden: mailformular). Im Backend bleibt alles schaltbar.
            'features' => array_replace([
                'georgsbote' => false,
                'roadmap' => false,
                'rechtstraeger' => false,
                'wp_import' => false,
                'downloads' => false,
                'code_login' => true,
                'mitgliedsbeitraege' => true,
            ], array_map('boolval', (array) ($a['funktionen'] ?? []))),

            'newsletter' => null,
            'home_highlights' => [],
            // Titelbild wählt der Stamm im Backend (Seite „start“).
            'titelbilder' => [],
            'sections' => [],

            'contact' => [
                'name' => $name,
                'office' => 'Stammesvorstand',
                'street' => trim((string) ($a['strasse'] ?? '')),
                'city' => trim((string) ($a['plz_ort'] ?? $ort)),
                'phone' => $telefon !== '' ? $telefon : null,
                'phone_href' => $telefon !== '' ? self::telefonLink($telefon) : null,
                'email' => $email,
            ],

            // Abweichende Stufenfarben, z. B. gelbe Biber (config/dpsg.php).
            'stage_colors' => (array) ($a['stufenfarben'] ?? []),

            'person_badges' => [
                'biber' => 'Biber (Stufenfarbe)',
                'woelflinge' => 'Wölflinge (Stufenfarbe)',
                'jupfi' => 'Jungpfadfinder*innen (Stufenfarbe)',
                'pfadi' => 'Pfadfinder*innen (Stufenfarbe)',
                'rover' => 'Rover (Stufenfarbe)',
            ],

            'person_email_domain' => (string) preg_replace('/^www\./i', '', $domain),
            // „vorname“: michele@… statt m.franke@…; Leitende mit eigener Adresse.
            'person_email_schema' => (string) ($a['email_schema'] ?? 'initial.nachname'),
            'person_email_leitende' => (bool) ($a['email_leitende'] ?? false),

            'gremien' => [
                'leitung' => ['label' => 'Stammesvorstand', 'page' => 'stammesvorstand'],
                'dak' => ['label' => 'Leitungsteam einer Stufe', 'page' => null],
            ],

            'event_consent' => [
                'version' => (string) ($a['eingerichtet'] ?? '2026-10-09'),
                'text' => 'Ich willige ein, dass der '.$name.' meine Angaben zur Organisation und Durchführung dieser Veranstaltung verarbeitet. Die Daten werden spätestens :tage Tage nach Ende der Veranstaltung gelöscht; unbestätigte Anmeldungen nach 48 Stunden. Ich kann die Einwilligung jederzeit über den Link in der Bestätigungsmail oder per E-Mail an '.$email.' widerrufen.',
                'guardian_text' => 'Ich bin personensorgeberechtigt für die angemeldete Person und willige in ihre Teilnahme sowie die Verarbeitung ihrer Angaben ein.',
            ],

            'wp_import' => ['source' => null, 'since_years' => 3, 'categories' => [], 'authors' => []],
        ];
    }

    /** „0651 123 45“ → „+4965112345“ für tel:-Links. */
    private static function telefonLink(string $telefon): string
    {
        $nummer = (string) preg_replace('/[^0-9+]/', '', $telefon);

        return match (true) {
            str_starts_with($nummer, '00') => '+'.substr($nummer, 2),
            str_starts_with($nummer, '0') => '+49'.substr($nummer, 1),
            default => $nummer,
        };
    }
}
