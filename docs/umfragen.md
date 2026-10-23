# Umfragen

Funktion **„Umfragen“** (Verwaltung → Funktionen; beim DV Trier an). Im
Backend unter **Verband & Kontakt → Umfragen**. Pflichtenheft:
`docs/pflichtenheft-umfragen.md`.

## Wer darf was

- **Anlegen, bearbeiten, auswerten:** alle ab Diözesanleitung, also die
  Rollen Stufenleitung (`stufenadmin`), Fachbereichsleitung
  (`sektionsadmin`), Vorstand, Verwaltung und Admins.
- **Sehen:** Alle mit diesem Recht sehen alle Umfragen samt Antworten.
  Autor*innen sehen keine.

## Umfrage anlegen

- **Neu:** **„Neue Umfrage“** (leer) oder **„Aus Vorlage“**. Mitgeliefert
  sind: Feedback zur Veranstaltung, Feedback zur Fahrt/zum Lager,
  Leiter*innenbefragung und Nachfrage an die Teilnehmenden. Dazu kommen
  eigene Vorlagen.
- **Fragetypen:**
  - Einfach- und Mehrfachauswahl (optional „Sonstiges: ___“, Mindest- und
    Höchstzahl);
  - Skala (0/1 bis 2–10, mit Beschriftung der Enden);
  - Bewertung („sehr gut … gar nicht gut“, eigene Stufen möglich,
    optional eine Antwort ohne Wertung wie „nicht teilgenommen“);
  - Ja/Nein, kurzer und langer Text;
  - **Abschnitt** (Überschrift mit Text).
- **Kommentarfeld dazu:** Auswahl, Skala, Bewertung und Ja/Nein bekommen auf
  Wunsch ein freies Feld („Hier hast du Platz für eigene Gedanken …“).
  Dafür braucht es keine eigene Frage.
- **Nur zeigen, wenn …:** Eine Frage erscheint nur, wenn bei einer früheren
  Auswahlfrage bestimmte Antworten gewählt sind (z. B. Sammelort nur bei
  „Bus“). Die Bedingungen funktionieren auch verschachtelt. Ohne JavaScript
  sind alle Fragen sichtbar; Antworten auf ausgeblendete Fragen verwirft
  der Server.
- **Status:**
  - *Entwurf:* nicht erreichbar, aber mit „Vorschau“ ansehen;
  - *Offen:* nimmt Antworten an, optional nur im Zeitraum „Offen ab/bis“;
  - *Geschlossen:* zeigt „beendet“;
  - *Archiviert:* nicht erreichbar, steht im Filter „Archiv“.
- **Sperre:** Sobald es Antworten gibt, lassen sich Art und
  Antwortmöglichkeiten bestehender Fragen nicht mehr ändern, und Fragen
  nicht mehr löschen. Texte bleiben änderbar.

In der Liste unter dem Menü (⋯) jeder Umfrage:
- **Link anzeigen**
- **Kopieren**: neue Umfrage mit denselben Fragen, ohne Antworten
- **Als Vorlage speichern**
- **Teilnehmende einladen**
- **Löschen**

## Teilnahme

- **Öffentlicher Link** `/umfrage/<code>`, auf Wunsch mit **Zugangscode**.
  Nach dem Absenden ist die Umfrage in diesem Browser erledigt (Cookie,
  kein harter Schutz); mit „Mehrfach aus einem Browser“ abschaltbar.
- **Nachfrage an Teilnehmende einer Veranstaltung:** bei „Nur für
  Teilnehmende einer Veranstaltung“ die Veranstaltung wählen.
  - Es gibt dann keinen öffentlichen Link.
  - **„Teilnehmende einladen“** schickt allen bestätigten Anmeldungen mit
    Mail-Adresse einen persönlichen Link, jede Person nur einmal. Später
    Angemeldete lädt ein erneuter Klick nach.
  - Jede Person kann einmal antworten.
  - **Nicht anonym:** Die Antwort hängt an der Anmeldung (Name und Mail
    stehen in der Auswertung und im Export); Name und Mail muss man nicht
    abfragen.
  - **Anonym:** Kluft merkt sich nur, *dass* jemand teilgenommen hat.
- **Spamschutz** wie beim Kontaktformular, dazu eine Begrenzung je Adresse.

## Gewinnspiel

„Nach dem Absenden zum Gewinnspiel einladen“ zeigt auf der Dankeseite ein
eigenes Formular für Name und Mail.

- **Getrennt gespeichert:** Die Einträge liegen in einer eigenen Tabelle,
  ohne Verweis auf die Antworten. Die Umfrage bleibt anonym.
- **Nur nach dem Absenden:** Teilnehmen kann nur, wer gerade die Umfrage
  abgeschickt hat, und nur einmal.
- **Auslosen:** In der Auswertung, Reiter „Gewinnspiel“: **„Gewinner*in
  auslosen“** (zufällig, wiederholbar) und **„Alle Einträge löschen“**.
- **Löschung:** spätestens drei Monate nach dem Ende automatisch.

## Auswertung

**Auswertung** (Klick auf die Umfrage):
- **Zusammenfassung:**
  - je Frage Balken mit Anzahl und Prozent;
  - Skala und Bewertung mit Mittelwert (Bewertung: 1 = beste Stufe; „nicht
    teilgenommen“ zählt nicht mit);
  - Freitexte, „Sonstiges“ und Kommentare als Liste;
  - bei bedingten Fragen, wie oft sie gezeigt wurden.
- **Einzelne Antworten:** alle Antworten einer Teilnahme nacheinander
  (Zurück/Weiter); bei nicht anonymen Umfragen mit Name und Mail.
- **PDF (A4):** die Zusammenfassung mit Balken für Gremien und Berichte.
- **Excel:**
  - eine Zeile je Teilnahme, eine Spalte je Frage, dazu Spalten für
    „Sonstiges“ und Kommentare;
  - Formeln aus Freitexten werden entschärft.

## Datenschutz

- **Anonyme Umfragen:** Gespeichert werden die Antworten (verschlüsselt) und
  der Tag, keine Uhrzeit, kein Name, keine Mail- oder IP-Adresse. Die Seite
  sagt das den Teilnehmenden.
- **Nicht anonyme Umfragen:** Name und Mail (bzw. die Anmeldung) stehen an
  der Antwort, mit Hinweis auf der Seite. Sie sind in der
  Datenschutzauskunft (Verwaltung → Datenschutz-Anfragen: finden, Export,
  Löschen) enthalten, ebenso Gewinnspiel-Einträge.
- **Löschfristen:** Antworten nach „Antworten löschen nach (Monaten)“
  (Standard 12) ab dem Ende bzw. der letzten Änderung; Gewinnspiel drei
  Monate nach dem Ende. Der Befehl `umfragen:aufraeumen` läuft täglich.
- **Vor dem Einschalten** die Datenschutzerklärung um Umfragen ergänzen.
