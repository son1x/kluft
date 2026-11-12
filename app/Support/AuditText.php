<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Lesbare Texte und Bereiche für Einträge des Audit-Logs (Verwaltung →
 * Protokoll). Unbekannte Aktionen erscheinen unverändert.
 */
class AuditText
{
    /** Bereich => Präfixe der Aktion (für Filter und Anzeige). */
    public const BEREICHE = [
        'anmeldung' => ['label' => 'Anmeldungen am Backend', 'prefixe' => ['login.']],
        'konten' => ['label' => 'Konten und Rechte', 'prefixe' => ['user.', 'role.', 'entra.']],
        'veranstaltungen' => ['label' => 'Veranstaltungen und Teilnehmende', 'prefixe' => ['event.', 'registration.', 'registrations.', 'veranstaltung.']],
        'datenschutz' => ['label' => 'Datenschutz', 'prefixe' => ['privacy.']],
        'funktionen' => ['label' => 'Funktionen, Fußzeile und Updates', 'prefixe' => ['feature.', 'update.', 'fusszeile.']],
        'mein_konto' => ['label' => 'Mein Konto (NaMi, Push)', 'prefixe' => ['nami.', 'push.']],
        'integrationen' => ['label' => 'Integrationen (Instagram, WhatsApp-Verbindung)', 'prefixe' => ['integration.', 'instagram.']],
        'whatsapp' => ['label' => 'WhatsApp-Newsletter', 'prefixe' => ['whatsapp.']],
        'newsletter' => ['label' => 'Newsletter und Kontaktformular', 'prefixe' => ['newsletter.', 'kontakt:']],
        'system' => ['label' => 'Mails und Inhalte', 'prefixe' => ['mail.', 'georgsbote.']],
    ];

    private const FEST = [
        'login.entra' => 'Anmeldung mit Microsoft 365',
        'nachricht.neu' => 'Nachricht wieder geöffnet',
        'nachricht.in_bearbeitung' => 'Nachricht in Bearbeitung',
        'nachricht.erledigt' => 'Nachricht erledigt',
        'nachricht.geloescht' => 'Nachricht gelöscht',
        'login.entra.blocked_breakglass_email' => 'Anmeldung mit Microsoft 365 abgelehnt (Adresse des Notfallzugangs)',
        'login.breakglass' => 'Anmeldung mit dem Notfallzugang',
        'login.breakglass.failed' => 'Notfallzugang: Anmeldung fehlgeschlagen',
        'login.code' => 'Anmeldung mit E-Mail-Code',
        'update.angefordert' => 'Kluft-Update angefordert',
        'update.installiert' => 'Kluft-Update installiert',
        'update.fehlgeschlagen' => 'Kluft-Update fehlgeschlagen',
        'login.code.angefordert' => 'Anmeldecode per E-Mail angefordert',
        'login.code.failed' => 'Anmeldung mit E-Mail-Code fehlgeschlagen',
        'user.nami_check' => 'Mitgliedschaft in NaMi geprüft',
        'entra.verzeichnis.verbunden' => 'Verzeichnissuche (Microsoft 365) verbunden',
        'entra.verzeichnis.fehlgeschlagen' => 'Verzeichnissuche (Microsoft 365) fehlgeschlagen',
        'entra.verzeichnis.anderes_konto' => 'Verzeichnissuche: anderes Microsoft-Konto gewählt, abgelehnt',
        'event.export' => 'Teilnehmendenliste exportiert',
        'event.kuechenliste' => 'Küchenliste heruntergeladen',
        'event.export.sensibel' => 'Teilnehmendenliste exportiert (mit sensiblen Angaben)',
        'event.liste' => 'Liste für unterwegs (PDF) abgerufen',
        'event.member_check' => 'Mitgliederabgleich gestartet',
        'registration.manual' => 'Teilnehmende*n von Hand eingetragen',
        'registration.promote' => 'Von der Warteliste nachgerückt',
        'registration.beitrag' => 'Teilnahmebeitrag festgelegt',
        'zahlung.angelegt' => 'Forderung angelegt',
        'zahlung.eingang' => 'Zahlungseingang erfasst',
        'zahlung.erstattung' => 'Erstattung erfasst',
        'zahlung.storniert' => 'Forderung storniert',
        'zahlung.konto' => 'Bankverbindung geändert',
        'registration.moveToWaitlist' => 'Auf die Warteliste gesetzt',
        'registration.cancel' => 'Anmeldung storniert',
        'registration.member_decision' => 'Mitgliedschaft von Hand entschieden',
        'registration.certificate.approve' => 'Teilnahmebescheinigung freigegeben',
        'registration.certificate.revoke' => 'Freigabe der Teilnahmebescheinigung zurückgenommen',
        'registration.document.upload' => 'Dokument zur Anmeldung hochgeladen',
        'registration.document.delete' => 'Dokument zur Anmeldung gelöscht',
        'privacy.search' => 'Datenschutz: nach einer E-Mail-Adresse gesucht',
        'privacy.search.whatsapp' => 'Datenschutz: nach einer WhatsApp-Nummer gesucht',
        'privacy.export' => 'Datenschutz: Auskunft heruntergeladen',
        'nami.zugang.gespeichert' => 'NaMi-Zugang im Profil gespeichert',
        'nami.zugang.geloescht' => 'NaMi-Zugang im Profil gelöscht',
        'push.eingeschaltet' => 'Push-Nachrichten eingeschaltet',
        'push.ausgeschaltet' => 'Push-Nachrichten ausgeschaltet',
        'push.neues_thema' => 'Push-Nachrichten: neues Thema erzeugt',
        'georgsbote.alle_freigegeben' => 'Alle Zeitschriften-Ausgaben mit PDF als geprüft markiert und veröffentlicht (Auftrag Projektleitung)',
        'fusszeile.gespeichert' => 'Fußzeile angepasst',
        'wartung.an' => 'Wartungsmodus eingeschaltet',
        'anmeldungen.sammeladresse' => 'Sammeladresse für Anmeldungen geändert',
        'wartung.aus' => 'Wartungsmodus ausgeschaltet',
        'event.nachricht' => 'Nachricht an die Teilnehmenden einer Veranstaltung',
        'event.erinnerung' => 'Erinnerung an die Teilnehmenden einer Veranstaltung',
        'backup.angefordert' => 'Backup im Backend angefordert',
        'backup.einspielen' => 'Backup einspielen angefordert',
        'backup.uebertragen' => 'Backup auf andere Website übertragen angefordert',
        'fusszeile.zurueckgesetzt' => 'Fußzeile auf Standard zurückgesetzt',
        'integration.instagram.connect' => 'Instagram verbunden',
        'umfrage.angelegt' => 'Umfrage angelegt',
        'mail.allowlist.hinzu' => 'Test-Empfänger*in freigeschaltet',
        'mail.allowlist.entfernt' => 'Test-Empfänger*in entfernt',
        'umfrage.geaendert' => 'Umfrage geändert',
        'umfrage.kopiert' => 'Umfrage kopiert',
        'umfrage.vorlage' => 'Umfrage als Vorlage gespeichert',
        'umfrage.antwort' => 'Umfrage beantwortet',
        'umfrage.export.pdf' => 'Umfrage-Auswertung als PDF',
        'umfrage.export.excel' => 'Umfrage-Antworten als Excel',
        'umfrage.gewinnspiel.ausgelost' => 'Gewinnspiel ausgelost',
        'instagram.feed.konto' => 'Instagram-Konto für die Einblendung gespeichert',
        'instagram.feed.abruf' => 'Instagram-Beiträge abgerufen',
        'integration.instagram.disconnect' => 'Instagram getrennt',
        'integration.whatsapp.gespeichert' => 'WhatsApp-Verbindung gespeichert',
        'integration.whatsapp.getrennt' => 'WhatsApp-Verbindung getrennt',
        'instagram.publish' => 'Beitrag auf Instagram geteilt',
        'whatsapp.anmeldung.angefragt' => 'WhatsApp-Newsletter: Anmeldung angefragt',
        'whatsapp.abonnent.geloescht' => 'WhatsApp-Newsletter: Abonnent*in gelöscht',
        'whatsapp.aussendung.zur_freigabe' => 'WhatsApp-Aussendung zur Freigabe gegeben',
        'whatsapp.aussendung.freigegeben' => 'WhatsApp-Aussendung freigegeben',
        'whatsapp.aussendung.zurueck' => 'WhatsApp-Aussendung zurück in Bearbeitung',
        'whatsapp.aussendung.gestartet' => 'WhatsApp-Aussendung gestartet',
        'whatsapp.aussendung.angehalten' => 'WhatsApp-Aussendung angehalten',
        'whatsapp.aussendung.fortgesetzt' => 'WhatsApp-Aussendung fortgesetzt',
        'whatsapp.aussendung.abgebrochen' => 'WhatsApp-Aussendung abgebrochen',
        'mail.retry' => 'Fehlgeschlagene Mail erneut versucht',
        'mail.retry_all' => 'Alle fehlgeschlagenen Mails erneut versucht',
        'mail.forget' => 'Fehlgeschlagene Mail verworfen',
    ];

    private const STATUS = ['active' => 'aktiv', 'disabled' => 'gesperrt', 'pending' => 'wartet auf Freischaltung'];

    public static function text(string $action): string
    {
        if (isset(self::FEST[$action])) {
            return self::FEST[$action];
        }
        [$kopf, $rest] = array_pad(explode(':', $action, 2), 2, '');
        $teile = $rest === '' ? [] : explode('+', $rest);

        return match ($kopf) {
            'user.status' => 'Kontostatus geändert: '.(self::STATUS[$rest] ?? $rest),
            'role.granted' => 'Recht vergeben: '.self::rolle($rest),
            'role.revoked' => 'Recht entzogen: '.self::rolle($rest),
            'praevention.geloescht' => 'Präventions-Nachweise gelöscht (ein Jahr nach Ablauf): '.$rest,
            'auslagen.geloescht' => 'Auslagen gelöscht (Aufbewahrungsfrist abgelaufen): '.$rest,
            'material.geloescht' => 'Material-Reservierungen gelöscht (6 Monate nach Rückgabe): '.$rest,
            'rolle.rechte' => 'Rolle geändert: '.$rest,
            'rolle.geloescht' => 'Rolle gelöscht: '.$rest,
            'role.changed' => 'Recht geändert: '.self::rolle($rest),
            'role.expired' => 'Recht abgelaufen: '.self::rolle($rest),
            'feature.an' => 'Funktion eingeschaltet: '.(Features::KATALOG[$rest]['label'] ?? $rest),
            'feature.aus' => 'Funktion ausgeschaltet: '.(Features::KATALOG[$rest]['label'] ?? $rest),
            'event.sensibel_geloescht' => 'Sensible Angaben gelöscht (Frist nach der Veranstaltung): '.$rest.' Anmeldungen',
            'konto.vorwarnung' => 'Vorwarnung: Konto wird mangels Anmeldung bald gesperrt',
            'konto.inaktiv_gesperrt' => 'Konto gesperrt: ein Jahr nicht angemeldet',
            'backup.download' => 'Backup heruntergeladen: '.$rest,
            'registration.geloescht' => 'Anmeldungen endgültig gelöscht: '.$rest,
            'registration.bearbeitet' => 'Angaben einer Anmeldung bearbeitet (Nr. '.$rest.')',
            'vor_ort.zahlung' => 'Vor Ort: Zahlung kassiert (Anmeldung Nr. '.$rest.')',
            'vor_ort.angaben' => 'Vor Ort: Angaben korrigiert (Anmeldung Nr. '.$rest.')',
            'vor_ort.zusatz' => 'Vor Ort: Zusatzinfo geändert (Anmeldung Nr. '.$rest.')',
            'rechte.geprueft' => 'Rechte-Prüfung abgeschlossen: '.$rest.' Personen mit sensiblen Rechten',
            'registrations.purge' => 'Automatisch gelöscht: '.($teile[0] ?? 0).' unbestätigte und '.($teile[1] ?? 0).' abgelaufene Anmeldungen',
            'privacy.delete' => count($teile) >= 4
                ? 'Datenschutz: Daten gelöscht ('.$teile[0].' Anmeldungen, '.$teile[1].' Personen, '.$teile[2].' Nachrichten, '.$teile[3].' Mails)'
                : 'Datenschutz: Daten gelöscht ('.($teile[0] ?? 0).' Anmeldungen, '.($teile[1] ?? 0).' Personen, '.($teile[2] ?? 0).' Mails)',
            'privacy.delete.whatsapp' => 'Datenschutz: WhatsApp-Daten gelöscht ('.($teile[0] ?? 0).' Abonnent*innen, '.($teile[1] ?? 0).' Sperreinträge)',
            'umfrage.eingeladen' => 'Zur Umfrage eingeladen: '.$rest.' Teilnehmende',
            'umfrage.gewinnspiel.geloescht' => 'Gewinnspiel-Einträge gelöscht: '.$rest,
            'georgsbote.bulk_upload' => $rest.' Zeitschriften-Ausgaben hochgeladen',
            'kontakt' => 'Kontaktformular '.(str_starts_with($rest, 'gesendet') ? 'gesendet' : 'fehlgeschlagen').(str_contains($rest, ':') ? ' (Thema: '.Str::after($rest, ':').')' : ''),
            'veranstaltung.frage' => 'Frage zu einer Veranstaltung '.($rest === 'gesendet' ? 'gesendet' : 'fehlgeschlagen'),
            'newsletter.doi' => 'Newsletter-Anmeldung '.(str_starts_with($rest, 'angefragt') ? 'angefragt' : 'fehlgeschlagen'),
            'whatsapp.anmeldung.bestaetigt' => 'WhatsApp-Newsletter: Anmeldung bestätigt ('.$rest.')',
            'whatsapp.abmeldung' => 'WhatsApp-Newsletter: Abmeldung ('.$rest.')',
            'whatsapp.vorlage.eingereicht' => 'WhatsApp-Vorlage eingereicht: '.$rest,
            default => $action,
        };
    }

    public static function bereich(string $action): ?string
    {
        foreach (self::BEREICHE as $key => $bereich) {
            foreach ($bereich['prefixe'] as $prefix) {
                if (str_starts_with($action, $prefix)) {
                    return $key;
                }
            }
        }

        return null;
    }

    private static function rolle(string $key): string
    {
        return RoleCatalog::all()[$key] ?? $key;
    }
}
