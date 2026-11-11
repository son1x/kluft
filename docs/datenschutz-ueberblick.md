# Datenschutz: Risiken und Einstellungen (Überblick)

Stand 11.11.2026, Code-Stand nach Rechte-Matrix, sparsamer Teilnehmendenansicht und den Datenschutz-Verbesserungen (sensible Felder, Foto-Einwilligung, Rechte-Prüfung, Kontosperre). Fokus DV Trier, gilt sinngemäß für
alle Kluft-Websites. Das ist eine technische Bestandsaufnahme, **keine
Rechtsberatung**. Die Datenschutzerklärung und die Verträge (AVV) gehören zur
oder zum Datenschutzbeauftragten des Verbands bzw. Bistums.

Siehe auch: `docs/rollen.md` (wer darf was), `docs/veranstaltungen.md`,
`docs/praevention.md`, `docs/auslagen.md`, `docs/materialverleih.md`,
`docs/nachrichten.md`, `docs/umfragen.md`, `docs/aufrufe.md`,
`docs/ntfy-server.md`, `docs/datenschutz-textbausteine.md`, `docs/avv-checkliste.md`.
Die Bewertung mit Punkten steht in `docs/datenschutz-bewertung.md`.

---

## 1. Kurzfassung: die wichtigsten Punkte

| # | Risiko | Einschätzung | Was hilft |
|---|---|---|---|
| 1 | **Breiter Zugriff auf Teilnehmerdaten** durch Verwaltung, Bildungsreferent*innen und Admins (alle Veranstaltungen, alle Personen) | mittel (vorher hoch) | Rechte-Matrix mit sensiblen Rechten als eigene Häkchen; DL nur sparsame Liste; **jährliche Rechte-Prüfung** mit Erinnerung; Hinweis auf Bereichsrollen ohne „gültig bis“; ungenutzte Konten werden nach 12 Monaten gesperrt (Abschnitt 3) |
| 2 | **Gesundheits- und Ernährungsangaben** in eigenen Formularfeldern (z. B. „Allergien“) sind besondere Kategorien (Art. 9 DSGVO, § 11 KDG) | mittel (vorher hoch) | Feld als **„sensibel“** markieren: Hinweis im Formular und in der Einwilligung, nicht im Standard-Export, nicht in der sparsamen Liste, **Löschung 14 Tage nach der Veranstaltung** (Abschnitt 4.2). Bleibt: nur markiert wirkt der Schutz |
| 3 | **Exporte und Ausdrucke** (Excel, „Liste für unterwegs“) verlassen das System | mittel | werden protokolliert (mit sensiblen Angaben gesondert); Hinweis vor dem Herunterladen; sensible Felder nur auf ausdrücklichen Wunsch; Liste „vertraulich, vernichten“ |
| 4 | **Testsystem mit echten Daten** | gering (geplant erledigt) | Das heutige Testsystem wird befüllt und **wird zur öffentlichen Website**; danach entsteht ein neues Testsystem **ohne echte Personendaten** (Abschnitt 4.5) |
| 5 | **Fotos von Minderjährigen** (Galerie, Mediathek, Instagram) | gering bis mittel | **Foto-Einwilligung im Anmeldeformular** (je Veranstaltung einschaltbar, je Person Ja/Nein); Fotos-Reiter nennt, wer nicht eingewilligt hat; Vier-Augen-Freigabe |
| 6 | **Externe Dienste** (Mailversand, Microsoft Entra, Brevo-Newsletter, NaMi, ntfy, Instagram/Meta, ggf. WhatsApp) | mittel | **AVV-Checkliste** (`docs/avv-checkliste.md`); eigener ntfy-Server möglich (`docs/ntfy-server.md`); nur nötige Funktionen einschalten (Abschnitt 5) |
| 7 | **Admin-Konten** sehen alles inkl. Backups und Protokoll | mittel | wenige Admins; Anmeldung über Microsoft 365 mit MFA; Notfallzugang offline verwahren |
| 8 | **Backups** enthalten alle Daten, gelöschte Daten bleiben bis zu 14 Tage darin | gering bis mittel | Backups verschlüsselt (Passwort), 14 Tage; Passwort sicher verwahren |
| 9 | **Lange Aufbewahrung je Veranstaltung** (bis 365 Tage) oder Zuschuss-Nachweise | gering bis mittel | Standard 90 Tage; bei Zuschuss-Veranstaltungen erklärt der Hinweis an der Löschfrist, was gesondert aufzubewahren ist |
| 10 | **Präventionsdaten** (Führungszeugnis eingesehen) | gering (nur Daten, kein Inhalt) | Zugriff nur Vorstand/Verwaltung; Löschung 1 Jahr nach Ablauf; Regel des Bistums prüfen |

---

## 2. Welche Daten wo liegen

Legende Verschlüsselung: **ja** = in der Datenbank verschlüsselt (Laravel
`encrypted`, Schlüssel `APP_KEY`), **nein** = Klartext in der Datenbank
(geschützt durch Server/Zugang, aber in Backups und bei DB-Zugriff lesbar).

| Daten | Wer sieht sie im Backend | Verschlüsselt | Löschung (automatisch) | Einstellung |
|---|---|---|---|---|
| **Anmeldungen**: Name, E-Mail, Geburtsjahr, Sorgeberechtigte, Foto-Einwilligung | Redaktion (Verwaltung, BR, Admin) alle; DL im eigenen Bereich nur **sparsam** (Name, Stamm, Tage, Status); Vorstand nur Anzahlen | Grunddaten **nein**, Zusatzangaben aus dem Baukasten **ja** | unbestätigt nach 48 h; **sensible Felder 14 Tage nach der Veranstaltung** (`SENSIBLE_TAGE`); sonst *Löschfrist der Veranstaltung* (Standard 90 Tage nach Ende, 1–365) | je Veranstaltung „Anmeldedaten löschen nach … Tagen“, je Feld „Sensible Angabe“ |
| Hochgeladene Dokumente zur Anmeldung (Rechnungen, Bescheinigungen) | wie Anmeldungen | Ablage privat (nicht öffentlich erreichbar) | mit der Anmeldung | – |
| **Nachrichten** (Kontaktformular, Fragen zu Veranstaltungen) | Redaktion; Stufen-/Sektionsadmins ihre | Nachrichtentext **ja** | nach 6 Monaten (`NACHRICHTEN_AUFBEWAHRUNG_MONATE`) | Funktion „Nachrichten-Eingang“ |
| **Benutzerkonten** (Name, E-Mail, Rollen, letzte Anmeldung) | Verwaltung, Admins; BR/Stufenadmins nur Autor:innen | 2FA-Geheimnis, NaMi-Zugang, Push-Thema **ja** | **gesperrt nach 12 Monaten ohne Anmeldung** (Vorwarnung per Mail einen Monat vorher; Notfall-Admin ausgenommen); Rollen laufen mit „gültig bis“ ab | `KONTEN_SPERREN_MONATE` (0 = aus), Rollen mit „gültig bis“ |
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
| Stufen-/Sektionsadmin (DV: DL) | eigener Bereich, **nur lesen, sparsam**: Name, Stamm, Tage, Status | Autor:innen des Bereichs | des Bereichs | eigene Auslagen | – | – | – |
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
- **DL liest nur und sparsam** (DV): Ehrenamtliche Stufen- und
  Sektionsadmins sehen nur Name, Stamm, gewählte Tage und Status
  (bestätigt/Warteliste). Keine Kontaktdaten, kein Geburtsjahr, keine
  weiteren Formularangaben (z. B. Gesundheit), keine Sorgeberechtigten,
  Dokumente oder Zahlungen; Schreib-, Detail- und Exportaktionen gibt es in
  dieser Ansicht nicht. Die volle Liste sehen die datenschutzgeschulten
  Bildungsreferent*innen, die Verwaltung und „Veranstaltungen bearbeiten“.
- **Vergabe:** Admins bekommen bei weitreichenden Rollen (Verwaltung,
  Bildungsreferent*in, Kasse, WhatsApp, Admin) einen Sicherheitshinweis.
  Jede Vergabe/Entzug steht im Protokoll.
- **Konten ohne Zuständigkeit**: Personen, die nichts pflegen (z. B.
  Vorstand ohne Aufgaben), bekommen keine weitreichende Rolle (Empfehlung
  Autor:in oder kein Konto).
- **Anmeldung:** Microsoft 365 (Entra) mit der MFA-Richtlinie des Tenants;
  ohne M365 optional E-Mail-Code. Der Notfallzugang (Super-Admin, 2FA) ist
  offline zu verwahren.
- **Rechte-Matrix** (Verwaltung → Rollen und Rechte, nur Admins): Die
  mitgelieferten Rollen sind feste Vorlagen; eigene Rollen entstehen als
  Kopie und werden per Häkchen angepasst – auch in Stämmen und Bezirken.
  Sensible Rechte (Veranstaltungen bearbeiten = volle Teilnehmendenliste,
  Benutzer, Kasse, Prävention, Datenschutz-Anfragen) sind markiert;
  Bereichsrollen gelten nie für die ganze Website.
- **Rechte-Prüfung** (Verwaltung → Rechte-Prüfung, wer „Benutzer und alle
  Rollen“ darf): listet alle aktiven Konten mit sensiblen Rechten, ihre Rollen
  mit „gültig bis“, die letzte Anmeldung und Hinweise („seit über 6 Monaten
  nicht angemeldet“, „ohne gültig bis“). „Prüfung abschließen“ speichert
  Datum und Person im Protokoll; nach 12 Monaten erinnert „Was steht an“.
- **Ungenutzte Konten:** täglicher Lauf `konten:inaktive` warnt nach 11
  Monaten ohne Anmeldung per Mail und sperrt nach 12 (frühestens 14 Tage nach
  der Warnung). Lokaler Notfall-Admin ausgenommen. Eine Anmeldung setzt die
  Warnung zurück; Entsperren unter Benutzer.
- **„Was steht an“** zeigt Bereichsrollen ohne „gültig bis“, damit
  Amtszeiten nicht vergessen werden.

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
  Teil über Jahre. Der Hinweis an der Löschfrist erklärt bei
  Zuschuss-Veranstaltungen: Frist bis nach der Abrechnung, eingereichte
  Teilnahmeliste und Verwendungsnachweis gesondert aufbewahren (z. B.
  Nextcloud-Ablage), nicht in Kluft.

### 4.2 Formularfelder (Baukasten)

- Standardfelder fragen nur Nötiges ab; Geburtsjahr statt -datum.
- Freitext „Anmerkungen“ trägt den Hinweis „keine Gesundheitsangaben“.
- **Eigene Felder** (bis 15) sind frei formulierbar. Felder wie
  „Allergien“, „Medikamente“, „Unverträglichkeiten“ sind Gesundheitsdaten.
  Dafür gibt es den Schalter **„Sensible Angabe“** je Feld:
  - Im Formular steht unter dem Feld „Besonders geschützt: … sieht nur die
    Veranstaltungsleitung, wir löschen sie N Tage nach der Veranstaltung“;
    die Einwilligung nennt das ebenfalls (neue Textversion).
  - Excel-Export ohne diese Felder; mitnehmen nur per Schalter im
    Export-Dialog, protokolliert als „mit sensiblen Angaben“.
  - In der „Liste für unterwegs“ als „(sensibel)“ gekennzeichnet und nicht
    vorausgewählt; in der sparsamen Liste der DL nie.
  - Täglicher Lauf `registrations:sensible-loeschen` (03:05) entfernt sie
    `SENSIBLE_TAGE` (Standard 14) Tage nach Veranstaltungsende aus allen
    Anmeldungen; der Rest der Anmeldung bleibt bis zur normalen Frist.
  - **Bleibt organisatorisch:** Wer ein Gesundheitsfeld anlegt, muss den
    Schalter setzen. Ohne Markierung gelten die alten Regeln.
- Alle Zusatzangaben (`data`) sind verschlüsselt; Name, E-Mail und Geburtsjahr
  nicht (für Suche, Duplikat- und Mitgliederprüfung nötig).

### 4.3 Wer bekommt Daten aus der Anmeldung

| Weg | Inhalt | Empfänger |
|---|---|---|
| Bestätigungs-/Statusmails | Titel, Name, Status, Links (keine Zusatzangaben) | angemeldete Person |
| Info-Mails ans Team | Name, Stamm, Stand der Plätze, Link ins Backend | Sammeladresse (z. B. info@) und verantwortliche Person, je Veranstaltung abschaltbar |
| Push (ntfy) | **nur** Titel und Platzzahlen, keine Namen | wer Teilnehmende sehen darf und Push eingerichtet hat |
| Nachricht an Teilnehmende | Text der Leitung, je Person einzeln (Adressen unsichtbar) | Teilnehmende |
| Excel-Export / Liste für unterwegs (PDF) | alles außer sensiblen Feldern (nur auf Wunsch) bzw. gewählte Spalten; „Keine Fotos“ markiert | Gerät der Person; **protokolliert** |
| Küchenliste (PDF) | Essen je Tag, Verpflegung, nur Personen mit Besonderheiten (Vorname + Initial, ggf. Stamm, z. B. Allergien) – keine Kontaktdaten | Aushang in der Küche; **protokolliert** |
| Mitgliederabgleich (NaMi) | Name, Geburtsjahr | NaMi (DPSG), nur wenn eingeschaltet |
| Teilnahmebeiträge | Name, Betrag, Verwendungszweck | Kasse; Mail mit GiroCode an die Person |

### 4.4 Fotos

- **Foto-Einwilligung bei der Anmeldung** (Schalter je Veranstaltung): je
  Person freiwillig Ja/Nein, auch für weitere Personen der
  Familienanmeldung. Die Antwort steht in der Teilnehmendenliste (auch
  sparsam), in den Details, in der Auskunft und als „Keine Fotos“ in der
  Liste für unterwegs. Der Fotos-Reiter nennt, wer nicht eingewilligt hat.
- Hochladen nur mit Pflicht-Haken „Einwilligung der erkennbar Abgebildeten
  (bei Minderjährigen der Sorgeberechtigten)“.
- Bis zur Freigabe **privat** gespeichert, Vorschau nur für Berechtigte.
  Freigabe nur durch eine **zweite Person**. Ausblenden jederzeit.
- **Risiko:** Ohne eingeschaltete Abfrage bleibt der Haken eine
  Selbstauskunft. → Für Veranstaltungen mit Fotos die Abfrage einschalten;
  Widerruf per Mail heißt: Fotos ausblenden bzw. löschen. Freigegebene Fotos sind öffentlich und können
  kopiert werden; Löschen auf Wunsch entfernt sie samt Medium.
- „Auf Instagram teilen“ überträgt Bild und Text an Meta.

### 4.5 Vorab freigeben / Testsystem

- „Vorab extern freigeben“ macht eine Veranstaltung auf dem geschlossenen
  Testsystem öffentlich; ihre Mails gehen **echt** raus (alle anderen an die
  Testadresse). Die Anmeldungen liegen dann in der Test-Datenbank.
- **Plan DV:** Das heutige Testsystem wird mit Inhalten befüllt und zieht
  als öffentliche Website um (Livegang). Danach wird ein **neues Testsystem
  ohne echte Personendaten** aufgesetzt (nur Inhalte bzw. Beispieldaten,
  kein „auf andere Website übertragen“ von Live).
  → Nach dem Umzug: Seitenaufrufe zurücksetzen (Verwaltung → Aufrufe),
  Rechte-Prüfung einmal abschließen, Zugänge des alten Testbetriebs prüfen.

---

## 5. Externe Dienste (Auftragsverarbeitung prüfen)

| Dienst | Wofür | Welche Daten | Status / Einstellung |
|---|---|---|---|
| Webhosting (netcup) | Server, Datenbank, Backups | alles | AVV mit Hoster |
| Mailversand (SMTP laut `MAIL_*`, z. B. Brevo) | alle Mails | Empfänger, Inhalt | AVV mit Versanddienst |
| Microsoft Entra ID (M365) | Anmeldung Redaktion | Name, E-Mail, Konto-ID | AVV/Kirchen-Vertrag M365 |
| Brevo (Newsletter, z. B. Georgsbrief) | Newsletter-Anmeldung (Double-Opt-In) | E-Mail | Adressen liegen nur bei Brevo |
| NaMi (DPSG) | Mitgliederabgleich, Datenabgleich | Name, Geburtsjahr | nur wenn eingeschaltet |
| ntfy (Standard `ntfy.sh`, eigener Server möglich: `NTFY_SERVER`, `NTFY_TOKEN`) | Push aufs Handy | Titel, Platzzahlen (keine Namen); zufällige Themen | **Empfehlung:** eigener Server hinter Caddy, Anleitung `docs/ntfy-server.md` |
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
| Veranstaltung → Anmeldung | Löschfrist (Tage) | 90 (Standard); bei Zuschüssen bis nach der Abrechnung |
| Veranstaltung → Formular | eigene Felder, Schalter „Sensible Angabe“ | Gesundheit nur wenn nötig, **immer als sensibel markieren** |
| Veranstaltung → Anmeldung | „Foto-Einwilligung abfragen“ | bei allen Veranstaltungen mit Fotos an |
| Verwaltung → Rechte-Prüfung | „Prüfung abschließen“ | einmal im Jahr (Erinnerung kommt) |
| Verwaltung → Rollen und Rechte | eigene Rollen | sensible Häkchen nur bewusst |
| Veranstaltung → Info-Mails | Sammeladresse / verantwortliche Person | nur, wer es braucht |
| Veranstaltung | „Vorab extern freigeben“ | nur vor Livegang, danach aus |
| Veranstaltung | Mitgliederabgleich | nur bei Bedarf |
| Benutzer → Rollen | Rollen, „gültig bis“ | Amtszeiten eintragen; weitreichende Rollen sparsam |
| Verwaltung → Funktionen | Nachrichten-Eingang, Seitenaufrufe, Umfragen, Prävention, Auslagen, Materialverleih, WhatsApp, Code-Login | nur Benötigtes; Datenschutzerklärung je Funktion ergänzen |
| Veranstaltungen → „Sammeladresse“ | Adresse für Info-Mails | Funktionspostfach |
| Mein Konto → Push | Push-Thema | nur auf eigenen Geräten |
| Umfrage | anonym, Aufbewahrung | anonym, wo möglich |
| Environment | `NACHRICHTEN_AUFBEWAHRUNG_MONATE`, `SENSIBLE_TAGE` (14), `KONTEN_SPERREN_MONATE` (12), `BACKUP_PASSWORD`, `NTFY_SERVER`, `NTFY_TOKEN`, `MAIL_*` | gesetzt und dokumentiert |

---

## 8. Empfehlungen (Reihenfolge)

Erledigt im Code: Rechte-Matrix, sparsame Liste für die DL, sensible Felder,
Foto-Einwilligung, Rechte-Prüfung, Sperre ungenutzter Konten, Hinweise zu
„gültig bis“ und Zuschuss-Aufbewahrung. Offen bzw. organisatorisch:

1. **Rollen vergeben nach `docs/rollen.md`**, wenige Admins/Verwaltung, für
   alle DL „gültig bis“ = Ende der Amtszeit; danach die erste
   **Rechte-Prüfung abschließen**.
2. **Datenschutzerklärung** mit `docs/datenschutz-textbausteine.md` abgleichen
   (nur eingeschaltete Funktionen).
3. **AVV-Checkliste** (`docs/avv-checkliste.md`) abarbeiten und ablegen.
4. **Gesundheitsfelder** in bestehenden Veranstaltungen als „sensibel“
   markieren; Veranstaltungsverantwortliche kurz einweisen.
5. **Foto-Einwilligung** bei Veranstaltungen mit Fotos einschalten.
6. **Umzug Test → Live**, danach neues Testsystem ohne echte Daten.
7. **Eigener ntfy-Server** nach `docs/ntfy-server.md`.
8. **Zuschuss-Aufbewahrung** mit den Zuschussgebern klären.
