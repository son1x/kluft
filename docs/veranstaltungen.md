# Veranstaltungen und Anmeldung

Backend → Veranstaltungen. Anforderungen: Pflichtenheft 6.7 (F-EV-01 ff.).
Hier steht, was beim Pflegen einer Anmeldung wichtig ist.

## Stufe „Leiter*innen“

Für Veranstaltungen, die sich an Leitende richten (Leiterrunden, Schulungen):
Stufe **„Leiter*innen“** (nur wer Veranstaltungen für die ganze Website
anlegt, nicht Stufenadmins). Auf der Website als graues Kennzeichen und als
Filter bei Veranstaltungen und im Kalender. Teilnehmende sehen
Bildungsreferent*innen, Verwaltung und Admins wie gewohnt, dazu der
**Vorstand** lesend (sparsame Liste). Stufenadmins sehen sie nicht. Keine
eigene Stufenseite – die Stufen bleiben Biber bis Rover.

## Angaben einer Anmeldung bearbeiten

In der Teilnehmendenliste über **„Angaben bearbeiten“** (Stift) bei der Person: Name, E-Mail und alle abgefragten Angaben lassen sich nachträglich ändern – z. B. wenn jemand nur an einzelnen Tagen dabei ist (Feld „Anwesenheit (Tage)“). Die Person bekommt keine Mail; die Änderung wird protokolliert. Darf jeder, der die Teilnehmenden der Veranstaltung verwalten darf. Hat die Veranstaltung **Zusatzinfos fürs Team** (z. B. Zimmer, Zelt), stehen sie hier ebenfalls zur Bearbeitung und als Spalten in der Liste.

## Vor Ort (Tablet und Handy)

Für die Durchführung: Anwesenheit je Tag, Zahlungsstand und Barzahlung, Verpflegung, Korrekturen und Zusatzinfos wie Zimmer – siehe [docs/vor-ort.md](vor-ort.md).

## Anmeldungen endgültig löschen (nur Admins)

Unter „Teilnehmende“ einzeln (Aktion **Endgültig löschen**) oder für
markierte Anmeldungen (Sammelaktion). Gedacht für Testanmeldungen, Doppelte
oder Löschwünsche, die nicht über Verwaltung → Datenschutz laufen. Löscht die
Anmeldung mit allen Angaben und hochgeladenen Dokumenten unwiderruflich;
keine Mail an die Person, kein Nachrücken von der Warteliste (dafür
„Stornieren“). Zahlungen bleiben für die Kasse erhalten. Jeder Vorgang steht
im Protokoll („Anmeldungen endgültig gelöscht: N“).

## Datum und Uhrzeit

Wie im Apple-Kalender: **Beginn** und **Ende** jeweils mit Datum und Uhrzeit
in getrennten Feldern, darüber der Schalter **Ganztägig**.

- Beim Wählen des Beginns rückt das Ende mit (gleicher Tag); bei einer
  Uhrzeit wird das Ende eine Stunde später vorgeschlagen.
- **Ganztägig:** nur Datum, keine Uhrzeiten. Gespeichert als 0:00 bis 23:59
  des letzten Tages. Die Website zeigt nur das Datum („5. – 9. Mai 2027“),
  im Kalender und im Kalender-Abo (ICS) erscheint ein Ganztagstermin.
- Ende vor Beginn wird abgelehnt.
- Die Liste der Veranstaltungen zeigt ganztägige mit Datum bzw. Zeitraum und
  dem Vermerk „ganztägig“.
- Aus einem Nextcloud-Termin übernommen: „Ganztägig“ wird mitübernommen.
- Ältere Veranstaltungen mit Uhrzeit 0:00 (der frühere Trick) wurden beim
  Update als ganztägig markiert.

## Formular-Baukasten

Veranstaltung bearbeiten → **Formular-Baukasten**. Name und E-Mail werden
immer abgefragt. Alles andere nur, wenn es für die Veranstaltung wirklich
gebraucht wird (Datensparsamkeit):

- **Feste Felder** (Geburtsjahr, Stamm, Telefon, Verpflegung, Übernachtung,
  Tage, Anmerkungen, für Zuschüsse auch Adresse, Geburtsdatum und Geschlecht): je Feld „Nicht abfragen“, „Optional“ oder „Pflichtfeld“.
- **Eigene Felder** für Fragen, die es oben nicht gibt, bis zu 15:

  | Art | Im Formular | Prüfung |
  |---|---|---|
  | Kurzer Text | einzeiliges Feld | höchstens 150 Zeichen |
  | Langer Text | mehrzeiliges Feld | höchstens 1.000 Zeichen |
  | Zahl | Zahlenfeld | ganze Zahl von 0 bis 100.000 |
  | Auswahl (eine Antwort) | Auswahlliste | nur angebotene Antworten |
  | Auswahl (mehrere Antworten) | Kästchen zum Ankreuzen | nur angebotene Antworten, als Pflichtfeld mindestens eine |

  Je Feld: Frage, Pflichtfeld ja/nein, optional ein Hinweis unter dem Feld.
  Bei Auswahlfeldern stehen die Antwortmöglichkeiten eine pro Zeile.

Eigene Felder stehen im Formular vor „Anmerkungen“. Ihre Antworten erscheinen
überall wie die festen Felder: Liste und Details der Anmeldungen, „von Hand
eintragen“, Export und Datenauskunft. Jedes eigene Feld hat intern einen
festen Schlüssel (`eigen_…`). Umbenennen und Verschieben ändert deshalb nichts
an den Antworten. Wird ein Feld gelöscht, verschwinden seine Antworten aus der
Anzeige.

Keine Gesundheitsangaben über eigene Felder abfragen. Die klären die
Verantwortlichen bei Bedarf persönlich.

## Teilnehmende

- **Reiter:** Beim Bearbeiten einer Veranstaltung stehen oben „Veranstaltung“ (Formular), „Teilnehmende“ (Zahl = aktive Anmeldungen) und „Fragen“. Direkt zu den Teilnehmenden: Veranstaltungsliste → „Teilnehmende“ oder `?relation=0` an der Adresse.
- **Von Hand bestätigen:** Bei unbestätigten Anmeldungen (z. B. Bestätigungsmail nicht angekommen) „Bestätigen“, für mehrere die Sammelaktion „Bestätigen“.
  - Status: wie per Link (freier Platz, sonst Warteliste; ohne Warteliste trotzdem bestätigt), „bestätigt – auch über die Platzzahl hinaus“ oder „Warteliste“.
  - „Bestätigung per E-Mail schicken“ ist voreingestellt (mit Stornolink).
  - Danach wie bei der Bestätigung per Link: Info-Mails ans Team, Teilnahmebeitrag, Mitgliederabgleich. Protokoll `registration.confirm_manual`.
  - Bestätigte, Warteliste und Stornierte werden übersprungen.
## Nachricht an die Teilnehmenden, Erinnerung

- **Nachricht an Teilnehmende** (Reiter „Teilnehmende“, Kopfzeile): an Bestätigte, Warteliste und/oder Unbestätigte, mit Anzahl je Gruppe. Jede Person bekommt eine eigene Mail (keine sichtbaren Adressen) mit Anrede und einem Kasten mit Titel, Datum, Ort und Link; Antworten gehen an die Kontakt-E-Mail der Veranstaltung. Der Text wird als reiner Text übernommen (Zeilenumbrüche bleiben). Gespeichert werden Betreff, Text und Zahl der Empfänger*innen (`event_nachrichten`), protokolliert als `event.nachricht`.
- **Erinnerung** (Veranstaltung bearbeiten → „Erinnerung an die Teilnehmenden“): 1 bis 14 Tage vorher, eigener Text oder Standardtext. Der Befehl `veranstaltungen:erinnerungen` (täglich 9 Uhr) schickt sie einmal an alle Bestätigten; ändert sich das Datum oder die Vorlaufzeit, wird neu erinnert.
- Test-Umleitung und „Vorab extern freigeben“ gelten wie bei allen Mails.
## Mehrere Personen in einer Anmeldung (Familien)

Schalter „Mehrere Personen in einer Anmeldung (Familien)“ unter „Anmeldung“.
Das Formular zeigt dann „Weitere Person hinzufügen“ (ohne JavaScript bleibt es
bei einer Person):

- bis zu **5 weitere** Personen, je Person Vorname, Nachname (vorbelegt),
  Geburtsjahr (wenn das Formular es abfragt) und Teilnahmebeitrag (wenn es
  Preisstufen gibt);
- alle übrigen Angaben (Baukasten-Felder, E-Mail, Personensorgeberechtigte,
  Einwilligung) werden von der ersten Person übernommen; unter 16 gilt die
  Regel für jede Person;
- **eine Bestätigungsmail** mit allen Namen; der Link bestätigt alle. Plätze
  werden der Reihe nach vergeben, wer keinen mehr bekommt, kommt auf die
  Warteliste. Die Ergebnisseite zeigt das je Person;
- je Person eine eigene Anmeldung (Liste, Export, Zahlungen, Storno wie
  gewohnt) mit eigener Mail und eigenem Stornolink. Verbunden sind sie über
  `anmeldung_gruppe`. Verfällt der Link, werden alle gelöscht.

## Mails der Anmeldung

Alle Mails an Teilnehmende verschickt `EventRegistrationMail` über die
Warteschlange (verschlüsselt, Versand etwa jede Minute):

| Anlass | Betreff beginnt mit | Link in der Mail |
|---|---|---|
| Anmeldung abgeschickt | „Bitte bestätige deine Anmeldung“ | Bestätigen (gilt 48 Stunden, danach wird die Anmeldung gelöscht) |
| bestätigt, Platz frei | „Anmeldung bestätigt“ | Stornieren |
| bestätigt, Warteliste | „Du stehst auf der Warteliste“ | Stornieren |
| von der Warteliste nachgerückt | „Du bist nachgerückt“ | Stornieren |
| storniert | „Anmeldung storniert“ | – |

Die Mails enthalten Titel, Name, Status und Links, keine weiteren Angaben aus
dem Formular. Bei vorab freigegebenen Veranstaltungen auf dem Testsystem fehlt
der Hinweis auf „Meine Anmeldungen“, und „Website“ führt zur echten Website.

### Info-Mails an das Team

Veranstaltung bearbeiten → „Info-Mails an das Team“, beide Schalter
standardmäßig an (auch bei bestehenden Veranstaltungen, in allen Instanzen):

- **Sammeladresse informieren:** Adresse unter Veranstaltungen → Knopf
  „Sammeladresse“ (nur Verwaltung, protokolliert). Leer: die Kontaktadresse
  der Website, beim DV info@dpsg-trier.de.
- **Verantwortliche Person informieren:** Feld „Verantwortlich (E-Mail)“ mit
  Vorschlägen aus Benutzer*innen und Leitung & Büro. Neue Veranstaltungen
  tragen die eigene Adresse ein; leer: wer die Veranstaltung angelegt hat.
- **Suchen (Lupe)** an „Verantwortlich (E-Mail)“ und „Kontaktperson“: Name
  oder Adresse eintippen, Treffer aus aktiven Benutzerkonten und Personen der
  Website.
  Bei „Verantwortlich“ wird die E-Mail übernommen, bei der Kontaktperson nur
  der Name – die öffentliche Kontakt-E-Mail bleibt eine Funktionsadresse.

Eine Info-Mail (`AnmeldungInfoMail`) kommt nach der Bestätigung (bestätigt oder
Warteliste), beim Nachrücken und bei der Abmeldung, nicht schon bei der
unbestätigten Anmeldung. Sie enthält Name, ggf. Stamm, den Stand der Plätze und
den Knopf „Anmeldungen ansehen“ ins Backend. Gleiche Adressen bekommen eine
Mail. Wer das für eine Veranstaltung nicht will, schaltet es dort aus.

Auf dem Testsystem gehen Mails an die Testadresse (`MAIL_TEST_REDIRECT_TO`,
docs/deploy.md „Mail“). Ausnahme: Veranstaltungen mit „Vorab extern
freigeben“, siehe unten.

## Vorab extern freigeben (vor dem Livegang)

Schalter an der Veranstaltung, nur sichtbar auf einem Testsystem oder wenn
`einbetten_von` gesetzt ist. Damit läuft eine Anmeldung schon vor dem
Livegang über test.dpsg-trier.de, auch als iframe auf der alten Website:

- Veranstaltungsseite, Formular, Bestätigen und Stornieren per Link,
  Datenschutz und Impressum sind auch auf dem geschlossenen Testsystem ohne
  Anmeldung erreichbar, ohne gelbes Band. Bestätigen und Stornieren
  funktionieren auch im Wartungsmodus.
- Die Mails dieser Veranstaltung gehen an die echten Adressen, auch die
  Info-Mails ans Team. Erkannt an der Kopfzeile `X-Kluft-Extern`, die vor dem
  Versand entfernt wird.
- Gäste sehen diese Seiten in einem schlichten Rahmen: blaues Band mit Logo
  (führt zur Live-Adresse bzw. vor dem Livegang zur alten Website), unten
  Impressum und Datenschutz, kein Menü und keine Fußzeile der neuen Website.
  Die Einbett-Ansicht zeigt die Infos zur Aktion zum Aufklappen.
- Einbett-Ansicht: `/veranstaltungen/<slug>/einbetten`, nur von den Websites
  in `einbetten_von` (`instanzen/<instanz>/config.php`, nur https).

HTML-Schnipsel für WordPress und Ablauf am Livetag: docs/livegang.md
„Anmeldungen vor dem Livegang“. Geprüft wird der ganze Weg in
`tests/Feature/Events/WeihnachtsaktionMailsTest.php`.

## Beispiel: Weihnachtspostkartenaktion 2026 (DV Trier)

Die Inhaltsmigration
`instanzen/dv-trier/migrations/2026_11_09_160000_weihnachtspostkartenaktion_2026.php`
legt die Veranstaltung `/veranstaltungen/weihnachtspostkartenaktion-2026` an
(Anmeldung bis 23.11.2026, Daten 60 Tage nach Ende gelöscht), eine zweite
Migration schaltet „Vorab extern freigeben“ ein, eine dritte trägt Lisa Alm
(Bildungsreferentin) als Verantwortliche ein. Danach wird sie im Backend
gepflegt, eine erneute Migration ändert nichts.

## Fotos zur Veranstaltung (Vier-Augen)

Reiter **Fotos** beim Bearbeiten einer Veranstaltung, für alle, die ihre
Teilnehmenden verwalten dürfen:

- **Fotos hochladen** (bis 30 auf einmal, je 15 MB) mit „Foto von“ und der
  Bestätigung, dass die erkennbar Abgebildeten eingewilligt haben (bei
  Minderjährigen die Personensorgeberechtigten). Bis zur Freigabe liegen die
  Bilder privat (`storage/app/private/event-fotos`), sichtbar nur im Backend.
- **Freigeben** darf nur eine **andere Person** als die, die hochgeladen hat
  (Vier-Augen). Dann wird das Bild ein Medium (Mediathek) und erscheint als
  Galerie unter „Fotos“ auf der Veranstaltungsseite, mit Bildnachweis.
- Je Foto: Alternativtext, Reihenfolge (ziehen), **Ausblenden** (wieder
  freigeben holt es zurück), Löschen (entfernt auch das Medium).
- Der Reiter zeigt die Zahl der Fotos, die auf Freigabe warten.
## Liste für unterwegs (PDF)

Unter „Teilnehmende“ → **Liste für unterwegs (PDF)**: zum Ausdrucken für
Lager und Fahrten, wenn kein Netz da ist. Bestätigte Teilnehmende
alphabetisch (auf Wunsch mit Warteliste), wählbare Spalten (Vorschlag:
Geburtsjahr, Stamm, Telefon; lange Freitexte nicht), die
personensorgeberechtigte Person und auf Wunsch leere Kästchen für die
Anwesenheit je Tag. Mit dem Hinweis „Vertraulich, nach der Veranstaltung
vernichten“; jeder Abruf wird protokolliert („Liste für unterwegs (PDF)
abgerufen“).

## Küchenliste (PDF)

Unter „Teilnehmende“ → **Küchenliste (PDF)**: zum Aushängen in der Küche.
Datensparsam – nur, was die Küche braucht:

- **Essen je Tag**: bestätigte Teilnehmende je Tag (ohne Tage-Auswahl zählt
  die ganze Zeit; Warteliste nicht).
- **Verpflegung je Tag**: Verteilung jedes gewählten Auswahlfelds (z. B.
  „Esse alles / Vegetarisch / Vegan“), bei mehrtägigen Veranstaltungen je Tag.
- **Besonderheiten**: nur Personen, die etwas Besonderes angegeben haben
  (Freitext ausgefüllt oder bei Auswahlfeldern nicht die erste Antwort), mit
  Vorname und Anfangsbuchstabe (auf Wunsch voller Name) und ggf. Stamm.

Vorausgewählt sind „Verpflegung“ und Felder, deren Beschriftung nach Essen
klingt (Allergie, Unverträglichkeit, vegan …); änderbar im Dialog. Keine
E-Mail, kein Telefon, kein Geburtsjahr, keine anderen Angaben. Sensible Felder
dürfen hinein – genau dafür sind sie da –, die Liste trägt den Hinweis „Nur in
der Küche aushängen, nach der Veranstaltung vernichten“. Nur mit „Veranstaltungen
bearbeiten“; jeder Abruf wird protokolliert („Küchenliste heruntergeladen“).
