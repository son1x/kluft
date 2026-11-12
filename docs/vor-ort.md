# Vor Ort – Begleiter für die Durchführung von Veranstaltungen

Ein eigener Bereich unter **`/vor-ort`** für die Arbeit am Veranstaltungsort: Anwesenheit abhaken, sehen, wer bezahlt hat, Barzahlungen vermerken, Verpflegung und Angaben korrigieren. Gebaut für ein **Tablet des Büros** (zwei Spalten: Liste links, Person rechts); auf dem **Handy** als Ausweichgerät einspaltig.

Schalter: Verwaltung → Funktionen → **„Vor Ort (Begleiter für Veranstaltungen)“** (Standard aus).

## Wer darf was

Wie in der Teilnehmendenliste im Backend (`EventPolicy::manageParticipants`): Wer die Teilnehmenden einer Veranstaltung verwalten darf, sieht sie unter „Vor Ort“ und kann dort arbeiten. Wer nur lesen darf, sieht die Veranstaltung nicht. Angemeldet wird wie im Backend (Microsoft 365 oder E-Mail-Code).

Angezeigt werden Veranstaltungen, die gerade laufen, in den nächsten 60 Tagen beginnen oder vor höchstens 7 Tagen geendet haben (Nachträge).

## Was es kann (Ausbaustufe 1, online)

| Aufgabe | Wirkung |
|---|---|
| **Anwesenheit** je Tag abhaken | Spalte `vor_ort_tage` der Anmeldung; Zähler „da / erwartet“ je Tag |
| **Bezahlt?** | Zahlungsstand aus „Zahlungen“ (offen, teilweise, bezahlt) mit offenem Betrag |
| **Bar bezahlt** vermerken | Zahlungseingang an der Forderung (Notiz „Bar vor Ort“, mit Namen der erfassenden Person) – nur wenn es eine Forderung gibt |
| **Angaben korrigieren** | Name und abgefragte Angaben (z. B. Verpflegung, Tage, Anmerkungen) wie „Angaben bearbeiten“ im Backend |
| Überblick | Suche, Filter „noch nicht da“ / „offen bezahlt“, Verpflegung in Summe |

Gezeigt werden nur **bestätigte** Anmeldungen.

## Ereignisse statt Direktänderungen

Jede Änderung schickt die Oberfläche als **Ereignis** mit eigener Kennung (UUID) an `POST /vor-ort/api/ereignisse`, z. B. „Mia ist am 15.5. da“ oder „45 € bar“. Kluft verarbeitet jedes Ereignis genau einmal (doppelte Kennung → „schon verbucht“) und speichert es in `vor_ort_ereignisse` (Inhalt verschlüsselt).

Damit ist die nächste Ausbaustufe vorbereitet: Ohne Netz (zentraler Platz ohne Internet) sammelt das Gerät die Ereignisse und schickt sie, sobald wieder Netz da ist. Haken und Zahlungen können sich dabei nicht widersprechen; bei Angaben gilt die zuletzt verarbeitete Änderung, das Protokoll zeigt, wer was geändert hat.

| Typ | Daten | Prüfung |
|---|---|---|
| `anwesend` | `tag` (YYYY-MM-DD), `da` (ja/nein) | Tag gehört zur Veranstaltung |
| `bar` | `cent` | offene Forderung vorhanden, Betrag > 0 |
| `angaben` | `first_name`, `last_name`, `data` (nur abgefragte Felder) | wie im Backend |

## Datenschutz

- Ausbaustufe 1 speichert **nichts dauerhaft auf dem Gerät**; die Daten kommen bei jedem Öffnen frisch vom Server.
- Die Ereignisse enthalten nur, was geändert wurde, und werden mit der Anmeldung gelöscht (Löschfristen der Veranstaltung).
- Sensible Angaben (z. B. Gesundheit) zeigt „Vor Ort“ wie die Detailansicht im Backend, gekennzeichnet.
- Jede Barzahlung und jede Änderung der Angaben steht im Protokoll.

## Ausbau (geplant)

1. **Digitale Unterschrift** je Person und Tag, übernommen in die Zuschuss-Teilnahmeliste (PDF).
2. **Installierbar und offline**: Liste offline lesbar, Ereignisse offline sammeln und nachsenden.
3. **Datenschutz offline**: Gerätespeicher verschlüsselt, Löschen beim Abmelden und nach der Veranstaltung, sensible Angaben offline nur nach ausdrücklicher Freigabe.
