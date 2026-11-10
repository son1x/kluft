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
