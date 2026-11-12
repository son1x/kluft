# Vor Ort – Begleiter für die Durchführung von Veranstaltungen

Ein eigener Bereich unter **`/vor-ort`** für die Arbeit am Veranstaltungsort: Anwesenheit abhaken, sehen, wer bezahlt hat, Zahlungen vor Ort kassieren (bar, Karte, PayPal), Unterschriften für die Zuschuss-Teilnahmeliste einsammeln, Verpflegung im Blick behalten, Angaben korrigieren und Zusatzinfos wie Zimmer oder Zelt pflegen.

Gebaut für ein **Tablet des Büros** (quer: Liste links, Person rechts, beide scrollen getrennt); auf dem **Handy** als Ausweichgerät einspaltig. Alle Tippflächen mindestens 44 pt, keine seitliche Scrollleiste (Apple HIG, `docs/barrierefreiheit.md`).

Inhalt: [Einrichten](#einrichten) · [Bedienung](#bedienung) · [Unterschrift](#digitale-unterschrift-zuschuss-teilnahmeliste) · [Zusatzinfos fürs Team](#zusatzinfos-fürs-team) · [Zahlungsarten](#zahlungsarten-beim-kassieren) · [Rechte](#wer-darf-was) · [Technik](#technik) · [Datenschutz](#datenschutz) · [Entscheidungen und offene Punkte](#entscheidungen-und-offene-punkte) · [Ausbauplan](#ausbauplan)

---

## Einrichten

1. **Funktion einschalten:** Verwaltung → Funktionen → **„Vor Ort (Begleiter für Veranstaltungen)“** (Standard: aus).
2. **Optional – Zahlungen:** Damit „bezahlt / offen“ und „… € erhalten“ erscheinen, braucht die Veranstaltung einen Teilnahmebeitrag (Preisstufen) und die Funktion „Zahlungen“ (`docs/zahlungen.md`). Welche Zahlungsarten zur Wahl stehen, legt die Instanz fest (siehe [Zahlungsarten](#zahlungsarten-beim-kassieren)).
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
- **„x von y unterschrieben“** (nur mit Zuschussantrag).
- **Verpflegung in Summe**, z. B. „Vegetarisch: 3 · Vegan: 1“ (nur, wenn „Verpflegung“ abgefragt wird).

### Liste

- **Suche** nach Name – auch nach Zusatzinfos, z. B. „12“ findet alle in Zimmer 12.
- **Filter**: Alle · Nicht da · Nicht bezahlt · Ohne Unterschrift (die beiden letzten nur, wenn es Beitrag bzw. Zuschussantrag gibt).
- **Ordnen**: nach Name oder nach einer Zusatzinfo (z. B. „nach Zimmer“) – dann mit Zwischenüberschriften „Zimmer 12 · 3“, ohne Wert am Ende („Ohne Zimmer“).
- **„da?“-Knopf** rechts in jeder Zeile: hakt die Person für den gewählten Tag ab (grün „✓ da“); nochmal tippen nimmt den Haken zurück.
- In der Zeile: Jahrgang, Zusatzinfos, „heute nicht angemeldet“ (wer laut Tage-Auswahl an dem Tag nicht kommt) und der Zahlungsstand (grün bezahlt, rot offen mit Betrag).

### Person

Antippen öffnet die Person (Tablet: rechte Spalte; Handy: eigene Ansicht mit „← Liste“).

- **Anwesenheit**: alle Tage zum Abhaken; gestrichelt = an diesem Tag nicht angemeldet.
- **Zusatzinfos** (nur Team): Felder direkt bearbeiten, gespeichert beim Verlassen des Felds oder mit Enter. Darunter „Mit dabei: …“ – alle mit dem gleichen Wert (z. B. die Zimmermitbewohnenden).
- **Unterschrift** (nur bei Veranstaltungen mit Zuschussantrag): „Unterschreiben“ öffnet das Unterschriftsfeld im Vollbild; danach „✍ Unterschrieben am …“ und „Neu unterschreiben“. Siehe [Digitale Unterschrift](#digitale-unterschrift-zuschuss-teilnahmeliste).
- **Teilnahmebeitrag**: Stand und **„45,00 € erhalten …“** → Zahlungsart wählen (Bar · Karte (SumUp) · PayPal) oder Abbrechen. Bucht den offenen Betrag als Zahlungseingang mit Zahlungsart und Namen der kassierenden Person.
- **Angaben**: alle abgefragten Angaben; sensible sind markiert. **„Korrigieren“** öffnet ein Formular für Name, Verpflegung, Tage, Anmerkungen, eigene Felder. Die Person bekommt keine Mail.

### Verbindungsanzeige (oben rechts)

| Anzeige | Bedeutung |
|---|---|
| Verbunden | alles gespeichert |
| n werden gesendet … | Änderungen sind unterwegs |
| Offline · n warten | keine Verbindung; die Änderungen werden automatisch nachgesendet, sobald das Netz zurück ist – **die Seite dafür offen lassen** (noch nicht neu laden, siehe Ausbauplan) |

Lehnt Kluft eine Änderung ab (z. B. Anmeldung inzwischen storniert), erscheint ein Hinweis mit Grund, und die Daten werden neu geladen.

## Digitale Unterschrift (Zuschuss-Teilnahmeliste)

Für Veranstaltungen **mit Zuschussantrag**: Die **teilnehmende Person unterschreibt selbst**, **einmal je Veranstaltung** (nicht je Tag), auf dem Tablet. Die Unterschrift erscheint in der Zuschuss-Teilnahmeliste (PDF) **in der Zeile der Person** (Name, Anschrift, Geburtsdatum …) im Feld „Unterschrift“.

### Ablauf

1. **Vor Ort** bei der Person **„Unterschreiben“** antippen. Es öffnet sich ein großes Feld mit dem Namen und dem Satz *„Ich bestätige meine Teilnahme an „<Veranstaltung>“ (<Datum>).“*
2. Die Person unterschreibt mit Finger oder Stift. **„Löschen“** setzt das Feld zurück, **„Abbrechen“** schließt ohne Speichern, **„Fertig“** speichert.
3. In der Liste steht dann „✍ unterschrieben“; der Filter **„Ohne Unterschrift“** zeigt, wer noch fehlt. „Neu unterschreiben“ ersetzt eine Unterschrift.
4. Im Backend unter **Zuschussantrag → Unterlagen → Teilnahmeliste (PDF)** stehen die Unterschriften in den Zeilen; wer nicht digital unterschrieben hat, unterschreibt auf dem Ausdruck nach. Die Bestätigung unten („Die Richtigkeit der Angaben …“) unterschreibt weiter die antragstellende Person.

### Festschreiben und Löschen

5. Ist die Liste vollständig: **Zuschussantrag → „Teilnahmeliste festschreiben“**. Kluft legt die Teilnahmeliste mit den Unterschriften als PDF ab (`storage/app/private/zuschuss/teilnahmeliste-<id>.pdf`). „Teilnahmeliste (PDF)“ und das ZIP liefern ab dann **diese Fassung**.
6. Die **Einzelunterschriften werden 14 Tage nach dem Festschreiben gelöscht** (täglich 03:10, Befehl `vor-ort:unterschriften-loeschen`; Frist `UNTERSCHRIFT_LOESCHEN_TAGE`). Bis dahin lässt sich nachunterschreiben und **neu festschreiben**. Wer nach dem Festschreiben neu unterschreibt, wird erst mit dem nächsten Festschreiben wieder zum Löschen vorgemerkt.
7. Die festgeschriebene PDF bleibt beim Zuschussantrag (Verwendungsnachweis) und wird mit der Veranstaltung gelöscht.

### Technik

- Zeichnen mit der Bibliothek [signature_pad](https://github.com/szimek/signature_pad) 5.1.4 (MIT), über Vite gebündelt – nichts von fremden Servern, CSP-konform.
- Das Bild wird auf den beschriebenen Bereich zugeschnitten (höchstens 1200 × 500 Pixel) und als PNG geschickt (Ereignis `unterschrift`, Pakete zu 20).
- Kluft prüft Format und Größe (PNG, höchstens 300 KB), speichert es **verschlüsselt** in `vor_ort_unterschriften` mit **SHA-256-Prüfsumme**, Zeitpunkt (Gerätezeit) und der Person, die das Tablet bedient. In die Liste kommen nur Bilder, deren Prüfsumme stimmt.
- Rechtlich eine einfache elektronische Unterschrift; nach Rückmeldung von den Zuschussgebern anerkannt.

## Zusatzinfos fürs Team

Für Angaben, die **nicht** im Anmeldeformular stehen, sondern das Team vergibt: Zimmer, Zelt, Platz(nummer), Gruppe, Bus …

- **Anlegen**: Veranstaltung bearbeiten → Abschnitt **„Zusatzinfos fürs Team“** → Felder eingeben (Enter oder Komma), höchstens 6, Reihenfolge per Ziehen.
- **Eintragen**: unter „Vor Ort“ bei der Person oder im Backend in der Teilnehmendenliste über **„Angaben bearbeiten“**; dort gibt es außerdem je Feld eine (ein-/ausblendbare) Spalte.
- **Zusammengehörige**: Wer den gleichen Wert hat, gehört zusammen – „Mit dabei: …“ bei der Person, „Ordnen: nach Zimmer“ in der Liste. Werte werden genau verglichen („12“ ≠ „Zimmer 12“); am besten nur die Nummer bzw. den Namen eintragen.
- **Umbenennen**: Der interne Schlüssel kommt aus der Bezeichnung. Wird ein Feld umbenannt, sind die alten Werte darunter nicht mehr zu sehen (bei Rückbenennung wieder).
- Zusatzinfos sehen die Teilnehmenden nicht; sie stehen nicht im Export.

## Zahlungsarten beim Kassieren

Kassieren dürfen alle, die die Teilnehmenden der Veranstaltung verwalten dürfen – sie nehmen das Geld vor Ort entgegen. Jede Buchung steht mit Namen und Zahlungsart in der Zahlung (Notiz, „erfasst von“) und im Protokoll; die Kasse sieht sie unter Kasse → Zahlungen.

Zur Wahl stehen standardmäßig **Bar**, **Karte (SumUp)** und **PayPal**. Kluft **vermerkt** nur, wie bezahlt wurde – kassiert wird mit dem jeweiligen Gerät bzw. Konto (SumUp-Kartenleser, PayPal-App). Je Instanz anpassbar in `instanzen/<instanz>/config.php`:

```php
'vor_ort_zahlwege' => [
    'bar' => 'Bar',
    'karte' => 'Karte (SumUp)',
    // 'paypal' => 'PayPal',  // weglassen, wenn es kein Konto gibt
],
```

**Später möglich** (nicht gebaut):
- **SumUp**: Über die „Payment Switch“-Schnittstelle kann Kluft die SumUp-App mit dem Betrag öffnen und bekommt das Ergebnis zurück (braucht einen Affiliate-Schlüssel von SumUp).
- **PayPal**: QR-Code mit dem Betrag zum Scannen (PayPal.me-Link oder PayPal-QR des Kontos); die Zahlung muss dann trotzdem bestätigt werden.

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
| `zahlung` | `cent`, `weg` (`bar`, `karte`, `paypal` …) | Funktion „Zahlungen“, offene Forderung, Betrag > 0 und ≤ offen, bekannte Zahlungsart | `Zahlung::eingang()` mit Notiz „<Zahlungsart> vor Ort“ (z. B. „Karte (SumUp) vor Ort“), erfasst von der Person; Protokoll `vor_ort.zahlung` |
| `angaben` | `first_name`, `last_name`, `data` (nur abgefragte Felder) | Name nicht leer; Auswahl/Tage/Jahr/Zahl gültig | Anmeldung; Protokoll `vor_ort.angaben` |
| `zusatz` | `feld`, `wert` | Feld gehört zur Veranstaltung, ≤ 100 Zeichen | Spalte `event_registrations.zusatz` (leer = entfernen); Protokoll `vor_ort.zusatz` |
| `unterschrift` | `bild` (PNG als Data-URI) | Veranstaltung mit Zuschussantrag; gültiges PNG ≤ 300 KB | Tabelle `vor_ort_unterschriften` (ersetzt eine vorhandene); im Ereignis-Protokoll nur die Prüfsumme; Protokoll `vor_ort.unterschrift` |

Für alle gilt: Recht `manageParticipants`, Anmeldung bestätigt. Jede Kennung wird **genau einmal** verarbeitet (eindeutiger Index), erneutes Senden ergibt `doppelt`. Damit ist Offline vorbereitet: Haken und Zahlungen können sich beim Nachsenden nicht widersprechen; bei Angaben gilt die zuletzt verarbeitete Änderung.

Die Oberfläche wendet Änderungen sofort an (optimistisch), legt sie in eine Warteschlange, sendet in Paketen bis 20 und wiederholt automatisch (alle 3 s bei offener Warteschlange, sonst alle 15 s und beim Wiederverbinden).

### Datenmodell

| Tabelle / Spalte | Inhalt |
|---|---|
| `event_registrations.vor_ort_tage` | JSON-Liste der Tage, an denen die Person vor Ort abgehakt ist |
| `event_registrations.zusatz` | JSON `{schlüssel: wert}` der Zusatzinfos |
| `events.zusatz_felder` | JSON-Liste der Bezeichnungen (Schlüssel = `Str::slug(Bezeichnung, '_')`) |
| `vor_ort_unterschriften` | je Anmeldung höchstens eine: `bild` (verschlüsselt), `pruefsumme` (SHA-256), Person am Tablet, `unterschrieben_am`, `loeschen_am` (gesetzt beim Festschreiben) |
| `events.teilnahmeliste_datei`, `teilnahmeliste_am` | abgelegte, festgeschriebene Teilnahmeliste |
| `vor_ort_ereignisse` | `uuid` (eindeutig), Veranstaltung, Anmeldung, Person, `typ`, `daten` (verschlüsselt), `ergebnis` (ok/abgelehnt), `erfasst_am` (Gerätezeit) |

Migrationen: `2026_11_13_100000_create_vor_ort`, `2026_11_13_110000_add_zusatz_felder`, `2026_11_13_120000_create_vor_ort_unterschriften`.

`vor_ort_tage` ist bewusst getrennt von `anwesend`/`anwesend_tage` des Zuschussantrags (dort bedeutet „leer“ = alle Tage). Übernahme in den Zuschussantrag: **Zuschussantrag → „Anwesenheit aus „Vor Ort“ übernehmen“**. Wer mindestens einmal abgehakt wurde, gilt an genau diesen Tagen als anwesend (alle Tage = Standard, wie im Zuschuss-Dialog). Wer nie abgehakt wurde, bleibt unverändert – oder gilt mit dem Schalter „Wer nie abgehakt wurde, gilt als ‚nicht dabei‘“ als nicht dabei. Protokoll `zuschuss.anwesenheit_vor_ort`.

## Datenschutz

- **Gerät**: Stufe 1 speichert nichts dauerhaft auf dem Gerät; die Daten kommen bei jedem Öffnen frisch vom Server. Die Warteschlange lebt nur, solange die Seite offen ist.
- **Ereignisse** enthalten nur, was geändert wurde (verschlüsselt gespeichert). Sensible Angaben (z. B. Allergien) stehen dort nicht im Klartext, sondern nur „(sensibel, geändert)“. Die Ereignisse werden mit der Anmeldung gelöscht (Löschfristen der Veranstaltung).
- **Unterschriften**: verschlüsselt mit Prüfsumme; im Ereignis-Protokoll nur die Prüfsumme. Gelöscht 14 Tage nach dem Festschreiben der Teilnahmeliste, spätestens mit der Anmeldung. Danach existiert die Unterschrift nur noch in der festgeschriebenen PDF, die zum Zuschussantrag gehört (Aufbewahrung wie der Antrag) und mit der Veranstaltung gelöscht wird.
- **Protokoll**: Zahlungen, Korrekturen, Zusatzinfos, Unterschriften und Festschreiben stehen mit Namen im Protokoll (`vor_ort.*`, `registration.bearbeitet`).
- **Anzeige**: Sensible Angaben zeigt „Vor Ort“ wie die Detailansicht im Backend, gekennzeichnet – für dieselben Personen.
- Seite mit `noindex`, gleiche Sicherheits-Header (CSP) wie die Website.

Für die Datenschutzbewertung (`docs/datenschutz-bewertung.md`): keine neue Datenkategorie und kein neuer Empfänger; neu ist der Verarbeitungsort Tablet/Handy des Teams. Mit Stufe 3 (offline) kommt eine Speicherung auf dem Gerät hinzu – dann Bewertung ergänzen.

## Entscheidungen und offene Punkte

| Thema | Stand | Offen |
|---|---|---|
| Wer darf Zahlungen verbuchen? | **entschieden:** alle mit `manageParticipants` – das Team vor Ort nimmt das Geld entgegen; protokolliert mit Namen | – |
| Zahlungsarten | Bar, Karte (SumUp), PayPal – nur vermerkt, keine Anbindung | Anbindung an SumUp/PayPal (siehe unten) |
| Eigene App oder Bereich in Kluft? | Bereich in Kluft (gleiche Anmeldung, Rechte, Daten; kein App-Store) | – |
| Offline nötig? | ja – ein zentraler Platz ist ohne Internet | Stufe 3 |
| Digitale Unterschriften | **entschieden:** anerkannt; eine je Person und Veranstaltung; **die teilnehmende Person unterschreibt selbst** (auch Minderjährige); in der Zeile der Person; Standardtext „Ich bestätige meine Teilnahme …“ | – |
| Aufbewahrung der Unterschriften | **entschieden:** festgeschriebene Liste genügt; Einzelunterschriften 14 Tage nach dem Festschreiben gelöscht | – |
| Anwesenheit in den Zuschussantrag übernehmen | Knopf im Zuschussantrag (`VorOrt::anwesenheitUebernehmen`) | – |
| Wartende/stornierte Anmeldungen | nicht in „Vor Ort“ | ggf. Nachmeldung vor Ort als eigene Funktion |
| Gerät | Tablet des Büros, Handy als Ausweich | – |

## Ausbauplan

1. ~~**Stufe 1 – online**: Auswahl, Anwesenheit je Tag, Zahlungsstand und Barzahlung, Verpflegung, Angaben korrigieren, Zusatzinfos.~~ (PR „Vor Ort, Stufe 1“)
2. ~~**Stufe 2 – digitale Unterschrift** je Person und **Veranstaltung** (mit Finger oder Stift auf dem Tablet, Bibliothek [signature_pad](https://github.com/szimek/signature_pad), MIT, über Vite gebündelt), gespeichert als PNG (verschlüsselt, mit Zeitpunkt, bedienender Person und Prüfsumme) als Ereignis `unterschrift`, eingesetzt in die Zuschuss-Teilnahmeliste (PDF) in der Spalte „Unterschrift“; Festschreiben der Liste, Einzelunterschriften 14 Tage danach gelöscht.~~ (PR „Vor Ort, Stufe 2“)
3. **Stufe 3 – installierbar und offline**: Web-App-Manifest und Service Worker, Liste der gewählten Veranstaltung auf dem Gerät, Warteschlange dauerhaft (IndexedDB), Nachsenden beim Wiederverbinden, Anzeige „Stand von …“.
4. **Stufe 4 – Datenschutz offline**: Gerätespeicher verschlüsselt, Löschen beim Abmelden und nach der Veranstaltung, sensible Angaben offline nur nach ausdrücklicher Freigabe, Ergänzung der Datenschutzbewertung.
