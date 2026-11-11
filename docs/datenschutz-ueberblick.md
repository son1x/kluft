# Datenschutz: Risiken und Einstellungen (Überblick)

Stand 11.11.2026, Code-Stand nach PR #53. Fokus DV Trier, gilt sinngemäß für
alle Kluft-Websites. Das ist eine technische Bestandsaufnahme, **keine
Rechtsberatung**. Die Datenschutzerklärung und die Verträge (AVV) gehören zur
oder zum Datenschutzbeauftragten des Verbands bzw. Bistums.

Siehe auch: `docs/rollen.md` (wer darf was), `docs/veranstaltungen.md`,
`docs/praevention.md`, `docs/auslagen.md`, `docs/materialverleih.md`,
`docs/nachrichten.md`, `docs/umfragen.md`, `docs/aufrufe.md`.

---

## 1. Kurzfassung: die wichtigsten Punkte

| # | Risiko | Einschätzung | Was hilft |
|---|---|---|---|
| 1 | **Breiter Zugriff auf Teilnehmerdaten** durch Verwaltung, Bildungsreferent*innen und Admins (alle Veranstaltungen, alle Personen) | hoch, weil viele Personen | wenige Konten mit diesen Rollen, „gültig bis“ setzen, Protokoll prüfen (Abschnitt 3) |
| 2 | **Gesundheits- und Ernährungsangaben** in eigenen Formularfeldern (z. B. „Allergien“) sind besondere Kategorien (Art. 9 DSGVO) | hoch | nur abfragen, was nötig ist; Löschfrist kurz halten; Hinweis im Feld; nicht in Exporte/PDF, wenn nicht nötig (Abschnitt 4.2) |
| 3 | **Exporte und Ausdrucke** (Excel, „Liste für unterwegs“) verlassen das System | mittel | werden protokolliert; Liste mit Hinweis „vertraulich, vernichten“; Geräte der Leitung |
| 4 | **Testsystem mit echten Daten** (Backup „auf andere Website übertragen“, vorab freigegebene Anmeldungen) | mittel | Testsystem geschlossen halten; nach Tests Daten löschen bzw. nicht übertragen (Abschnitt 6) |
| 5 | **Fotos von Minderjährigen** (Galerie, Mediathek, Instagram) | mittel | Einwilligung ist Pflicht-Haken; Vier-Augen-Freigabe; Einwilligungen tatsächlich einholen und ablegen |
| 6 | **Externe Dienste** (Mailversand, Microsoft Entra, Brevo-Newsletter, NaMi, ntfy, Instagram/Meta, ggf. WhatsApp) | mittel | AVV prüfen; nur nötige Funktionen einschalten (Abschnitt 5) |
| 7 | **Admin-Konten** sehen alles inkl. Backups und Protokoll | mittel | wenige Admins; Anmeldung über Microsoft 365 mit MFA; Notfallzugang offline verwahren |
| 8 | **Backups** enthalten alle Daten, gelöschte Daten bleiben bis zu 14 Tage darin | gering bis mittel | Backups verschlüsselt (Passwort), 14 Tage; Passwort sicher verwahren |
| 9 | **Lange Aufbewahrung je Veranstaltung** (bis 365 Tage) oder Zuschuss-Nachweise | gering bis mittel | Standard 90 Tage beibehalten; für Zuschüsse prüfen, was wirklich aufbewahrt werden muss |
| 10 | **Präventionsdaten** (Führungszeugnis eingesehen) | gering (nur Daten, kein Inhalt) | Zugriff nur Vorstand/Verwaltung; Löschung 1 Jahr nach Ablauf; Regel des Bistums prüfen |

---

## 2. Welche Daten wo liegen

Legende Verschlüsselung: **ja** = in der Datenbank verschlüsselt (Laravel
`encrypted`, Schlüssel `APP_KEY`), **nein** = Klartext in der Datenbank
(geschützt durch Server/Zugang, aber in Backups und bei DB-Zugriff lesbar).

| Daten | Wer sieht sie im Backend | Verschlüsselt | Löschung (automatisch) | Einstellung |
|---|---|---|---|---|
| **Anmeldungen**: Name, E-Mail, Geburtsjahr, Sorgeberechtigte | Redaktion (Verwaltung, BR, Admin) alle; DL im eigenen Bereich (DV: nur lesen); Vorstand nur Anzahlen | Grunddaten **nein**, Zusatzangaben aus dem Baukasten **ja** | unbestätigt nach 48 h; sonst *Löschfrist der Veranstaltung* (Standard 90 Tage nach Ende, 1–365) | je Veranstaltung „Anmeldedaten löschen nach … Tagen“ |
| Hochgeladene Dokumente zur Anmeldung (Rechnungen, Bescheinigungen) | wie Anmeldungen | Ablage privat (nicht öffentlich erreichbar) | mit der Anmeldung | – |
| **Nachrichten** (Kontaktformular, Fragen zu Veranstaltungen) | Redaktion; Stufen-/Sektionsadmins ihre | Nachrichtentext **ja** | nach 6 Monaten (`NACHRICHTEN_AUFBEWAHRUNG_MONATE`) | Funktion „Nachrichten-Eingang“ |
| **Benutzerkonten** (Name, E-Mail, Rollen, letzte Anmeldung) | Verwaltung, Admins; BR/Stufenadmins nur Autor:innen | 2FA-Geheimnis, NaMi-Zugang, Push-Thema **ja** | nicht automatisch | Rollen mit „gültig bis“ |
| **Personen** (Vorstellung auf der Website) | Redaktion | Zuschuss-Angaben (Anschrift, Juleica) **ja** | nicht automatisch | Einwilligung „consent“ je Person |
| **Präventions-Nachweise** (eFZ eingesehen am, Schulung, Juleica) | Vorstand, Verwaltung, Admins (nicht BR) | **nein** (nur Daten, kein Dokument) | 1 Jahr nach Ablauf | Funktion „Präventions-Nachweise“ (aus) |
| **Auslagen** (Betrag, Belege, IBAN) | Einreichende selbst; Kasse/Verwaltung | IBAN **ja**; Belege privat abgelegt | IBAN nach Auszahlung; Auslage + Belege nach 10 Jahren; abgelehnt nach 1 Jahr | Funktion „Auslagen erstatten“ (aus) |
| **Zahlungen** (Teilnahmebeiträge, Sammelbestellung) | Kasse/Verwaltung | **nein** | Sammelbestellung: anonymisiert nach 6 Monaten | Funktion „Zahlungen“ |
| **Materialverleih** (Name, E-Mail, Telefon, Gruppe) | Materialwart*in, Redaktion | **nein** | 6 Monate nach Rückgabe | Funktionen „Materialverleih“ (+ „über die Website“) |
| **Umfragen** (Antworten; bei nicht-anonymen Name/E-Mail) | wer Umfragen anlegen darf | Antworten **ja** | je Umfrage „Aufbewahrung … Monate“; Gewinnspiel 3 Monate | je Umfrage anonym/nicht anonym |
| **Fotos zur Veranstaltung** | vor Freigabe: wer Teilnehmende verwalten darf; danach öffentlich | vor Freigabe privat abgelegt | keine | Vier-Augen-Freigabe |
| **Mediathek** (Bilder mit Personen) | alle mit Backend-Zugang | öffentlich | keine | Haken „Einwilligung liegt vor“ |
| **Protokoll (Audit-Log)** | Admins | – (IP gekürzt) | nach 12 Monaten | – |
| **Seitenaufrufe** | Redaktion, Vorstand | – (kein Personenbezug, keine IP, kein Cookie) | nach 25 Monaten | Funktion „Seitenaufrufe zählen“ |
| **NaMi-Datenabgleich** (Meldungen Mitglieder/Eltern) | Verwaltung, Kasse | **ja** | bestätigte nach 6 Monaten, Abgleichzeilen nach 60 Tagen | Funktion (Stämme) |
| **WhatsApp-Newsletter** (Nummern) | WhatsApp-Admins, Verwaltung | Nummer **ja** | offene Anmeldungen 48 h, Zustellungen 30 Tage, Sperrliste 12 Monate | nur wenn eingeschaltet |
| **Videokonferenzen** (Einladungen) | wer sie anlegt, Redaktion | – | nach 12 Monaten | Funktion |
| **Mails in der Warteschlange** | Verwaltung (Mail-Warteschlange) | Inhalt **ja** | fehlgeschlagene nach 14 Tagen | – |
| **Backups** (alles) | Admins | Archiv mit Passwort | 14 Tage | `BACKUP_PASSWORD`, optional WebDAV |

**Auskunft und Löschung:** Verwaltung → Datenschutz sucht alles zu einer
E-Mail-Adresse (Anmeldungen, Personen, Nachrichten, Umfragen,
Sammelbestellungen, Datenmeldungen, Adressbuch, Prävention, Auslagen,
Materialverleih, wartende Mails) und erzeugt eine Auskunft (ZIP) oder löscht.
Auslagen bleiben wegen der steuerlichen Aufbewahrung, Benutzerkonten werden
bewusst nur deaktiviert. Nur Verwaltung und Admins.

---

## 3. Rollen und Zugriff auf personenbezogene Daten

| Rolle | Teilnehmende | Personen/Konten | Nachrichten | Kasse, Auslagen | Prävention | Datenschutz-Anfragen | Protokoll, Backups |
|---|---|---|---|---|---|---|---|
| Autor:in | – | – | – | eigene Auslagen | – | – | – |
| Stufen-/Sektionsadmin (DV: DL) | eigener Bereich, **nur lesen** | Autor:innen des Bereichs | des Bereichs | eigene Auslagen | – | – | – |
| Veranstaltungen bearbeiten – Bereich | eigener Bereich, verwalten | – | Fragen der eigenen Veranstaltungen | eigene | – | – | – |
| Vorstand | nur Anzahlen | – | – | eigene | **ja** | – | – |
| Bildungsreferent*in | **alle** | Personen; Konten nur Autor:innen | alle | eigene | – | – | – |
| Verwaltung | **alle** | alle (außer Admins) | alle | **ja** (DV) | **ja** | **ja** | – |
| Admin | **alle** | alle | alle | **ja** | **ja** | **ja** | **ja** |

**Risiken und Gegenmaßnahmen:**

- **Need-to-know:** Bildungsreferent*innen, Verwaltung und Admins sehen
  alle Anmeldungen. Das ist für das Tagesgeschäft gewollt, betrifft aber
  viele Datensätze. → Rollen sparsam vergeben, „gültig bis“ nutzen (Ende
  Amtszeit/Arbeitsvertrag), täglicher Lauf entfernt abgelaufene Rollen.
- **DL liest nur** (DV): Schreib- und Exportaktionen sind serverseitig
  gesperrt, nicht nur ausgeblendet. Exporte (Excel/PDF) machen damit nur
  Redaktion und „Veranstaltungen bearbeiten“.
- **Vergabe:** Admins bekommen bei weitreichenden Rollen (Verwaltung,
  Bildungsreferent*in, Kasse, WhatsApp, Admin) einen Sicherheitshinweis.
  Jede Vergabe/Entzug steht im Protokoll.
- **Konten ohne Zuständigkeit**: Personen, die nichts pflegen (z. B.
  Vorstand ohne Aufgaben), bekommen keine weitreichende Rolle (Empfehlung
  Autor:in oder kein Konto).
- **Anmeldung:** Microsoft 365 (Entra) mit der MFA-Richtlinie des Tenants;
  ohne M365 optional E-Mail-Code. Der Notfallzugang (Super-Admin, 2FA) ist
  offline zu verwahren.
- **Idee für später:** ein Rollen-Editor als Matrix („erweiterte Rechte“),
  damit Stämme und Bezirke Rollen selbst zuschneiden können. Wichtig dabei:
  Teilnehmerdaten, Kasse, Prävention und Datenschutz-Anfragen als eigene,
  bewusst zu vergebende Häkchen.

---

## 4. Veranstaltungen im Detail

### 4.1 Ablauf und Speicherdauer

- **Double-Opt-In:** Eine Anmeldung zählt erst nach Klick auf den Link in der
  Mail (48 h). Unbestätigte werden stündlich nach 48 h gelöscht.
- **Löschfrist je Veranstaltung:** „Anmeldedaten löschen nach … Tagen (ab
  Ende)“, Standard 90, erlaubt 1–365. Täglicher Lauf `registrations:purge`
  (03:00) löscht samt Dokumenten. Die Einwilligung im Formular nennt die Frist.
- **Familienanmeldung:** Weitere Personen übernehmen E-Mail, Zusatzangaben und
  die Einwilligung der ersten Person. → Die anmeldende Person (meist ein
  Elternteil) willigt für alle ein; der Formulartext muss das abdecken.
- **Minderjährige:** Unter 16 nur durch Personensorgeberechtigte (Pflicht-Haken
  mit Name). „Nur Erwachsene“ sperrt jüngere Geburtsjahre.
- **Zuschussanträge:** Formular fragt dann Anschrift, Geburtsdatum,
  Geschlecht ab (Hinweis im Formular). Zuschussgeber verlangen Nachweise zum
  Teil über Jahre. → **Prüfen:** Löschfrist dieser Veranstaltungen und ob
  Zuschussunterlagen außerhalb von Kluft (Nextcloud-Ablage) aufbewahrt werden.

### 4.2 Formularfelder (Baukasten)

- Standardfelder fragen nur Nötiges ab; Geburtsjahr statt -datum.
- Freitext „Anmerkungen“ trägt den Hinweis „keine Gesundheitsangaben“.
- **Eigene Felder** (bis 15) sind frei formulierbar. **Risiko:** Felder wie
  „Allergien“, „Medikamente“, „Unverträglichkeiten“ sind
  Gesundheitsdaten. → Nur wenn nötig, mit Hinweis, kurze Löschfrist; sie
  sind verschlüsselt gespeichert, erscheinen aber in Export, PDF und Details.
- Alle Zusatzangaben (`data`) sind verschlüsselt; Name, E-Mail und Geburtsjahr
  nicht (für Suche, Duplikat- und Mitgliederprüfung nötig).

### 4.3 Wer bekommt Daten aus der Anmeldung

| Weg | Inhalt | Empfänger |
|---|---|---|
| Bestätigungs-/Statusmails | Titel, Name, Status, Links (keine Zusatzangaben) | angemeldete Person |
| Info-Mails ans Team | Name, Stamm, Stand der Plätze, Link ins Backend | Sammeladresse (z. B. info@) und verantwortliche Person, je Veranstaltung abschaltbar |
| Push (ntfy) | **nur** Titel und Platzzahlen, keine Namen | wer Teilnehmende sehen darf und Push eingerichtet hat |
| Nachricht an Teilnehmende | Text der Leitung, je Person einzeln (Adressen unsichtbar) | Teilnehmende |
| Excel-Export / Liste für unterwegs (PDF) | alles bzw. gewählte Spalten | Gerät der Person; **protokolliert** |
| Mitgliederabgleich (NaMi) | Name, Geburtsjahr | NaMi (DPSG), nur wenn eingeschaltet |
| Teilnahmebeiträge | Name, Betrag, Verwendungszweck | Kasse; Mail mit GiroCode an die Person |

### 4.4 Fotos

- Hochladen nur mit Pflicht-Haken „Einwilligung der erkennbar Abgebildeten
  (bei Minderjährigen der Sorgeberechtigten)“.
- Bis zur Freigabe **privat** gespeichert, Vorschau nur für Berechtigte.
  Freigabe nur durch eine **zweite Person**. Ausblenden jederzeit.
- **Risiko:** Der Haken ist eine Selbstauskunft. → Einwilligungen bei der
  Anmeldung bzw. auf Papier einholen und ablegen; im Zweifel Bilder ohne
  erkennbare Gesichter. Freigegebene Fotos sind öffentlich und können
  kopiert werden; Löschen auf Wunsch entfernt sie samt Medium.
- „Auf Instagram teilen“ überträgt Bild und Text an Meta.

### 4.5 Vorab freigeben / Testsystem

- „Vorab extern freigeben“ macht eine Veranstaltung auf dem geschlossenen
  Testsystem öffentlich; ihre Mails gehen **echt** raus (alle anderen an die
  Testadresse). Die Anmeldungen liegen dann in der Test-Datenbank.
  → Nach dem Livegang übertragen oder löschen; Testsystem nicht dauerhaft mit
  echten Daten betreiben.

---

## 5. Externe Dienste (Auftragsverarbeitung prüfen)

| Dienst | Wofür | Welche Daten | Status / Einstellung |
|---|---|---|---|
| Webhosting (netcup) | Server, Datenbank, Backups | alles | AVV mit Hoster |
| Mailversand (SMTP laut `MAIL_*`, z. B. Brevo) | alle Mails | Empfänger, Inhalt | AVV mit Versanddienst |
| Microsoft Entra ID (M365) | Anmeldung Redaktion | Name, E-Mail, Konto-ID | AVV/Kirchen-Vertrag M365 |
| Brevo (Newsletter, z. B. Georgsbrief) | Newsletter-Anmeldung (Double-Opt-In) | E-Mail | Adressen liegen nur bei Brevo |
| NaMi (DPSG) | Mitgliederabgleich, Datenabgleich | Name, Geburtsjahr | nur wenn eingeschaltet |
| ntfy (Standard `ntfy.sh`, eigener Server möglich: `NTFY_SERVER`) | Push aufs Handy | Titel, Platzzahlen (keine Namen); zufällige Themen | **Empfehlung:** eigener ntfy-Server oder Inhalte weiter knapp halten |
| Nextcloud (eigene) | Kalender-Abgleich, Zuschuss-Ablage | Termine; Zuschussunterlagen | eigene Instanz |
| Instagram / Meta | Feed anzeigen, Beiträge teilen | veröffentlichte Bilder/Texte | Token im Backend |
| WhatsApp (Meta Cloud API) | Newsletter | Telefonnummern | nur wenn eingeschaltet |
| Jitsi (eigene) | Videokonferenzen | Teilnahme-Links | eigene Instanz |

**Ohne externe Anfragen** (selbst gehostet): Karte (PMTiles, Schriften),
Webfonts, Spamschutz (Zeitfalle, Honeypot, ALTCHA-Arbeitsnachweis),
Seitenaufrufe (ohne IP/Cookie). Eingebettete Videos/Karten Dritter laden erst
nach Klick (Zwei-Klick, Consent-Banner).

---

## 6. Technische und organisatorische Maßnahmen

- **Verschlüsselung** sensibler Felder (siehe Abschnitt 2); `APP_KEY` sicher
  verwahren (ohne ihn sind verschlüsselte Felder und Backups unbrauchbar).
- **Backups** täglich 02:00, verschlüsselt, 14 Tage, wöchentlich geprüft;
  optional zusätzlich per WebDAV. „Backup einspielen“ und „auf andere Website
  übertragen“ nur für Admins. **Risiko:** Übertragen von Live auf Test kopiert
  alle Personendaten.
- **Protokoll** (Audit-Log): Rollen, Exporte, Listen, Löschungen,
  Datenschutz-Aktionen; IP-Adressen gekürzt; 12 Monate.
- **Sicherheitsköpfe:** CSP ohne Inline-Skripte, `X-Frame-Options`, iframe nur
  für eingetragene Websites (vorab freigegebene Anmeldung).
- **Testsystem:** geschlossen (Login), `noindex`, Mails an Testadresse
  umgeleitet (außer vorab freigegebene Veranstaltungen).
- **Wartungsmodus** für Umzüge (Website und optional Backend gesperrt).
- **Spamschutz** ohne Drittanbieter; Verdachtsfälle werden zurückgehalten.

---

## 7. Einstellungen mit Datenschutzbezug (Checkliste)

| Wo | Einstellung | Empfehlung DV |
|---|---|---|
| Veranstaltung → Anmeldung | Löschfrist (Tage) | 90 (Standard); kürzer bei Gesundheitsangaben |
| Veranstaltung → Formular | eigene Felder | Gesundheit nur wenn nötig, mit Hinweis |
| Veranstaltung → Info-Mails | Sammeladresse / verantwortliche Person | nur, wer es braucht |
| Veranstaltung | „Vorab extern freigeben“ | nur vor Livegang, danach aus |
| Veranstaltung | Mitgliederabgleich | nur bei Bedarf |
| Benutzer → Rollen | Rollen, „gültig bis“ | Amtszeiten eintragen; weitreichende Rollen sparsam |
| Verwaltung → Funktionen | Nachrichten-Eingang, Seitenaufrufe, Umfragen, Prävention, Auslagen, Materialverleih, WhatsApp, Code-Login | nur Benötigtes; Datenschutzerklärung je Funktion ergänzen |
| Veranstaltungen → „Sammeladresse“ | Adresse für Info-Mails | Funktionspostfach |
| Mein Konto → Push | Push-Thema | nur auf eigenen Geräten |
| Umfrage | anonym, Aufbewahrung | anonym, wo möglich |
| Environment | `NACHRICHTEN_AUFBEWAHRUNG_MONATE`, `BACKUP_PASSWORD`, `NTFY_SERVER`, `MAIL_*` | gesetzt und dokumentiert |

---

## 8. Empfehlungen (Reihenfolge)

1. **Rollen vergeben nach `docs/rollen.md`**, wenige Admins/Verwaltung, für
   alle DL „gültig bis“ = Ende der Amtszeit.
2. **Datenschutzerklärung abgleichen** mit den eingeschalteten Funktionen
   (Nachrichten, Seitenaufrufe, Umfragen, Fotos, Push, Materialverleih,
   Familienanmeldung, Zuschüsse) und den Diensten in Abschnitt 5.
3. **AVV-Liste** für Hoster, Mailversand, Microsoft, Brevo, ggf. Meta prüfen.
4. **Gesundheitsangaben**: Leitfaden für Veranstaltungsverantwortliche
   (nur nötig, kurz löschen, nicht ausdrucken, wenn nicht gebraucht).
5. **Foto-Einwilligungen** organisatorisch klären (Formular bei Anmeldung).
6. **Testsystem** nach dem Livegang von echten Daten befreien; Übertragen von
   Live auf Test nur im Ausnahmefall.
7. **ntfy**: eigenen Server erwägen oder bei Titel/Platzzahlen bleiben.
8. **Zuschuss-Aufbewahrung** mit den Zuschussgebern klären und die Löschfrist
   der betroffenen Veranstaltungen anpassen.
9. **Später:** Rollen-Editor als Matrix für Stämme und Bezirke („erweiterte
   Rechte“), mit eigenen Häkchen für Teilnehmerdaten, Kasse, Prävention.
