# Materialverleih

Funktionen unter Verwaltung → Funktionen (Standard: aus):

- **Materialverleih**: Backend-Bereich „Materialverleih“ mit Material und
  Reservierungen.
- **Materialverleih: Anfragen über die Website**: öffentliche Seite
  `/material` mit Liste und Anfrageformular.

## Wer

Rolle **Materialwart*in** (Benutzer → Rollen) und die Verwaltung (Admins
ebenso). Anfragen von der Website gehen per Mail an alle Materialwart*innen,
ohne sie an die Verwaltung.

## Material

Name, Kategorie, Anzahl, Lagerort, Beschreibung (öffentlich), interne
Hinweise, Bild, „Kann ausgeliehen werden“. „Belegung“ zeigt je Material einen
Monatskalender: weiß frei, gelb teilweise, rot alles vergeben, blauer Punkt
offene Anfrage. Darunter die Reservierungen des Monats.

## Reservierungen

Status: **angefragt → zugesagt → ausgegeben → zurück** (oder abgesagt).

- Reiter: Anfragen · Zugesagt · Ausgegeben · Erledigt.
- **Zusagen** prüft die Belegung: Zugesagte und ausgegebene Reservierungen
  zählen am vollsten Tag des Zeitraums gegen die Anzahl. Ist nichts mehr frei,
  wird nicht zugesagt. Dasselbe gilt beim Anlegen oder Bearbeiten mit Status
  „zugesagt“ oder „ausgegeben“.
- Zusage und Absage gehen per Mail an die Person (Absage optional mit
  Nachricht). „Zurück“ fragt nach Mängeln.
- Anfragen von der Website mit Spamverdacht (Link, fehlender
  Arbeitsnachweis) landen ohne Mail im Backend, markiert als „Spamverdacht“.

## Täglich (`material:taeglich`, 08:35)

- Am Tag vor dem Rückgabedatum eine Erinnerung an ausgegebene Reservierungen
  (einmal).
- Erledigte (zurück/abgesagt) werden 6 Monate nach dem Ende gelöscht.

## Datenschutz

Datenschutzauskunft und -löschung (Verwaltung → Datenschutz) erfassen
Reservierungen zur E-Mail-Adresse. Vor dem Einschalten der Anfragen über die
Website die Datenschutzerklärung um den Materialverleih ergänzen.
