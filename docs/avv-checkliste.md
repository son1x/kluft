# Checkliste Auftragsverarbeitung (AVV)

Für jeden Dienst, der in unserem Auftrag personenbezogene Daten verarbeitet,
braucht es einen Vertrag zur Auftragsverarbeitung (§ 29 KDG bzw. Art. 28
DSGVO). Eigene Dienste (selbst betrieben) brauchen keinen, gehören aber ins
Verzeichnis der Verarbeitungstätigkeiten. **Keine Rechtsberatung.**

Abhaken, Ablageort und Datum eintragen; einmal im Jahr zusammen mit der
Rechte-Prüfung (Verwaltung → Rechte-Prüfung) durchgehen.

| Dienst | Wofür in Kluft | Daten | Nötig, wenn | AVV vorhanden | Abgelegt unter | Geprüft am |
|---|---|---|---|---|---|---|
| Webhoster (z. B. netcup) | Server, Datenbank, Backups | alle | immer | ☐ | | |
| Mailversand (`MAIL_*`, z. B. Brevo SMTP oder M365) | alle Mails | Empfänger, Inhalt | immer | ☐ | | |
| Microsoft 365 / Entra ID | Anmeldung im Backend | Name, E-Mail, Konto-ID | Entra-Anmeldung aktiv | ☐ (Rahmenvertrag Bistum?) | | |
| Brevo (Newsletter) | Newsletter-Anmeldung | E-Mail | Newsletter-Formular | ☐ | | |
| ntfy.sh | Push aufs Handy | Thema, Titel, Platzzahlen | Push aktiv **ohne** eigenen Server | ☐ (oder eigener Server, `docs/ntfy-server.md`) | | |
| Meta (Instagram) | Feed, Teilen | veröffentlichte Bilder/Texte | Instagram verbunden | ☐ (Nutzungsbedingungen) | | |
| Meta (WhatsApp Cloud API) | WhatsApp-Newsletter | Telefonnummern | WhatsApp eingeschaltet | ☐ + Drittland (USA) prüfen | | |
| DPSG Bundesamt (NaMi) | Mitgliederabgleich, Datenabgleich | Name, Geburtsjahr | Funktion eingeschaltet | ☐ (Verbandsregelung) | | |
| Nextcloud (eigene oder gehostet) | Kalender, Zuschuss-Ablage, Backups per WebDAV | Termine, Zuschussunterlagen, Backups | eingerichtet | ☐ nur wenn gehostet | | |
| Jitsi (eigene oder gehostet) | Videokonferenzen | Teilnahme | Funktion eingeschaltet | ☐ nur wenn gehostet | | |
| Backup-Ziel extern (WebDAV) | Kopie der Backups | alle (verschlüsselt) | eingerichtet | ☐ | | |

## Je Vertrag prüfen

- Gegenstand, Dauer, Art der Daten und Betroffenen stimmen mit der Tabelle.
- Technische und organisatorische Maßnahmen (TOM) liegen bei.
- Unterauftragnehmer und Serverstandort (EU?); bei Drittland:
  Angemessenheitsbeschluss bzw. Standardvertragsklauseln.
- Löschung bzw. Rückgabe nach Vertragsende.
- Kirchliche Stellen: Hinweis auf KDG bzw. Zustimmung der oder des
  Diözesandatenschutzbeauftragten, wo verlangt.

## Verzeichnis der Verarbeitungstätigkeiten

Die Tabellen in `docs/datenschutz-ueberblick.md` (Abschnitt 2 und 5) sind
eine gute Grundlage: je Zeile eine Verarbeitungstätigkeit mit Zweck,
Datenarten, Zugriff und Löschfrist.
