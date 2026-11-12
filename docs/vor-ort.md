# Vor Ort – Begleiter für die Durchführung von Veranstaltungen

Ein eigener Bereich unter **`/vor-ort`** für die Arbeit am Veranstaltungsort: Anwesenheit abhaken, sehen, wer bezahlt hat, Barzahlungen vermerken, Verpflegung im Blick behalten, Angaben korrigieren und Zusatzinfos wie Zimmer oder Zelt pflegen.

Gebaut für ein **Tablet des Büros** (quer: Liste links, Person rechts, beide scrollen getrennt); auf dem **Handy** als Ausweichgerät einspaltig. Alle Tippflächen mindestens 44 pt, keine seitliche Scrollleiste (Apple HIG, `docs/barrierefreiheit.md`).

Inhalt: [Einrichten](#einrichten) · [Bedienung](#bedienung) · [Zusatzinfos fürs Team](#zusatzinfos-fürs-team) · [Rechte](#wer-darf-was) · [Technik](#technik) · [Datenschutz](#datenschutz) · [Entscheidungen und offene Punkte](#entscheidungen-und-offene-punkte) · [Ausbauplan](#ausbauplan)

---

## Einrichten

1. **Funktion einschalten:** Verwaltung → Funktionen → **„Vor Ort (Begleiter für Veranstaltungen)“** (Standard: aus).
2. **Optional – Zahlungen:** Damit „bezahlt / offen“ und „bar erhalten“ erscheinen, braucht die Veranstaltung einen Teilnahmebeitrag (Preisstufen) und die Funktion „Zahlungen“ (`docs/zahlungen.md`).
3. **Optional – Zusatzinfos:** In der Veranstaltung unter **„Zusatzinfos fürs Team“** Felder wie „Zimmer“, „Zelt“ oder „Platz“ anlegen (siehe unten).
4. **Tablet vorbereiten:** `https://<domain>/vor-ort` im Browser öffnen, anmelden (Microsoft 365 oder E-Mail-Code) und als Lesezeichen bzw. „Zum Home-Bildschirm“ ablegen. Das Konto des Tablets braucht das Recht, die Teilnehmenden der Veranstaltung zu verwalten (z. B. ein Funktionskonto „Büro“ mit der passenden Rolle).

Aus dem Backend führt der Knopf **„Vor Ort“** auf der Bearbeiten-Seite einer Veranstaltung direkt dorthin.

## Bedienung

### Veranstaltung wählen

Die Startseite zeigt die Veranstaltungen, deren Teilnehmende man verwalten darf – laufende (Markierung „läuft gerade“), kommende der nächsten **60 Tage** und gerade beendete der letzten **7 Tage** (für Nachträge). Entwürfe erscheinen nicht.

### Oben: Tage und Überblick

- **Tagesreiter**: ein Reiter je Veranstaltungstag, „heute“ ist vorausgewählt.
- **„x von y da“**: wie viele der für diesen Tag Angemeldeten schon abgehakt sind.
- **„n noch nicht bezahlt“** (nur mit Teilnahmebeitrag).
- **Verpflegung in Summe**, z. B. „Vegetarisch: 3 · Vegan: 1“ (nur, wenn „Verpflegung“ abgefragt wird).

### Liste

- **Suche** nach Name – auch nach Zusatzinfos, z. B. „12“ findet alle in Zimmer 12.
- **Filter**: Alle · Noch nicht da · Nicht bezahlt.
- **Ordnen**: nach Name oder nach einer Zusatzinfo (z. B. „nach Zimmer“) – dann mit Zwischenüberschriften „Zimmer 12 · 3“, ohne Wert am Ende („Ohne Zimmer“).
- **„da?“-Knopf** rechts in jeder Zeile: hakt die Person für den gewählten Tag ab (grün „✓ da“); nochmal tippen nimmt den Haken zurück.
- In der Zeile: Jahrgang, Zusatzinfos, „heute nicht angemeldet“ (wer laut Tage-Auswahl an dem Tag nicht kommt) und der Zahlungsstand (grün bezahlt, rot offen mit Betrag).

### Person

Antippen öffnet die Person (Tablet: rechte Spalte; Handy: eigene Ansicht mit „← Liste“).

- **Anwesenheit**: alle Tage zum Abhaken; gestrichelt = an diesem Tag nicht angemeldet.
- **Zusatzinfos** (nur Team): Felder direkt bearbeiten, gespeichert beim Verlassen des Felds oder mit Enter. Darunter „Mit dabei: …“ – alle mit dem gleichen Wert (z. B. die Zimmermitbewohnenden).
- **Teilnahmebeitrag**: Stand und **„45,00 € bar erhalten“** (mit Rückfrage). Bucht den offenen Betrag als Zahlungseingang.
- **Angaben**: alle abgefragten Angaben; sensible sind markiert. **„Korrigieren“** öffnet ein Formular für Name, Verpflegung, Tage, Anmerkungen, eigene Felder. Die Person bekommt keine Mail.

### Verbindungsanzeige (oben rechts)

| Anzeige | Bedeutung |
|---|---|
| Verbunden | alles gespeichert |
| n werden gesendet … | Änderungen sind unterwegs |
| Offline · n warten | keine Verbindung; die Änderungen werden automatisch nachgesendet, sobald das Netz zurück ist – **die Seite dafür offen lassen** (noch nicht neu laden, siehe Ausbauplan) |

Lehnt Kluft eine Änderung ab (z. B. Anmeldung inzwischen storniert), erscheint ein Hinweis mit Grund, und die Daten werden neu geladen.

## Zusatzinfos fürs Team

Für Angaben, die **nicht** im Anmeldeformular stehen, sondern das Team vergibt: Zimmer, Zelt, Platz(nummer), Gruppe, Bus …

- **Anlegen**: Veranstaltung bearbeiten → Abschnitt **„Zusatzinfos fürs Team“** → Felder eingeben (Enter oder Komma), höchstens 6, Reihenfolge per Ziehen.
- **Eintragen**: unter „Vor Ort“ bei der Person oder im Backend in der Teilnehmendenliste über **„Angaben bearbeiten“**; dort gibt es außerdem je Feld eine (ein-/ausblendbare) Spalte.
- **Zusammengehörige**: Wer den gleichen Wert hat, gehört zusammen – „Mit dabei: …“ bei der Person, „Ordnen: nach Zimmer“ in der Liste. Werte werden genau verglichen („12“ ≠ „Zimmer 12“); am besten nur die Nummer bzw. den Namen eintragen.
- **Umbenennen**: Der interne Schlüssel kommt aus der Bezeichnung. Wird ein Feld umbenannt, sind die alten Werte darunter nicht mehr zu sehen (bei Rückbenennung wieder).
- Zusatzinfos sehen die Teilnehmenden nicht; sie stehen nicht im Export.

## Wer darf was

Wie die Teilnehmendenliste im Backend (`EventPolicy::manageParticipants`): Wer die Teilnehmenden einer Veranstaltung verwalten darf (z. B. „Veranstaltungen bearbeiten – Wölflinge“, Stufenadmins je nach Instanz, Bildungsreferent*innen, Verwaltung, Admins), sieht sie unter „Vor Ort“ und kann dort alles oben Beschriebene. Wer nur lesen darf, sieht die Veranstaltung nicht.

Gezeigt werden nur **bestätigte** Anmeldungen (keine Warteliste, keine stornierten).

Im Backend gibt es parallel bei jeder Anmeldung **„Angaben bearbeiten“** (Name, E-Mail, alle Angaben, Zusatzinfos) – gleiches Recht.

## Technik

### Aufbau

| Teil | Datei |
|---|---|
| Seite (Blade, eigenes schlankes Layout) | `resources/views/vor-ort/app.blade.php` |
| Oberfläche (Alpine.js, eigener Vite-Einstieg, CSP-konform ohne Inline-Skript) | `resources/js/vor-ort.js` |
| Schnittstelle | `app/Http/Controllers/VorOrtController.php`, Routen in `routes/web.php` (`vor-ort.*`) |
| Logik (Daten, Ereignisse, Prüfungen) | `app/Support/VorOrt.php` |
| Ereignis-Protokoll | `app/Models/VorOrtEreignis.php` |
| Schalter | `Features::VOR_ORT` |
| Tests | `tests/Feature/Events/VorOrtTest.php`, `AnmeldungBearbeitenTest.php` |

### Schnittstelle (nur angemeldet, `throttle:240,1`)

| Methode und Pfad | Inhalt |
|---|---|
| `GET /vor-ort` | die Seite |
| `GET /vor-ort/api/veranstaltungen` | betreubare Veranstaltungen (Titel, Datum, läuft gerade, Anzahl bestätigt) |
| `GET /vor-ort/api/veranstaltungen/{id}` | alles zur Veranstaltung: Tage, Felder, Zusatzfelder, Verpflegung in Summe, Personen mit erwarteten Tagen, Anwesenheit, Zahlung, Angaben, Zusatzinfos |
| `POST /vor-ort/api/ereignisse` | bis zu 200 Ereignisse; Antwort je Kennung `ok`, `doppelt` oder `abgelehnt` mit Grund |

Ohne eingeschaltete Funktion antwortet alles mit 404; ohne Anmeldung leitet `/vor-ort` zur Anmeldung.

### Ereignisse statt Direktänderungen

Jede Änderung schickt die Oberfläche als **Ereignis** mit eigener Kennung (UUID, auf dem Gerät erzeugt) und Gerätezeit:

```json
{"ereignisse": [
  {"id": "6f1c…", "typ": "anwesend", "anmeldung": 42, "daten": {"tag": "2027-05-15", "da": true}, "zeit": "2027-05-15T09:12:00+02:00"}
]}
```

| Typ | Daten | Prüfung | Wirkung |
|---|---|---|---|
| `anwesend` | `tag`, `da` (ja/nein) | Tag gehört zur Veranstaltung | Spalte `event_registrations.vor_ort_tage` |
| `bar` | `cent` | Funktion „Zahlungen“, offene Forderung, Betrag > 0 und ≤ offen | `Zahlung::eingang()` mit Notiz „Bar vor Ort“, erfasst von der Person; Protokoll `vor_ort.bar` |
| `angaben` | `first_name`, `last_name`, `data` (nur abgefragte Felder) | Name nicht leer; Auswahl/Tage/Jahr/Zahl gültig | Anmeldung; Protokoll `vor_ort.angaben` |
| `zusatz` | `feld`, `wert` | Feld gehört zur Veranstaltung, ≤ 100 Zeichen | Spalte `event_registrations.zusatz` (leer = entfernen); Protokoll `vor_ort.zusatz` |

Für alle gilt: Recht `manageParticipants`, Anmeldung bestätigt. Jede Kennung wird **genau einmal** verarbeitet (eindeutiger Index), erneutes Senden ergibt `doppelt`. Damit ist Offline vorbereitet: Haken und Zahlungen können sich beim Nachsenden nicht widersprechen; bei Angaben gilt die zuletzt verarbeitete Änderung.

Die Oberfläche wendet Änderungen sofort an (optimistisch), legt sie in eine Warteschlange, sendet in Paketen bis 50 und wiederholt automatisch (alle 3 s bei offener Warteschlange, sonst alle 15 s und beim Wiederverbinden).

### Datenmodell

| Tabelle / Spalte | Inhalt |
|---|---|
| `event_registrations.vor_ort_tage` | JSON-Liste der Tage, an denen die Person vor Ort abgehakt ist |
| `event_registrations.zusatz` | JSON `{schlüssel: wert}` der Zusatzinfos |
| `events.zusatz_felder` | JSON-Liste der Bezeichnungen (Schlüssel = `Str::slug(Bezeichnung, '_')`) |
| `vor_ort_ereignisse` | `uuid` (eindeutig), Veranstaltung, Anmeldung, Person, `typ`, `daten` (verschlüsselt), `ergebnis` (ok/abgelehnt), `erfasst_am` (Gerätezeit) |

Migrationen: `2026_11_13_100000_create_vor_ort`, `2026_11_13_110000_add_zusatz_felder`.

`vor_ort_tage` ist bewusst getrennt von `anwesend`/`anwesend_tage` des Zuschussantrags (dort bedeutet „leer“ = alle Tage). Eine Übernahme „Anwesenheit aus Vor Ort in den Zuschussantrag“ ist mit der Unterschriften-Stufe geplant.

## Datenschutz

- **Gerät**: Stufe 1 speichert nichts dauerhaft auf dem Gerät; die Daten kommen bei jedem Öffnen frisch vom Server. Die Warteschlange lebt nur, solange die Seite offen ist.
- **Ereignisse** enthalten nur, was geändert wurde (verschlüsselt gespeichert). Sensible Angaben (z. B. Allergien) stehen dort nicht im Klartext, sondern nur „(sensibel, geändert)“. Die Ereignisse werden mit der Anmeldung gelöscht (Löschfristen der Veranstaltung).
- **Protokoll**: Barzahlungen, Korrekturen und Zusatzinfos stehen mit Namen im Protokoll (`vor_ort.*`, `registration.bearbeitet`).
- **Anzeige**: Sensible Angaben zeigt „Vor Ort“ wie die Detailansicht im Backend, gekennzeichnet – für dieselben Personen.
- Seite mit `noindex`, gleiche Sicherheits-Header (CSP) wie die Website.

Für die Datenschutzbewertung (`docs/datenschutz-bewertung.md`): keine neue Datenkategorie und kein neuer Empfänger; neu ist der Verarbeitungsort Tablet/Handy des Teams. Mit Stufe 3 (offline) kommt eine Speicherung auf dem Gerät hinzu – dann Bewertung ergänzen.

## Entscheidungen und offene Punkte

| Thema | Stand | Offen |
|---|---|---|
| Wer darf bar verbuchen? | alle mit `manageParticipants` (Team vor Ort kassiert), protokolliert mit Namen | Soll das nur die Kasse dürfen? |
| Eigene App oder Bereich in Kluft? | Bereich in Kluft (gleiche Anmeldung, Rechte, Daten; kein App-Store) | – |
| Offline nötig? | ja – ein zentraler Platz ist ohne Internet | Stufe 3 |
| Digitale Unterschriften | werden laut Rückmeldung anerkannt, oft gewünscht | Stufe 2 |
| Wartende/stornierte Anmeldungen | nicht in „Vor Ort“ | ggf. Nachmeldung vor Ort als eigene Funktion |
| Gerät | Tablet des Büros, Handy als Ausweich | – |

## Ausbauplan

1. ~~**Stufe 1 – online**: Auswahl, Anwesenheit je Tag, Zahlungsstand und Barzahlung, Verpflegung, Angaben korrigieren, Zusatzinfos.~~ (PR „Vor Ort, Stufe 1“)
2. **Stufe 2 – digitale Unterschrift** je Person und Tag (mit dem Finger auf dem Tablet), gespeichert als Bild, eingesetzt in die Zuschuss-Teilnahmeliste (PDF) an Stelle der Unterschriftsspalte; Übernahme der Anwesenheit in den Zuschussantrag.
3. **Stufe 3 – installierbar und offline**: Web-App-Manifest und Service Worker, Liste der gewählten Veranstaltung auf dem Gerät, Warteschlange dauerhaft (IndexedDB), Nachsenden beim Wiederverbinden, Anzeige „Stand von …“.
4. **Stufe 4 – Datenschutz offline**: Gerätespeicher verschlüsselt, Löschen beim Abmelden und nach der Veranstaltung, sensible Angaben offline nur nach ausdrücklicher Freigabe, Ergänzung der Datenschutzbewertung.
