# „Meine Anmeldungen“ – Probleme, Bedenken, Lösungen

Stand: 3. Oktober 2026 · Meilenstein Talia · Seite: `/meine-anmeldungen`

## Was es schon gibt

- Anmeldung ohne Konto: E-Mail-Adresse eingeben, sechsstelligen Code aus der Mail eintippen, danach ist die Adresse eine Stunde lang freigeschaltet.
- Übersicht aller Anmeldungen, die unter der Adresse gespeichert sind, getrennt nach bevorstehend und vergangen, auch für mehrere Personen (Kinder, Gruppen).
- Selbst stornieren bis zum Beginn der Veranstaltung. Ein frei werdender Platz geht an die Warteliste.
- Dokumente je Anmeldung (Rechnung, Teilnahmebescheinigung, Sonstiges):
  - Das Diözesanbüro lädt sie im Backend hoch (Veranstaltung → Teilnehmende → Dokumente), auf Wunsch mit Hinweis-Mail.
  - Eine neue Bescheinigung ersetzt die alte.
  - Die Dateien liegen nicht öffentlich und werden mit der Anmeldung gelöscht.

## Entschieden

| Thema | Entscheidung |
|---|---|
| Pflichtenheft „keine Besucher-Logins“ | Teilnehmende sind keine Besucher*innen, sondern Akteure. Der Code-Zugang ist deshalb zulässig. **To-do:** Pflichtenheft 9.3 entsprechend ergänzen. |
| Rechnungen | Das Diözesanbüro erstellt sie wie bisher in der Buchhaltung und lädt sie als PDF hoch. Das CMS erzeugt selbst keine Rechnungen. |
| Bescheinigungen | Das Diözesanbüro kann sie jederzeit durch ein hochgeladenes PDF ersetzen. |

## Probleme und Bedenken

### 1. Löschfrist und „frühere Anmeldungen“ – noch offen

**Problem:** Bestätigte Anmeldungen werden nach `retention_days` gelöscht, Standard 90 Tage nach Veranstaltungsende, und Rechnungen und Bescheinigungen verschwinden mit ihnen. Eine echte Historie („wo war ich mal?“) und ein späterer Download sind so kaum möglich.

**Rechtlicher Rahmen:**

- **KDG statt DSGVO:** Für den DPSG-Diözesanverband als katholischen Träger gilt das Kirchliche Datenschutzgesetz, das sich an der DSGVO orientiert.
  - Zweckbindung und Speicherbegrenzung (§ 7 KDG): Daten nur so lange speichern, wie der Zweck es erfordert.
  - Eine feste Höchstfrist gibt es nicht. Die gewählte Frist muss aber begründet und dokumentiert sein (Löschkonzept, Verzeichnis der Verarbeitungstätigkeiten).
- **Steuer- und Handelsrecht (§ 147 AO, § 257 HGB):**
  - Buchungsbelege wie Rechnungen: 8 Jahre (seit 2025, vorher 10).
  - Bücher und Jahresabschlüsse: 10 Jahre.
  - Geschäftsbriefe: 6 Jahre.
  - Diese Pflicht erfüllt die **Buchhaltung**, nicht das Portal. Die Rechnung im Portal ist nur eine Kopie für die Teilnehmenden, und ihre Frist darf kürzer sein.
- **Zuschüsse** (z. B. Landesjugendplan, Bistum, KJP): Teilnehmendenlisten gehören zum Verwendungsnachweis. Wie lange sie aufzubewahren sind, steht im Bewilligungsbescheid (häufig 5 Jahre). Auch diese Listen gehören in die Buchhaltung bzw. Ablage, nicht ins Portal.
- **Aus- und Fortbildung** (Modulausbildung, Woodbadge, Juleica-Schulungen): Teilnehmende brauchen den Nachweis teils noch Jahre später, z. B. für die Juleica-Verlängerung oder ein Ausbildungszeugnis. Das spricht für eine längere Frist, aber nur für einen schlanken Datensatz.

**Lösung – Stufenmodell (Vorschlag):**

| Stufe | Inhalt | Frist (Vorschlag, zu entscheiden) |
|---|---|---|
| 1 – vollständige Anmeldung | Alle Formularangaben inkl. Essen, Übernachtung, Notfallkontakt, ggf. Gesundheit | wie bisher, z. B. 90 Tage nach Ende (Abrechnung, Nachbereitung) |
| 2 – schlanker Teilnahmenachweis | Name, E-Mail, Veranstaltung, Datum, Status/Teilnahme, Dokumente | z. B. 1 Jahr für normale Veranstaltungen; länger (z. B. 5 Jahre) für Veranstaltungen mit Bescheinigung |
| 3 – Löschung | alles | nach Ablauf von Stufe 2 |

- **Technisch:** Am bisherigen Löschtermin werden nur die Zusatzangaben (Stufe 1) entfernt, die Anmeldung selbst bleibt bis zum zweiten Termin. Die Fristen sind je Veranstaltung einstellbar.
- **Begleitend:**
  - Einwilligungstext und Datenschutzerklärung nennen beide Fristen.
  - Teilnehmende können ihren Nachweis vorzeitig selbst löschen.
  - Den **Datenschutzbeauftragten** (Bistum Trier) vorab einbinden.

### 2. Gemeinsame E-Mail-Adressen (Familien, Gruppenleitungen)

**Problem:** Wer Zugriff auf das Postfach hat, sieht alle Anmeldungen, die unter dieser Adresse laufen, also z. B. alle Kinder einer Familie oder eine Gruppe, die eine Leitung angemeldet hat.

**Bewertung:** Diese Angaben hat die Person selbst gemacht, und eine Anmeldung gilt erst, wenn sie über dasselbe Postfach bestätigt wurde. Das ist vertretbar.

**Offen:** Wenn sich Erwachsene später selbst sehen sollen (z. B. eine 17-jährige Person, die bisher die Eltern angemeldet haben), braucht es eine Möglichkeit, die E-Mail-Adresse zu ändern (siehe 7).

### 3. Ausspähen von Adressen und Raten von Codes – gelöst

- **Gleiche Antwort:** Die Seite antwortet immer gleich, ob es zur Adresse Anmeldungen gibt oder nicht. Mails gehen nur an Adressen mit Anmeldungen.
- **Code:**
  - sechsstellig, 10 Minuten gültig, höchstens 5 Versuche;
  - nur als HMAC im Cache gespeichert, gilt nur einmal;
  - neuer Code frühestens nach 60 Sekunden.
- **Eigene Rate-Limits** pro IP-Adresse und pro E-Mail-Adresse (höchstens 5 Code-Mails pro Stunde).
- **Code statt Link:** Linkscanner in Mailprogrammen (Outlook) können ihn nicht verbrauchen.

### 4. Gemeinsam genutzte Geräte

**Problem:** Auf einem Familien- oder Büro-PC bleibt die Freischaltung bis zu 60 Minuten aktiv.

**Lösung:** Es gibt einen „Abmelden“-Button und kein „angemeldet bleiben“. Optional: Frist auf 30 Minuten senken.

### 5. Falsches Dokument bei der falschen Person

**Problem:** Eine Rechnung mit Anschrift und Betrag landet bei der falschen Person. Das wäre eine Datenpanne nach KDG mit Meldepflicht.

**Lösung:**

- Der Upload-Dialog zeigt Name **und E-Mail-Adresse**.
- Jeder Upload und jedes Löschen steht im Audit-Log.
- Ein falsch hochgeladenes Dokument lässt sich sofort löschen.
- **Empfehlung:** Rechnungsnummer als Bezeichnung eintragen. Die Hinweis-Mail erst verschicken lassen, wenn der Upload geprüft ist (Schalter „Person informieren“).

### 6. Rechnungen: steuerliche Fragen

- **Privatpersonen:** Eine PDF-Rechnung reicht.
- **E-Rechnung:** Gehen Rechnungen an **Stämme, Kirchengemeinden oder andere Träger**, kann seit 2025 die Pflicht zur E-Rechnung (XRechnung/ZUGFeRD) greifen, je nachdem, ob beide Seiten umsatzsteuerlich Unternehmer sind. **Mit der Buchhaltung bzw. Steuerberatung klären.** Das Portal kann auch XML- bzw. ZUGFeRD-Dateien ausliefern, wenn das gebraucht wird.
- **Originale:** Das Portal ist nur ein zusätzlicher Zustellweg. Die Originale bleiben in der Buchhaltung.

### 7. E-Mail-Adresse ändert sich

**Problem:** Nach einem Adresswechsel sind alte Anmeldungen nicht mehr erreichbar.

**Lösung (noch zu bauen):** Das Diözesanbüro kann die E-Mail-Adresse einer Anmeldung im Backend ändern; das wird im Audit-Log festgehalten.

### 8. Stornieren über das Portal

**Problem:** Im Portal kann man bis zum Beginn der Veranstaltung stornieren. Bei kostenpflichtigen Veranstaltungen gibt es aber oft Stornofristen oder Stornogebühren.

**Lösung (zu entscheiden):**

- Option a: eine Stornofrist je Veranstaltung. Danach zeigt das Portal „Bitte wende dich an …“ statt des Storno-Buttons.
- Option b: Die Stornobedingungen stehen neben dem Button.

### 9. Teilnahmebescheinigung automatisch erzeugen (noch zu bauen)

- **Nur bei tatsächlicher Teilnahme:** Das Team setzt nach der Veranstaltung einen „teilgenommen“-Haken in der Teilnehmendenliste.
- **Fälschungsschutz:**
  - Jede Bescheinigung bekommt einen Prüfcode bzw. QR-Code. Unter `dpsg-trier.de/bescheinigung/<code>` lässt sich prüfen, ob sie echt ist.
  - Unterschrift bzw. Stempel nur als Bild in geringer Auflösung.
- **Offen:**
  - Wer unterschreibt?
  - Welcher Text (Inhalte, Stundenumfang für Juleica)?
  - Wie lange bleibt der Prüfcode gültig? Das hängt an der Frist in Punkt 1.

### 10. Testumgebung

**Problem:** Auf test.dpsg-trier.de werden alle Mails umgeleitet, auch die Code-Mails. Wer testen will, muss im GitHub-Secret `MAIL_TEST_ALLOWLIST` stehen (höchstens 10 Adressen, keine ganze Domain, damit Ehrenamtliche mit Verbandsadresse keine Testmails bekommen).

**Prüfen:** Actions → „Server-Prüfung“ → `mail:check-allowlist`.

### 11. Sicherungen

Dokumente liegen in `storage/app/private` und sind damit in den Backups enthalten. Gelöschte Dokumente bleiben dort, bis die Sicherungen rotiert sind. Das gehört so ins Löschkonzept.

### 12. Datenschutz-Dokumentation – To-do

- **Datenschutzerklärung:** Abschnitt „Meine Anmeldungen“ ergänzen (Zweck, Code-Mail über Brevo, Sitzungscookie, Fristen).
- **Verzeichnis der Verarbeitungstätigkeiten:** Eintrag „Veranstaltungsanmeldung“ um Portal und Dokumente erweitern.
- **Pflichtenheft 9.3:** Ausnahme für Teilnehmende und das Stufenmodell der Löschfristen festhalten.

## Offene Entscheidungen auf einen Blick

1. Fristen für das Stufenmodell (Punkt 1), Abstimmung mit dem Datenschutzbeauftragten.
2. E-Rechnung bei Rechnungen an Stämme oder Träger nötig? (Punkt 6)
3. Stornofrist oder Stornobedingungen je Veranstaltung? (Punkt 8)
4. Bescheinigung: Unterschrift, Text, Gültigkeit des Prüfcodes (Punkt 9)
5. Sitzungsdauer 60 oder 30 Minuten? (Punkt 4)
