<?php

/*
| Status je Überschrift des Pflichtenhefts (docs/pflichtenheft.md) für
| die Seite /roadmap. Status: done | partial | open | later | info.
| Ein Test prüft, dass alle Überschriften des Pflichtenhefts enthalten sind.
*/

return [
    'stand' => '2026-10-02',

    'current_phase' => '1e',

    // Erledigte Punkte, die /roadmap nicht automatisch prüfen kann
    // (Schlüssel aus App\Support\Roadmap::todoList()).
    'todos_done' => [],

    'phases' => [
        ['0', 'Setup', 'done'],
        ['1a', 'Basis', 'done'],
        ['1b', 'Kernmodule', 'done'],
        ['1c', 'Veranstaltungen', 'done'],
        ['1d', 'Instagram', 'partial'],
        ['1e', 'WordPress-Migration', 'partial'],
        ['Talia', 'Feedback Talia', 'open'],
        ['Abnahme', 'Abnahme', 'open'],
        ['2', 'Beraten-Online', 'later'],
    ],

    'sections' => [
        '1' => ['title' => 'Ziel, Kontext und Rahmenbedingungen', 'status' => 'info', 'note' => ''],
        '1.1' => ['title' => 'Ziel', 'status' => 'info', 'note' => ''],
        '1.2' => ['title' => 'Gesetzte Rahmenbedingungen', 'status' => 'info', 'note' => ''],
        '1.3' => ['title' => 'Leitprinzipien', 'status' => 'info', 'note' => ''],
        '2' => ['title' => 'Akteure und Rollen', 'status' => 'done', 'note' => 'Alle Akteure sind im Rollenmodell abgebildet.'],
        '3' => ['title' => 'Technologie-Vorschlag', 'status' => 'done', 'note' => ''],
        '3.1' => ['title' => 'Empfehlung', 'status' => 'done', 'note' => 'Laravel 12, Filament 4, Tailwind, MapLibre mit selbst gehosteten Kartendaten.'],
        '3.2' => ['title' => 'Begründung der Wartungsarmut', 'status' => 'info', 'note' => ''],
        '3.3' => ['title' => 'Verworfene Alternativen', 'status' => 'info', 'note' => ''],
        '4' => ['title' => 'Systemarchitektur', 'status' => 'partial', 'note' => ''],
        '4.1' => ['title' => 'Überblick', 'status' => 'done', 'note' => ''],
        '4.2' => ['title' => 'Multi-Domain / Multi-Site', 'status' => 'partial', 'note' => 'Domain-Erkennung für bolivienpartnerschaft.de ist fertig; DNS/Alias fehlen noch. Weitere Sites (Beraten-Online) folgen in Phase 2.'],
        '4.3' => ['title' => 'Verzeichnisstruktur (Soll)', 'status' => 'done', 'note' => ''],
        '4.4' => ['title' => 'Deployment', 'status' => 'partial', 'note' => 'Testumgebung wird bei jedem Push automatisch deployt. Produktiv-Deploy mit manueller Freigabe fehlt noch.'],
        '4.5' => ['title' => 'Hosting-Umgebung (Ist-Stand)', 'status' => 'done', 'note' => 'Läuft auf dem Netcup-Webhosting.'],
        '5' => ['title' => 'Authentifizierung und Autorisierung', 'status' => 'partial', 'note' => ''],
        '5.1' => ['title' => 'Entra-ID-Login (Redaktion)', 'status' => 'done', 'note' => 'Login über Microsoft 365, Freischaltung über Rollen.'],
        '5.2' => ['title' => 'Notfall-Super-Admin (Break-Glass)', 'status' => 'partial', 'note' => 'Funktion mit 2FA und Alarmmail fertig; Notfall-Account muss noch angelegt werden.'],
        '5.3' => ['title' => 'Rollenmodell mit Scopes', 'status' => 'done', 'note' => ''],
        '5.4' => ['title' => 'Berechtigungsmatrix', 'status' => 'done', 'note' => 'Umgesetzt mit Policies und Negativtests je Rolle.'],
        '5.5' => ['title' => 'Delegation und Freigabe-Workflow', 'status' => 'partial', 'note' => 'Delegation fertig. Offen: Mail an Freigebende, Jahres-Review der Rollen.'],
        '5.6' => ['title' => 'Benutzerverwaltung und Zuordnung zu M365-Konten', 'status' => 'partial', 'note' => 'Rollenvergabe per E-Mail-Adresse fertig. Offen: Suche im Entra-Verzeichnis (Graph), Jahres-Review.'],
        '6' => ['title' => 'Funktionale Anforderungen', 'status' => 'partial', 'note' => ''],
        '6.1' => ['title' => 'Redaktionelle Inhalte (Basis) – Phase 1', 'status' => 'partial', 'note' => 'Seiten, Blog, Mediathek, Stufenseiten, Rechtsseiten und Freigabe-Workflow fertig. KI-Kennzeichnung, Bildnachweis, WebP-Varianten und Personenvorstellung mit anklickbarem Gruppenfoto fertig. Offen: Versionierung, Suche, Kontaktformular, Freigabe-Mail.'],
        '6.2' => ['title' => 'Bolivienpartnerschaft – Phase 1', 'status' => 'partial', 'note' => 'Landingpage, eigene Navigation und Sektionsrechte fertig. Offen: Domain-Alias, Bankverbindung, eigener Feed.'],
        '6.3' => ['title' => 'Beraten-Online – Phase 2 (konzeptionell vorbereitet)', 'status' => 'later', 'note' => 'Phase 2.'],
        '6.4' => ['title' => 'Instagram-Integration – Phase 1 (S) / Phase 2', 'status' => 'partial', 'note' => 'Teilen aus dem Blog-Editor, Bildaufbereitung, Status/Permalink, Rechteprüfung und automatische Token-Erneuerung fertig (gegen simulierte API getestet). Es fehlt nur noch das echte Instagram-Konto.'],
        '6.5' => ['title' => 'Nextcloud-Kalender – Phase 1', 'status' => 'done', 'note' => 'Sync, Wiederholungen, Liste/Monat, Filter und SSRF-Schutz fertig. Kalender-Links müssen noch eingetragen werden.'],
        '6.6' => ['title' => 'Georgsbote (Brevo) – Phase 1', 'status' => 'partial', 'note' => 'Georgsbote (PDF-Zeitschrift): Archiv mit Veröffentlichungsprüfung fertig. Georgsbrief (Newsletter): eigene Seite mit Double-Opt-In fertig. Offen: Brevo-Zugangsdaten, Bulk-Upload alter Jahrgänge.'],
        '6.7' => ['title' => 'Veranstaltungsmanagement (Eigenentwicklung) – Phase 1', 'status' => 'done', 'note' => 'Anmeldung mit Double-Opt-In, Warteliste, Minderjährigen-Einwilligung, XLSX-Export und Löschfristen fertig.'],
        '6.8' => ['title' => 'Consent-Management – Phase 1', 'status' => 'partial', 'note' => 'Banner, Widerruf und lokale Auslieferung aller Dateien fertig. Offen: Zwei-Klick-Einbettungen.'],
        '6.9' => ['title' => 'WordPress-Migration – Phase 1e', 'status' => 'partial', 'note' => 'Import-Kommando über die WordPress-REST-API mit Testlauf, wiederholbar ohne Duplikate; bereinigt das HTML, übernimmt Bilder und PDFs (auch aus Link-Beiträgen auf den Download-Manager), schreibt interne Links um, legt 301-Weiterleitungen an, übernimmt keine Kommentare/Benutzer. Beiträge kommen als Entwurf mit Nacharbeitsliste; Veröffentlichung erst nach Durchsicht. Bericht im Backend. Offen: Import auf dem Server ausführen und die 41 Beiträge durchsehen; alte WordPress-Seiten bei Bedarf von Hand umleiten.'],
        '6.10' => ['title' => 'E-Mail-Versand über Brevo SMTP – Phase 1', 'status' => 'partial', 'note' => 'Mails, Testumleitung und datensparsame Inhalte fertig. Offen: SMTP-Zugang, Domain-Authentifizierung, Versand über Warteschlange.'],
        '6.11' => ['title' => 'Bezirke- und Stämme-Verzeichnis mit Kartenansicht – Phase 1', 'status' => 'partial', 'note' => 'Karte, Liste, Filter und Backend fertig; 68 Stämme erfasst. Offen: Daten von den Stämmen bestätigen lassen (Links, Biber-Gruppen).'],
        '7' => ['title' => 'Design und UI', 'status' => 'done', 'note' => ''],
        '7.1' => ['title' => 'Layout (Anlehnung an bistum-trier.de)', 'status' => 'done', 'note' => 'Startseite und Navigation nach dem Vorbild bistum-trier.de.'],
        '7.2' => ['title' => 'Corporate Identity (DPSG-Bundesebene)', 'status' => 'done', 'note' => 'Farben, Schriften und Maskottchen nach CD-Leitfaden; Biber-Farbe vorläufig.'],
        '7.3' => ['title' => 'Barrierefreiheit', 'status' => 'partial', 'note' => 'Grundlagen umgesetzt (Kontraste, Tastatur, Formulare). Barrierefreiheitstest steht aus.'],
        '8' => ['title' => 'Datenmodell (Skizze)', 'status' => 'partial', 'note' => ''],
        '8.1' => ['title' => 'ER-Diagramm', 'status' => 'info', 'note' => ''],
        '8.2' => ['title' => 'Tabellen (Auszug)', 'status' => 'partial', 'note' => 'Kern-Tabellen vorhanden. Weiterleitungen und Importläufe dazugekommen. Offen: Versionen, Einwilligungstexte.'],
        '8.3' => ['title' => 'Datenschutz im Datenmodell', 'status' => 'done', 'note' => 'Verschlüsselte Zusatzdaten, Löschdatum je Anmeldung, keine Newsletter-Adressen im CMS.'],
        '9' => ['title' => 'KDG-Konformität', 'status' => 'partial', 'note' => ''],
        '9.1' => ['title' => 'Rechtsgrundlagen und Grundsätze', 'status' => 'partial', 'note' => 'In den Formularen umgesetzt; Abstimmung mit der/dem Datenschutzbeauftragten steht aus.'],
        '9.2' => ['title' => 'Maßnahmenkatalog', 'status' => 'partial', 'note' => 'Technische Maßnahmen weitgehend fertig. Offen: Auskunft/Löschung pro Person im Backend, VVT, DSFA, AV-Verträge.'],
        '9.3' => ['title' => 'Aufbewahrung / Löschung (Standardwerte, konfigurierbar)', 'status' => 'partial', 'note' => 'Löschfristen für Anmeldungen fertig (brauchen den Cronjob). Offen: Audit-Log nach 12 Monaten bereinigen.'],
        '9.4' => ['title' => 'Newsletter-Double-Opt-In', 'status' => 'done', 'note' => ''],
        '9.5' => ['title' => 'Consent / Cookies', 'status' => 'done', 'note' => ''],
        '10' => ['title' => 'Nichtfunktionale Anforderungen', 'status' => 'partial', 'note' => ''],
        '10.1' => ['title' => 'Sicherheit', 'status' => 'partial', 'note' => 'CSRF, HTML-Bereinigung, Rate-Limits, Upload-Schutz, Security-Header mit CSP und HSTS fertig. Offen: automatische Schwachstellenprüfung (composer audit/Dependabot).'],
        '10.2' => ['title' => 'Betrieb und Wartung', 'status' => 'partial', 'note' => 'Backups fertig: täglich, verschlüsselt, 14 Tage, optional extern per WebDAV, wöchentlicher Wiederherstellungstest; Fehlerprotokoll im Deploy-Log. Offen: Monitoring, Betriebs- und Redaktionshandbuch.'],
        '10.3' => ['title' => 'Performance und Verfügbarkeit', 'status' => 'partial', 'note' => 'Caching, Lazy-Loading und WebP in mehreren Größen da. Offen: Lighthouse-Messung.'],
        '10.4' => ['title' => 'Kompatibilität', 'status' => 'done', 'note' => 'Lesen und Anmelden funktionieren ohne JavaScript.'],
        '10.5' => ['title' => 'Wartbarkeit', 'status' => 'partial', 'note' => 'Automatisierte Tests laufen bei jedem Deploy. Offen: Larastan und Pint in CI, ADRs.'],
        '11' => ['title' => 'API-Übersicht', 'status' => 'partial', 'note' => ''],
        '11.1' => ['title' => 'Öffentliche Endpunkte (Web/JSON)', 'status' => 'partial', 'note' => 'Fast alle Endpunkte da. Offen: Kontaktformular, RSS-Feed.'],
        '11.2' => ['title' => 'Admin-/Interne Endpunkte', 'status' => 'partial', 'note' => 'Offen: Entra-Verzeichnissuche. Instagram läuft als Aktion im Blog-Editor statt als eigene URL.'],
        '11.3' => ['title' => 'Ausgehende Integrationen', 'status' => 'partial', 'note' => 'Entra, Brevo-Newsletter, Nextcloud und Instagram angebunden (Instagram wartet auf das Konto). Offen: Graph, Brevo-SMTP-Zugang.'],
        '11.4' => ['title' => 'Geplante Scheduler-Jobs', 'status' => 'partial', 'note' => 'Kalender-Sync, Löschjobs, Instagram-Token-Erneuerung, Backup und Wiederherstellungstest eingeplant. Offen: Audit-Bereinigung.'],
        '12' => ['title' => 'Projektplan und Phasen', 'status' => 'partial', 'note' => ''],
        '13' => ['title' => 'Abnahmekriterien (Auszug)', 'status' => 'partial', 'note' => ''],
        '14' => ['title' => 'Risiken', 'status' => 'info', 'note' => ''],
        '15' => ['title' => 'Offene Punkte / Klärungsbedarf', 'status' => 'partial', 'note' => 'Siehe „Deine offenen Punkte“ oben.'],
        '16' => ['title' => 'Anhang: Glossar', 'status' => 'info', 'note' => ''],
    ],

    /*
    | Rückmeldungen außerhalb des Pflichtenhefts, je Meilenstein. Status wie
    | oben; 'note' = Einschätzung/Empfehlung.
    */
    'feedback' => [
        'Talia' => [
            ['status' => 'open', 'title' => 'Aktuelles weiter nach vorne', 'note' => 'Empfehlung: im Menü direkt hinter „Start“ und auf der Startseite die Meldungen vor den Begrüßungstext. Funktioniert nur, wenn regelmäßig neue Beiträge kommen – sonst steht ganz oben Veraltetes.'],
            ['status' => 'open', 'title' => 'Suche', 'note' => 'Im Pflichtenheft als Soll vorgesehen (F-CMS-07). Empfehlung: einfache Suche über Seiten, Beiträge, Veranstaltungen und Stämme jetzt bauen (Lupe im Kopf); bessere Trefferreihenfolge mit dem Umzug auf MySQL.'],
            ['status' => 'done', 'title' => 'Footer ohne Wiederholung des Menüs', 'note' => 'Umgesetzt: Footer nur noch mit Kontakt, Service, „DPSG im Netz“ und Georgsbrief-Anmeldung.'],
            ['status' => 'open', 'title' => 'Weniger Beige', 'note' => 'Die Farben sind die des DPSG-CD (Leitfaden April 2026); Beige ist dort für Flächen und Fußzeile vorgesehen, aber kein Muss. Empfehlung: Footer in DPSG-Blau, Infoboxen hellgrau/weiß, Beige nur noch sparsam.'],
            ['status' => 'done', 'title' => 'Diözesan-Logo größer', 'note' => 'Umgesetzt: Logo im Kopf 1,4-fach (Desktop). Für noch größer und gestochen scharf: Logo als SVG/PDF aus dem Diözesanbüro.'],
            ['status' => 'done', 'title' => 'Inklusions-Lilie für den Fachbereich Inklusion', 'note' => 'Umgesetzt: Inklusions-Lilie von dpsg.de (für die Arbeit in der DPSG frei verfügbar) im Kopf der Inklusionsseite und auf der Fachbereichsübersicht.'],
        ],
    ],

    // Abnahmekriterien (Abschnitt 13) einzeln
    'acceptance' => [
        ['done', 'Entra-Login mit Rechten nach Rolle'],
        ['partial', 'Notfall-Login mit 2FA und Alarmmail – Account fehlt noch'],
        ['partial', 'bolivienpartnerschaft.de ohne URL-Wechsel – DNS fehlt noch'],
        ['partial', 'Newsletter nur nach Bestätigung, keine Adresse im CMS – Brevo-Zugang fehlt'],
        ['partial', 'Unbestätigte Anmeldungen nach 48 h gelöscht – braucht den Cronjob'],
        ['done', 'XLSX-Export mit konfigurierten Spalten, Formelschutz und Audit'],
        ['done', 'Ohne Einwilligung keine Anfragen an Drittanbieter'],
        ['done', 'Nextcloud-Ausfall ohne Fehler im Frontend'],
        ['done', 'Stufenfarben inkl. Biber kontrastkonform'],
        ['partial', 'Wiederherstellung aus Backup getestet – automatischer Test wöchentlich, sobald Backups eingeschaltet sind'],
        ['done', 'Stufenadmin sieht nur die eigene Stufe (Negativtests)'],
        ['done', 'Autor:innen bearbeiten nur eigene News, Veröffentlichung nach Freigabe'],
        ['done', 'Verwaltung ohne Systemeinstellungen und Tokens'],
        ['open', 'Transaktionsmails über Brevo mit SPF/DKIM/DMARC'],
        ['partial', 'WordPress-Import wiederholbar mit 301-Weiterleitungen – fertig und getestet, Import auf dem Server steht aus'],
        ['partial', 'M365-Konto suchen und Rolle zuweisen – Verzeichnissuche fehlt'],
        ['partial', '.env/vendor/storage nicht erreichbar; Produktiv-Deploy mit Freigabe fehlt'],
        ['done', 'Stämme-Karte ohne externe Kacheln, Liste ohne JavaScript'],
    ],
];
