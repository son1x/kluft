# Pflichtenheft: Sammelbestellung – neue Shop-Oberfläche und Katalog-Import

**Stand:** 9. November 2026 · **Status:** Phase A und Teile von B umgesetzt (`feature/sammelbestellung-shop`), Katalog-Import offen · **Bezug:** `docs/pflichtenheft-sammelbestellung.md` (SB-01 bis SB-92, Phase 1 und 2 gebaut)

Dieses Pflichtenheft ergänzt das bestehende. Es ändert **nicht** den Ablauf
einer Bestellrunde, die Bezahlung oder das Backend der Sammelbestellung,
sondern zwei Dinge:

1. **Die öffentliche Bestellseite** `/bestellen` bekommt eine aufgeräumte
   Shop-Oberfläche nach den Apple Human Interface Guidelines (HIG).
2. **Ein Katalog-Import** holt die DPSG-Artikel des Rüsthaus kategorieweise
   als Vorschläge ins Backend. Freigegeben wird weiter von Hand.

Leitlinie für beides: **nicht überladen** und **rechtlich sauber**.

**Entscheidungen des Stamms (9. November 2026):**

- **Rüsthaus:** einverstanden, dass wir einzelne Artikeldaten übernehmen (Name, Beschreibung, Bild, Preis, Größen), auch kategorieweise als Vorschläge.
- **Keine unternehmerische Tätigkeit:** Die Sammelbestellung ist ein Service für Mitglieder. Wir sammeln die Bestellungen und geben sie weiter.
- **Preise:** nur Originalpreise des Rüsthaus, keine Rabatte, keine Streichpreise.
- **Storno:** nur bis zum Bestellschluss, also bis wir beim Rüsthaus bestellt haben; Bezahltes wird erstattet.
- **Knopf:** „Verbindlich bestellen“.
- **Mehrere Artikelnummern auf einmal** sollen sich im Backend eingeben lassen (SB-150a).

Anforderungen tragen IDs der Form `SB-1xx`. **MUSS**, **SOLL**, **KANN** wie
im Haupt-Pflichtenheft. Der Abschnitt zur Rechtslage ist eine fachliche
Einschätzung, **keine Rechtsberatung**. Vor dem Einschalten des
Katalog-Imports prüft der Rechtsträger bzw. der DV die offenen Punkte (Kapitel 9).

Das Mockup dazu liegt als Design-Canvas vor (Link in der PR-Beschreibung).

---

## 1. Ist-Stand und was stört

Die Bestellseite (`resources/views/shop/index.blade.php`, 291 Zeilen,
Logik in `resources/js/shop-bestellung.js`) ist **ein langes Formular**:

| Problem | Folge |
|---|---|
| Alles auf einer Seite: Einleitung, „Für wen“, Sets mit aufklappbaren Größen, Artikelliste nach Kategorie, „Anderen Artikel bestellen“, Übersicht, Kontakt, Einwilligung. | Auf dem Handy sehr lang; man verliert den Überblick, was schon gewählt ist. |
| Artikel als schmale Liste mit 64-px-Bild, Größe, Anzahl und „Hinzufügen“ in einer Zeile. | Wenig Bild, kleine Bedienelemente, Größenwahl als Klappliste. |
| „Für wen?“ steht oben, die Auswahl wirkt auf alle Zeilen; ein Umschalter klebt oben. | Bei Geschwistern fehleranfällig. |
| Übersicht erst ganz unten. | Kein ständig sichtbarer Warenkorb mit Summe. |
| Hinweise als Kästen mit rotem Rand. | Unruhig; Warnung und Information sehen gleich aus. |

Was gut ist und bleibt: Sets, eigene Artikel des Stamms, freie Bestellung
per Artikelnummer, Double-Opt-in, GiroCode, Testbetrieb, CSP ohne
Inline-Skripte.

---

## 2. Gestaltungsgrundsätze (Apple HIG)

Grundlage sind die aktuellen Human Interface Guidelines mit der
Designsprache **Liquid Glass** (seit 2025, Aktualisierungen 2025/2026:
Materialien sparsam und semantisch, Lesbarkeit und Kontrast vor Effekt,
einheitliche Werkzeugleisten, konzentrische Ecken). Übertragen auf eine
Website heißt das:

- **SB-100** MUSS: **Hierarchie:** Inhalt (Artikel) steht im Vordergrund. Bedienung schwebt darüber und tritt zurück: die Warenkorb-Leiste unten, die Kategorie-Leiste oben. Je Bildschirm genau eine Hauptaktion.
- **SB-101** MUSS: **Glas nur für schwebende Bedienelemente:**
  - **Wo:** die Warenkorb-Leiste und die beim Scrollen fixierte Kategorie-Leiste, als durchscheinendes Material (`backdrop-filter`).
  - **Wo nicht:** Karten, Listen und Text bleiben auf festen Flächen.
  - **Barrierefreiheit:** Bei `prefers-reduced-transparency` und `prefers-contrast: more` wird das Glas zu einer festen Fläche.
- **SB-102** MUSS: **Harmonie und Konsistenz:**
  - **Ecken:** konzentrisch (Karte 16 px, Bild darin 12 px, Knöpfe als Kapsel).
  - **Raster:** Abstände im 4/8-px-Raster.
  - **Schrift:** Systemschrift (`-apple-system, system-ui`) für die Bedienung, die Hausschrift der Website für Überschriften.
  - **Farbe:** DPSG-Blau als Akzent für Aktionen, Rot nur für Warnungen.
- **SB-103** MUSS: **Bedienbarkeit:** Trefferflächen mindestens 44 × 44 px; Schrift skaliert mit der Browser-Einstellung (rem); Kontrast mindestens 4,5 : 1 (WCAG 2.2 AA); alles per Tastatur erreichbar mit sichtbarem Fokus; Bewegungen respektieren `prefers-reduced-motion`.
- **SB-104** MUSS: **Bekannte Muster:**
  - **Kategorien:** als Segment-Leiste (Kapseln).
  - **Artikel:** als Raster mit großen Bildern.
  - **Details:** als **Sheet** (von unten auf dem Handy, Dialog am Desktop).
  - **Größen und Anzahl:** Größen als Auswahlknöpfe, Anzahl als Stepper.
  - **Bestellen:** als kurzer, geführter Ablauf.
- **SB-105** MUSS: **Hell und dunkel:** folgt `prefers-color-scheme` der Website, sobald die Website ein dunkles Thema hat; bis dahin hell.

---

## 3. Informationsarchitektur der Bestellseite

```
/bestellen
├─ Kopf: Titel „Ausrüstung“, Rundenstatus (offen bis …, bzw. „nächste Runde …“)
├─ Suche (nur freigegebene Artikel)
├─ Kategorie-Leiste: Alle · Grundausstattung · Kluft · Halstücher · Aufnäher · Sets · Vom Stamm
├─ „Für den Start“ (Grundausstattung und Sets, höchstens 4 Karten)
├─ Artikelraster (2 Spalten Handy, 3–4 Desktop)
├─ „Anderen Rüsthaus-Artikel bestellen“ (eingeklappt, unten)
└─ Warenkorb-Leiste (schwebend): Anzahl · Summe · „Warenkorb“
     ├─ Sheet „Artikel“: Bild, Name, Preis, Größe, Für wen, Anzahl, Details
     └─ Sheet „Warenkorb“ → Ablauf „Bestellen“ (3 Schritte)
```

- **SB-110** MUSS: **Rundenstatus oben**, als ruhige Zeile mit Symbol statt Kasten: „Herbst 2026 · bestellen und bezahlen bis 15. November“. Ohne offene Runde: „Gerade keine Sammelbestellung. Du kannst dich umsehen.“ Der Warenkorb ist dann aus.
- **SB-111** MUSS: **Kategorie-Leiste** mit den Kategorien des lokalen Shops (Kapitel 6); höchstens 7 sichtbar, weitere unter „Mehr“. „Alle“ ist Standard. Die Leiste bleibt beim Scrollen oben (SB-101).
- **SB-112** MUSS: **Artikelkarte:**
  - **Inhalt:** quadratisches Bild, Name (höchstens zwei Zeilen), Preis („ab 29,90 €“ bei unterschiedlichen Größenpreisen), Kennzeichen „Set“ oder „Vom Stamm“.
  - **Bedienung:** Die ganze Karte öffnet das Artikel-Sheet; ein „+“-Knopf öffnet dasselbe Sheet mit Fokus auf der Größe.
  - **Weggelassen:** Beschreibung und Artikelnummer stehen nicht auf der Karte.
- **SB-113** MUSS: **Suche** über Name, Kategorie und Artikelnummer der **freigegebenen** Artikel, ohne Neuladen. Ohne Treffer der Hinweis auf „Anderen Rüsthaus-Artikel bestellen“.
- **SB-114** SOLL (umgesetzt als Kategorie): **„Für den Start“:** hervorgehobene Artikel und Sets (Feld „Oben unter „Für den Start““ am Artikel bzw. Set) als eigene Kategorie in der Leiste.
- **SB-115** MUSS: **Freie Bestellung** (SB-14) bleibt, aber als eingeklappter Bereich am Ende und als Sheet; sie bekommt keinen eigenen Formularblock mitten im Shop.

### 3.1 Artikel-Sheet

- **SB-120** MUSS: Inhalt von oben nach unten:
  1. **Bild** groß, wischbar bei mehreren Bildern.
  2. **Name**, **Preis** mit Zusatz „inkl. MwSt., Preis wie beim Rüsthaus“ (SB-171).
  3. **Größe:** Auswahlknöpfe (bis 12 Größen), sonst Liste. Nicht lieferbare Größen sind ausgegraut. Daneben der Link „Größentabelle“, der die Maßtabelle des Rüsthaus in einem zweiten Sheet zeigt, falls übernommen.
  4. **Für wen:** Kapseln mit den Mitgliedern dieser Bestellung („Lea · Wölflinge“, „+ Mitglied“). Beim ersten Artikel fragt das Sheet nach Vorname, Nachname und Stufe; danach steht das Mitglied zur Auswahl.
  5. **Anzahl:** Stepper (1–10).
  6. **Hauptaktion** „In den Warenkorb“ (mit Preis), unten fest.
  7. **Aufklappbar:** „Beschreibung“, „Hersteller und Sicherheit“ (SB-173), „Artikelnummer“.
- **SB-121** MUSS: **Sets** im selben Sheet: Teile als Liste. Je Teil mit Größen eine Größenwahl, optionale Teile mit Schalter. Die Summe aktualisiert sich (SB-38 unverändert).
- **SB-122** MUSS: Nach „In den Warenkorb“ schließt das Sheet. Die Warenkorb-Leiste zeigt kurz „Hinzugefügt“ (bei `prefers-reduced-motion` ohne Animation) und die neue Summe.

### 3.2 Warenkorb und Bestellen

- **SB-130** MUSS: **Warenkorb-Leiste** (schwebend, unten): Anzahl Artikel, Summe, Knopf „Warenkorb“. Sie ist nur sichtbar, wenn etwas im Warenkorb liegt und eine Runde offen ist.
- **SB-131** MUSS: **Warenkorb-Sheet:** Positionen **nach Mitglied gruppiert**. Je Position Bild, Name, Größe, Stepper, Entfernen; Mitglied umbenennen. Freie Artikel tragen den Hinweis „wird geprüft, Preis vorläufig“. Unten Summe und „Weiter zur Bestellung“.
- **SB-132** MUSS: Der Warenkorb überlebt Neuladen und Zurück-Navigation (lokal im Browser, `localStorage`). Gespeichert sind nur Artikel-IDs, Größen, Mengen und die eingegebenen Mitgliedsnamen; nach Abschicken oder 7 Tagen wird er gelöscht. Hinweis im Datenschutztext (SB-72).
- **SB-133** MUSS: **Bestellen in drei Schritten**, auf dem Handy je ein Bildschirm, am Desktop eine Seite mit Abschnitten:
  1. **Kontakt:** Name, E-Mail, optional Telefon; „Ich bin selbst Mitglied“ bzw. „erziehungsberechtigt“.
  2. **Abholung:** Gruppenstunde der Stufe bzw. nach Absprache; Bemerkung.
  3. **Prüfen:** Alle Positionen je Mitglied, Summe, Zahlungsfrist, Zahlungsweg („Du bekommst nach der Bestätigung per Mail die Bankverbindung mit GiroCode“), Hinweise zu Storno und Abholung, Einwilligungen (SB-34). Dann die Hauptaktion **„Zahlungspflichtig bestellen“** (SB-170).
- **SB-134** MUSS: **Bestätigungsseite:** „Fast geschafft: Bitte bestätige deine Bestellung über den Link in der Mail.“ Dazu die nächsten Schritte als nummerierte Liste (bestätigen, bezahlen bis …, Abholung).
- **SB-135** MUSS: Fehler stehen am Feld, nicht als Sammelmeldung oben. Der Ablauf springt zum ersten fehlerhaften Schritt.
- **SB-136** SOLL (zurückgestellt): Ohne JavaScript bleibt ein einfaches Bestellformular nutzbar. Die bisherige Seite brauchte JavaScript ebenfalls; der Server prüft weiter alles neu.

---

## 4. Seite eines Artikels

- **SB-140** SOLL (umgesetzt als Anker): Jeder Artikel hat eine teilbare Adresse `/bestellen#artikel-a12` bzw. `#artikel-s3` für Sets. Sie öffnet das Sheet direkt; das Sheet setzt den Anker beim Öffnen.

---

## 5. Was bewusst nicht kommt (nicht überladen)

- **Keine Bewertungen**, keine „Andere kauften auch“, kein „Nur noch 2 da“, kein Countdown, kein Newsletter-Kasten im Ablauf.
- **Kein voller Rüsthaus-Katalog öffentlich:** Mitglieder sehen nur, was der Stamm freigegeben hat (SB-160).
- **Keine Filter nach Farbe, Preis oder Material:** Kategorie und Suche reichen bei einigen Dutzend Artikeln.
- **Keine Konten für Eltern:** Der Mail-Link genügt (SB-33).

---

## 6. Katalog-Import (Backend)

### 6.1 Ziel

Die Sammelbestellung soll nicht jeden Artikel einzeln per Nummer anlegen
müssen. Kluft holt auf Knopfdruck die Artikel ausgewählter
Rüsthaus-Kategorien, z. B. „DPSG-Artikel → Kluft“, „Halstücher“,
„Aufnäher“. Sie erscheinen als **Vorschläge**, die man sichtet und in
einem Rutsch freigibt.

### 6.2 Voraussetzung

- **SB-150** MUSS: **Erlaubnis des Rüsthaus** liegt vor (9.11.2026, siehe oben). Sie wird im Pflichtenheft und in `docs/roden.md` vermerkt; eine Datei im Backend ist nicht nötig. Für andere Stämme gilt: erst Erlaubnis einholen, dann einschalten.
- **SB-150a** MUSS (umgesetzt): **Mehrere Artikel holen.** Im Backend lassen sich bis zu 50 Artikelnummern oder Links auf einmal eingeben (je Zeile, Komma oder Leerzeichen). Kluft holt sie nacheinander einzeln im Hintergrund (drei je Lauf, eine Anfrage pro Sekunde), wahlweise als Entwurf. Stand und Fehler stehen unter der Überschrift; „Im Shop zeigen“ gibt es als Sammelaktion.
- **SB-151** SOLL: **Datenfeed statt Abruf der Shopseiten**, wenn das Rüsthaus einen anbietet (CSV- bzw. Produkt-Feed oder Shop-Schnittstelle mit Zugangsschlüssel). Kluft bekommt dafür eine austauschbare Quelle: „Feed“ oder „Shopseiten“. Der Feed ist der bevorzugte Weg: rechtlich klar, schneller, stabiler.

### 6.3 Ablauf

1. **Kategorien wählen:** Backend „Sammelbestellung → Katalog“. Die Sammelbestellung wählt Rüsthaus-Kategorien aus einer Liste, die Kluft aus der Navigation des Shops liest, und ordnet jeder eine **lokale Kategorie** zu (z. B. Rüsthaus „Halstücher Stufen“ → „Halstücher“). Gesperrte Kategorien (SB-15) sind nicht wählbar.
2. **Abgleich starten:** „Katalog abgleichen“ stellt einen Auftrag in die Warteschlange. Fortschritt und Abbruch stehen im Backend.
3. **Listen holen:**
   - **Quelle:** nur die **Kategorie-Übersichten** (mit Seitenblättern).
   - **Inhalt je Artikel:** Name, Artikelnummer, Preis „ab“, Link zur Produktseite, Adresse des Vorschaubilds.
   - **Ohne:** Bilder werden nicht gespeichert, Varianten nicht einzeln abgerufen.
4. **Vorschläge zeigen** in einer Tabelle mit Status:
   - **neu:** noch nicht im lokalen Shop;
   - **freigegeben:** schon im Shop;
   - **geändert:** Preis oder Name weicht vom Cache ab;
   - **entfallen:** freigegeben, aber nicht mehr in der Kategorie;
   - **gesperrt:** passt auf die Sperrliste;
   - **ausgeblendet:** von der Sammelbestellung dauerhaft ignoriert.
5. **Freigeben:** Haken setzen, dann „Freigeben“. Erst jetzt holt Kluft je Artikel die Produktseite (wie SB-10: Varianten, Beschreibung, Bild in die Mediathek) und legt den Artikel als **Entwurf** an. Die Sammelbestellung prüft und schaltet ihn sichtbar, einzeln oder gesammelt.
6. **Ergebnis:** Zusammenfassung „12 neu, 3 Preise geändert, 1 entfallen“. Bei geänderten Preisen gilt SB-17, bei entfallenen SB-13.

### 6.4 Anforderungen

- **SB-152** MUSS: **Nur auf Anstoß**, kein Zeitplan. Höchstens ein Abgleich gleichzeitig, höchstens einer je Tag und Instanz.
- **SB-153** MUSS: **Schonend abrufen:**
  - **Takt:** höchstens eine Anfrage je Sekunde, wie bisher (SB-16), mit eigener Kennung im User-Agent.
  - **Obergrenzen je Abgleich:** 20 Kategorien, 30 Übersichtsseiten, 500 Artikel. Darüber bricht der Auftrag mit Hinweis ab.
  - **Regeln des Shops:** `robots.txt` und ausdrückliche Vorbehalte (z. B. Text- und Data-Mining-Vorbehalt) werden vor jedem Abgleich geprüft. Verbietet der Shop den Abruf, startet der Abgleich nicht.
- **SB-154** MUSS: **Varianten erst bei Freigabe.** Ein Artikel mit 12 Größen kostet heute 13 Anfragen (SB-10). Ein Abgleich über Kategorien ruft deshalb **nie** Varianten ab, nur die Freigabe tut es, und zwar im Hintergrund.
- **SB-155** MUSS: **Bilder** werden erst bei Freigabe geladen, verkleinert und mit Vermerk „Bild: Rüsthaus“ in der Mediathek gespeichert. Vorschaubilder in der Vorschlagstabelle lädt der Browser der Sammelbestellung **nicht** direkt vom Rüsthaus (keine Datenübertragung an Dritte): Die Tabelle zeigt sie nicht oder über einen kurzzeitigen Zwischenspeicher in Kluft.
- **SB-156** MUSS: **Robust:** Ändert sich das Seitenformat, liefert der Abgleich „0 Artikel erkannt“ statt falscher Daten. Freigegebene Artikel bleiben unverändert, und die Sammelbestellung bekommt einen Hinweis. Getestet wird mit gespeicherten Beispielseiten, nie live (SB-85).
- **SB-157** SOLL: **Kategorie-Zuordnung merken:** Beim nächsten Abgleich sind die gewählten Kategorien und Zuordnungen vorbelegt. Neue Artikel einer gewählten Kategorie kommen als „neu“ und werden nie automatisch freigegeben.
- **SB-158** KANN: **Abgleich vor Rundenstart:** Beim Öffnen einer Runde fragt Kluft: „Katalog vorher abgleichen?“ Das ersetzt SB-12 „alle aktualisieren“ für importierte Kategorien.
- **SB-159** MUSS: **Rechte:** Katalog und Abgleich gibt es nur für die Rolle „Sammelbestellung“ und Admins. Die Erlaubnis (SB-150) hinterlegen nur Admins.

### 6.5 Öffentliche Sichtbarkeit

- **SB-160** MUSS: Öffentlich erscheinen **nur freigegebene** Artikel. Der Import vergrößert den öffentlichen Shop nicht von selbst.
- **SB-161** SOLL: Lokale Kategorien: höchstens 7. Die Sammelbestellung kann Rüsthaus-Kategorien zusammenlegen.

---

## 7. Datenmodell (Erweiterung)

- **SB-162** MUSS: Neue Tabellen, keine Änderung bestehender Spalten:
  - **`shop_quellen`:** Art (Shopseiten bzw. Feed), Einstellungen, Erlaubnis (Datei, Datum, Umfang, wer).
  - **`shop_kategorien_zuordnung`:** Rüsthaus-Kategorie (Pfad, Name) → lokale Kategorie, aktiv.
  - **`shop_katalog`:** Vorschläge mit Artikelnummer, Name, Preis ab, Link, Kategorie, Status, zuletzt gesehen, Abgleich-ID.
  - **`shop_abgleiche`:** Start, Ende, Zähler, Fehler, wer.
- Neue Spalten in eigenen Migrationen:
  - **`shop_artikel.hervorheben`** (SB-114);
  - **`shop_artikel.slug`** (SB-140);
  - **`shop_artikel.hersteller`** (Name, Anschrift, E-Mail; SB-173);
  - **`shop_artikel.sicherheitshinweise`** (Text, optional).

---

## 8. Technik

- **SB-163** MUSS: Die Oberfläche bleibt **Blade + Alpine (Vite)** ohne neue Laufzeit-Bibliothek, CSP-konform ohne Inline-Skripte. Das Sheet ist ein `<dialog>` mit Fokusfalle, Escape zum Schließen und `aria-modal`.
- **SB-164** MUSS: Der Abgleich läuft als **Warteschlangen-Auftrag** (Cronjob, wie Mails). Die Seite im Backend zeigt den Fortschritt per Polling.
- **SB-165** MUSS: Die Quelle „Shopseiten“ steckt hinter einer Schnittstelle (`KatalogQuelle`) mit den Methoden `kategorien()`, `artikelIn(kategorie)`, `produkt(nummer)`. `Ruesthaus` wird darauf umgestellt, eine Feed-Quelle kommt später dazu.
- **SB-166** MUSS: Tests für Oberfläche (Sheet, Warenkorb nach Mitglied, Ablauf in drei Schritten, ohne JavaScript), Abgleich (Seitenblättern, Grenzen, robots-Verbot, Formatänderung, Sperrliste, Status neu/geändert/entfallen) und Rechte. Grün für alle drei Instanzen.

---

## 9. Rechtslage

Fachliche Einschätzung, Stand November 2026, keine Rechtsberatung.

### 9.1 Daten, Bilder und Texte des Rüsthaus

| Thema | Einschätzung | Folge |
|---|---|---|
| **Datenbankherstellerrecht** (§§ 87a, 87b UrhG) | Ein Shop-Katalog ist in der Regel eine geschützte Datenbank. Das **systematische Entnehmen** wesentlicher Teile ist zustimmungspflichtig. Wiederholtes Entnehmen unwesentlicher Teile ist es auch, wenn es darauf hinausläuft (BGH, „Zweite Zahnarztmeinung“). Ein kategorieweiser Abruf aller DPSG-Artikel fällt sehr wahrscheinlich darunter. | **SB-150:** schriftliche Erlaubnis vor dem Einschalten, besser ein Feed (SB-151). |
| **Urheberrecht an Bildern und Texten** | Produktfotos und Beschreibungen sind meist geschützt. Laut Haupt-Pflichtenheft ist die Übernahme „laut Stamm erlaubt“, schriftlich noch offen (dort Kapitel 10, Frage 3). | Erlaubnis muss Bilder und Texte ausdrücklich umfassen. Bildnachweis „Bild: Rüsthaus“ bleibt. |
| **Text- und Data-Mining** (§ 44b UrhG) | Erlaubt nur die Analyse; Speichern und **Anzeigen** im eigenen Shop deckt das nicht. Ein maschinenlesbarer Vorbehalt des Shops schließt auch das aus. | Kein Rückgriff auf § 44b; SB-153 prüft Vorbehalte trotzdem. |
| **Marken** (DPSG-Lilie, Stufenzeichen) | Abbildung im Zusammenhang mit dem Weiterverkauf echter Ware ist unproblematisch. | Keine eigenen Abwandlungen der Zeichen. |

### 9.2 Verkauf an Mitglieder

Der Stamm handelt hier **nicht unternehmerisch** (Entscheidung 9.11.2026):
Er sammelt die Bestellungen seiner Mitglieder und gibt sie zum
Originalpreis an das Rüsthaus weiter. Damit entfallen Widerrufsbelehrung
und die Pflichtangaben für Online-Händler. Kluft bleibt trotzdem klar und
fair: Preise inkl. MwSt., Bestellübersicht vor dem Abschicken,
eindeutiger Knopf.

| Thema | Anforderung |
|---|---|
| **Bestell-Button** | **SB-170** MUSS (umgesetzt): Die Hauptaktion heißt **„Verbindlich bestellen“**. Direkt davor stehen Artikel, Größen, Mengen, Gesamtpreis und Zahlungsfrist. |
| **Preise** | **SB-171** MUSS (umgesetzt): **Originalpreise des Rüsthaus** inkl. MwSt., keine Rabatte, keine Streichpreise. Hinweis „kein Versand, Abholung in der Gruppenstunde“. Eigene Artikel: „Preis des Stamms“. |
| **Storno** | **SB-172** MUSS (umgesetzt): Storno über den Link in der Mail **bis zum Bestellschluss**, also solange die Runde nicht „beim Rüsthaus bestellt“ ist, auch nach Zahlung. Bezahltes erscheint bei der Kasse als „Erstattung offen“; die Person bekommt die Storno-Mail. Die Regel steht im Ablauf, auf der Danke-Seite, in der Mail und auf der Statusseite. Ein gesetzliches Widerrufsrecht besteht nicht (kein Unternehmer). |
| **Produktsicherheit** (GPSR) | Der Stamm ist kein Wirtschaftsakteur; die Pflichtangaben nach Art. 19 entfallen. **SB-173** KANN: Hinweise des Rüsthaus (z. B. Pflege, Warnhinweise) stehen in der Beschreibung; der Link „beim Rüsthaus ansehen“ führt zu allen Herstellerangaben. |
| **Barrierefreiheit** (BFSG, seit 28.06.2025) | Ein Stamm mit weniger als 10 Beschäftigten (Ehrenamtliche zählen in der Regel nicht) fällt als Kleinstunternehmen bei Dienstleistungen vermutlich unter die Ausnahme. **SB-174** MUSS trotzdem: WCAG 2.2 AA als Ziel (SB-103). Das ist ohnehin Anspruch der Website. |
| **Datenschutz** (KDG; DSGVO für Dienstleister) | Unverändert nach SB-70 bis SB-74. Neu: **SB-175** MUSS: Der Warenkorb im Browser (SB-132) ist technisch notwendig, kein Tracking, und steht im Datenschutztext. Keine externen Bilder, Schriften oder Skripte im Shop (SB-155). |
| **Kinder und Jugendliche** | Bestellungen für Minderjährige geben Erziehungsberechtigte auf (SB-31/34, unverändert). Die Seite richtet sich in der Ansprache an Eltern und Leitende. |
| **Steuer und Gemeinnützigkeit** | Die Provision kann Einnahme eines wirtschaftlichen Geschäftsbetriebs sein. **Hinweis an die Kasse**, keine Anforderung an Kluft: mit dem Rechtsträger bzw. der Steuerberatung klären. |

---

## 10. Offene Fragen

1. **Rüsthaus:** Erlaubnis liegt vor (9.11.2026). Offen: Gibt es einen **Produkt-Feed** oder Shop-API-Zugang (SB-151)?
2. ~~Unternehmer oder nicht~~ – geklärt am 9.11.2026: Service für Mitglieder, nicht unternehmerisch.
3. **Kategorien:** Welche Rüsthaus-Kategorien sollen abgeglichen werden, und wie heißen die lokalen Kategorien?
4. **„Für den Start“:** Welche Artikel und Sets stehen oben?
5. **Netz:** Für die Entwicklung muss `www.ruesthaus.de` im Netzwerk der Entwicklungsumgebung erlaubt sein, um echte Beispielseiten für Tests zu speichern. Aus dieser Sitzung war der Shop nicht erreichbar; `robots.txt` und Seitenstruktur sind deshalb noch nicht neu geprüft.

---

## 11. Abnahme

1. Auf dem Handy (iPhone, Android) und am Desktop:
   - Artikel finden über Kategorie und Suche.
   - Im Sheet Größe, Mitglied und Anzahl wählen.
   - Zwei Mitglieder im Warenkorb, getrennt angezeigt.
   - Nach Neuladen ist der Warenkorb noch da.
   - In drei Schritten „Zahlungspflichtig bestellen“; die Mail kommt wie bisher.
2. Bedienung nur per Tastatur und mit VoiceOver bzw. TalkBack: Sheet öffnen und schließen, Größe wählen, bestellen.
3. Mit „Transparenz reduzieren“ und „Bewegung reduzieren“ keine Glaseffekte und keine Animation.
4. Ohne JavaScript ist eine Bestellung möglich.
5. Katalog:
   - Ohne hinterlegte Erlaubnis ist der Abgleich gesperrt.
   - Mit Erlaubnis liefert der Abgleich zweier Kategorien Vorschläge.
   - Freigabe von fünf Artikeln legt Entwürfe mit Größen und Bild an.
   - Eine Preisänderung erscheint als „geändert“, ein entfernter Artikel als „entfallen“.
   - Ein robots-Verbot (Beispielseite) stoppt den Abgleich.
6. Öffentlich erscheinen nur freigegebene Artikel.

---

## 12. Umsetzung in Phasen (Vorschlag)

| Phase | Inhalt |
|---|---|
| **A** (gebaut) | Neue Oberfläche: Raster, Kategorien, Suche, Sheet, Warenkorb nach Mitglied, Ablauf in drei Schritten, „Verbindlich bestellen“, Preisangaben, Storno bis Bestellschluss, mehrere Artikel holen (SB-100 bis SB-135, SB-150a, SB-170 bis SB-172, SB-174/175). Ohne JavaScript (SB-136) zurückgestellt. |
| **B** (gebaut, vereinfacht) | „Für den Start“ als Kategorie, teilbarer Anker je Artikel (SB-114, SB-140). |
| **C** | Katalog-Import kategorieweise über Shopseiten (SB-151 bis SB-161); Erlaubnis liegt vor. |
| **D** | Feed-Quelle, sobald das Rüsthaus einen anbietet (SB-151). |
