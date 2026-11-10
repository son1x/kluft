# Plan: Was Ehrenamtlichen im Alltag hilft

Stand 10.11.2026. Dreizehn Vorschläge aus der Durchsicht des Codes aus Sicht
von Stammesleitungen, Leitenden, Kasse und Eltern. Reihenfolge = Umsetzung:
erst die schnellen Gewinne (1–5), dann die großen Entlastungen (6–10), zuletzt
„gut zu haben“ (11–13). Jeder Punkt kommt als eigener PR mit Tests in allen
drei Instanzen; neue Funktionen hängen, wo es passt, an einem Schalter unter
Verwaltung → Funktionen.

Bewusst nicht: Gesundheitsdaten bei Anmeldungen (Art. 9 DSGVO / § 11 KDG) und
ein eigenes Sitzungsprotokoll-System (dafür ist die Nextcloud da).

---

## 1. Nachricht an alle Angemeldeten, Erinnerung vor der Veranstaltung

- **Problem:** Treffpunkt geändert, Packliste, Absage – heute kopiert man die
  Adressen aus dem Export ins private Postfach.
- **Weg:**
  - Reiter „Teilnehmende“ → Aktion **„Nachricht an Teilnehmende“**: Empfänger
    nach Status (bestätigt, Warteliste, unbestätigt), Betreff, Text, optional
    Anhang aus der Mediathek. Versand über die Warteschlange, je Person eine
    Mail (keine sichtbaren Adressen), Antwort-An = Kontakt der Veranstaltung.
  - **Erinnerung:** je Veranstaltung „Erinnerung … Tage vorher“ (aus, 1–14) mit
    eigenem Text; ein täglicher Befehl schickt sie an alle Bestätigten, einmal.
  - Protokoll (wer, wann, an wie viele), Kopie an die Sammeladresse optional.
  - Test-Umleitung und „Vorab extern freigeben“ gelten wie bei allen Mails.
- **Daten:** `event_nachrichten` (Betreff, Text, Empfängerzahl, gesendet_von,
  gesendet_am); `events.erinnerung_tage`, `erinnerung_text`, `erinnerung_am`.

## 2. Kalender-Abo (ICS)

- **Problem:** Termine stehen auf der Website, aber nicht im Handy-Kalender.
- **Weg:** `/kalender.ics` (alles) und `/kalender/<stufe>.ics`, dazu
  Veranstaltungen mit Anmeldung als eigene Einträge (Link zur Anmeldung).
  Knopf „Kalender abonnieren“ (webcal://) auf Kalender-, Stufen- und
  Veranstaltungsseite. Nur öffentliche Daten, 15 Minuten zwischengespeichert.

## 3. Suche auf der Website

- **Weg:** `/suche?q=…` über veröffentlichte Seiten, Beiträge, Veranstaltungen,
  Downloads, Glossar (und Georgsbote, wo an). Datenbankabfrage mit Gewichtung
  (Titel vor Text), Treffer mit Auszug und Hervorhebung. Suchfeld im Kopf
  (Lupe), ohne JavaScript nutzbar. Keine Speicherung der Suchbegriffe.

## 4. Terminfindung in Umfragen

- **Weg:** neuer Fragetyp „Terminfindung“: Vorschläge (Datum, Uhrzeit), je
  Vorschlag ja / wenn nötig / nein. Auswertung als Tabelle mit Summen, bester
  Termin markiert, Export. Optional: „Ergebnis den Teilnehmenden zeigen“.

## 5. Rollen mit Ablaufdatum

- **Weg:** `role_assignments.gueltig_bis` (optional). Beim Vergeben „bis“
  setzen (z. B. Ende der Amtszeit). 30 und 7 Tage vorher Hinweis an die
  Person und die Verwaltung; am Tag danach wird die Rolle entfernt
  (Protokoll). „Meine Rollen“ zeigt das Datum.

## 6. Präventions-Nachweise der Leitenden

- **Weg:** Neue Ressource „Prävention“ (nur Vorstand/Verwaltung bzw. für den
  eigenen Bereich): je Person **erweitertes Führungszeugnis** (eingesehen am,
  gültig bis = +5 Jahre), **Präventionsschulung** (Art, Datum, gültig bis),
  **Juleica** (gültig bis). Gespeichert werden nur Daten, **keine Dokumente,
  keine Inhalte des Führungszeugnisses**. Ampel in der Liste, Erinnerung
  90/30 Tage vorher an Person und Vorstand. Personen-Anbindung über
  `people`/`users`. Löschfrist: Ende der Tätigkeit + 1 Jahr (Bistumsregel prüfen).

## 7. Auslagen und Fahrtkosten erstatten

- **Weg:** Formular (Backend, alle mit Konto): Anlass, Betrag oder Kilometer
  (Satz einstellbar, z. B. 0,30 €/km), Belege als Foto/PDF, IBAN (verschlüsselt,
  einmal im Profil). Kasse prüft, gibt frei oder lehnt mit Grund ab;
  „ausgezahlt am“. Sammel-Überweisung als SEPA-XML bzw. Liste. Belege privat
  gespeichert, Löschfrist nach Steuerrecht (10 Jahre) nur für Belege, IBAN nur
  solange nötig. Rolle „Kasse“ (gibt es schon).

## 8. Materialverleih

- **Weg:** Ressource „Material“ (Name, Kategorie, Bild, Menge, Ort,
  Hinweise) und Reservierungen (von–bis, für wen, Status angefragt/zugesagt/
  ausgegeben/zurück, Mängel). Belegungskalender je Material, Konfliktprüfung.
  Anfrage öffentlich per Formular (mit Spamschutz) oder nur im Backend
  (Schalter). Mail an die Materialwart*in.

## 9. Familienanmeldung

- **Weg:** Im Anmeldeformular „Weitere Person anmelden“: mehrere Teilnehmende
  mit denselben Kontaktangaben, je Person Name, Geburtsjahr, Felder aus dem
  Baukasten. Eine Bestätigungsmail für alle; je Person eine Anmeldung (Liste,
  Export, Zahlungen bleiben wie sie sind). Höchstens 6 auf einmal.

## 10. „Was steht an“ im Backend

- **Weg:** Widget auf der Startseite, je nach Rolle: unbestätigte Anmeldungen
  eigener Veranstaltungen, offene Zahlungen, Zuschussfristen, auslaufende
  Rollen und Nachweise (5, 6), offene Auslagen (7) und Reservierungen (8),
  wartende Freigaben. Jeweils mit Link.

## 11. Fotogalerie zur Veranstaltung mit Freigabe

- **Weg:** An der Veranstaltung „Fotos“: Leitende laden hoch, eine zweite
  Person mit Recht gibt frei (Vier-Augen). Hinweis auf Foto-Einwilligung
  (Einwilligung der Abgebildeten), Bilder einzeln ausblendbar. Öffentlich als
  Galerie auf der Veranstaltungsseite (vorhandene `Galerie`-Komponente).

## 12. Teilnehmendenliste für unterwegs

- **Weg:** „Liste drucken (PDF)“ im Reiter Teilnehmende: Name, Geburtsjahr,
  Stamm, Telefon der Sorgeberechtigten, gewählte Felder; optional mit
  Anwesenheits-Spalten je Tag. Protokolliert wie der Export, Hinweis
  „vertraulich, nach der Veranstaltung vernichten“.

## 13. Barrierefreiheit beim Schreiben

- **Weg:** Prüfung beim Speichern von Seiten und Beiträgen (Hinweise, kein
  Zwang): Bilder ohne Alternativtext, sehr lange Sätze, Überschriften-
  Reihenfolge, Links „hier klicken“. Mediathek: Alternativtext als Pflicht für
  neue Bilder (außer „dekorativ“).

---

## Reihenfolge und Stand

| Nr. | Thema | Stand |
|---|---|---|
| 1 | Nachricht an Teilnehmende, Erinnerung | gebaut (`feature/teilnehmende-nachricht`) |
| 2 | Kalender-Abo | offen |
| 3 | Suche | offen |
| 4 | Terminfindung | offen |
| 5 | Rollen mit Ablauf | offen |
| 6 | Präventions-Nachweise | offen |
| 7 | Auslagen | offen |
| 8 | Materialverleih | offen |
| 9 | Familienanmeldung | offen |
| 10 | Was steht an | offen |
| 11 | Fotogalerie | offen |
| 12 | Liste für unterwegs | offen |
| 13 | Barrierefreiheit | offen |
