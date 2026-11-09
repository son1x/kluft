# Veranstaltungen und Anmeldung

Backend → Veranstaltungen. Anforderungen: Pflichtenheft 6.7 (F-EV-01 ff.).
Hier steht, was beim Pflegen einer Anmeldung wichtig ist.

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
dem Formular. Die Kontaktperson der Veranstaltung bekommt keine Mail je
Anmeldung. Sie sieht die Anmeldungen im Backend.

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
- Die Mails dieser Veranstaltung gehen an die echten Adressen. Erkannt an der
  Kopfzeile `X-Kluft-Extern`, die vor dem Versand entfernt wird.
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
Migration schaltet „Vorab extern freigeben“ ein. Danach wird sie im Backend
gepflegt, eine erneute Migration ändert nichts.
