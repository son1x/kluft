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
        'funktionen' => ['label' => 'Funktionen', 'prefixe' => ['feature.']],
        'mein_konto' => ['label' => 'Mein Konto (NaMi, Push)', 'prefixe' => ['nami.', 'push.']],
        'integrationen' => ['label' => 'Integrationen (Instagram, WhatsApp-Verbindung)', 'prefixe' => ['integration.', 'instagram.']],
        'whatsapp' => ['label' => 'WhatsApp-Newsletter', 'prefixe' => ['whatsapp.']],
        'newsletter' => ['label' => 'Newsletter und Kontaktformular', 'prefixe' => ['newsletter.', 'kontakt:']],
        'system' => ['label' => 'Mails und Inhalte', 'prefixe' => ['mail.', 'georgsbote.']],
    ];

    private const FEST = [
        'login.entra' => 'Anmeldung mit Microsoft 365',
        'login.entra.blocked_breakglass_email' => 'Anmeldung mit Microsoft 365 abgelehnt (Adresse des Notfallzugangs)',
        'login.breakglass' => 'Anmeldung mit dem Notfallzugang',
        'login.breakglass.failed' => 'Notfallzugang: Anmeldung fehlgeschlagen',
        'login.code' => 'Anmeldung mit E-Mail-Code',
        'login.code.angefordert' => 'Anmeldecode per E-Mail angefordert',
        'login.code.failed' => 'Anmeldung mit E-Mail-Code fehlgeschlagen',
        'user.nami_check' => 'Mitgliedschaft in NaMi geprüft',
        'entra.verzeichnis.verbunden' => 'Verzeichnissuche (Microsoft 365) verbunden',
        'entra.verzeichnis.fehlgeschlagen' => 'Verzeichnissuche (Microsoft 365) fehlgeschlagen',
        'entra.verzeichnis.anderes_konto' => 'Verzeichnissuche: anderes Microsoft-Konto gewählt, abgelehnt',
        'event.export' => 'Teilnehmendenliste exportiert',
        'event.member_check' => 'Mitgliederabgleich gestartet',
        'registration.manual' => 'Teilnehmende*n von Hand eingetragen',
        'registration.promote' => 'Von der Warteliste nachgerückt',
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
        'integration.instagram.connect' => 'Instagram verbunden',
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
            'role.changed' => 'Recht geändert: '.self::rolle($rest),
            'feature.an' => 'Funktion eingeschaltet: '.(Features::KATALOG[$rest]['label'] ?? $rest),
            'feature.aus' => 'Funktion ausgeschaltet: '.(Features::KATALOG[$rest]['label'] ?? $rest),
            'registrations.purge' => 'Automatisch gelöscht: '.($teile[0] ?? 0).' unbestätigte und '.($teile[1] ?? 0).' abgelaufene Anmeldungen',
            'privacy.delete' => 'Datenschutz: Daten gelöscht ('.($teile[0] ?? 0).' Anmeldungen, '.($teile[1] ?? 0).' Personen, '.($teile[2] ?? 0).' Mails)',
            'privacy.delete.whatsapp' => 'Datenschutz: WhatsApp-Daten gelöscht ('.($teile[0] ?? 0).' Abonnent*innen, '.($teile[1] ?? 0).' Sperreinträge)',
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
