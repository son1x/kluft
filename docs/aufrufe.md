# Seitenaufrufe zählen

Funktion `aufrufe` („Seitenaufrufe zählen“, Bereich Website). Standard aus,
beim DV Trier an. Umschalten unter Verwaltung → Funktionen.

Kluft zählt Aufrufe öffentlicher Seiten **auf dem Server als reine Zähler**:
je Pfad und Tag eine Zahl. Kein JavaScript, kein Cookie, keine Einwilligung
nötig, keine zusätzliche Software.

## Was gespeichert wird

Nur die Tabelle `seitenaufrufe` mit drei Spalten:

| Spalte | Inhalt |
|---|---|
| `datum` | Tag des Aufrufs |
| `pfad` | aufgerufene Seite, z. B. `/aktuelles/sommerlager` (ohne Parameter, kleingeschrieben) |
| `aufrufe` | Anzahl |

Keine IP-Adresse (auch nicht gekürzt oder gehasht), kein User-Agent, keine
Sitzungs- oder Besucherkennung, kein Referrer. Damit gibt es keine Besuche,
Besucher, Verweildauer oder Wege durch die Seite. Das ist gewollt.

## Was gezählt wird und was nicht

Gezählt wird ein Aufruf nur, wenn alles zutrifft:

- Funktion an,
- `GET` (kein `HEAD`), Antwort 200 mit `text/html`,
- öffentlicher Pfad: nicht unter `admin`, `livewire…`, `filament`, `api`,
  `build`, `storage`, `uploads`, nicht die Anmeldung der Redaktion (`auth`,
  `redaktion`, `notfall-login`), keine Datei-Endung (`.pdf`, `.xml`, `.ics`
  …). PDFs des Georgsboten zählt der Georgsbote selbst,
- nicht angemeldet: weder Redaktion noch Teilnehmende („Meine Anmeldungen“),
- kein Bot: User-Agent nicht leer und ohne `bot`, `crawl`, `spider`,
  `curl`, `headless`, `whatsapp` usw. Der User-Agent wird nur geprüft,
  nie gespeichert,
- kein Vorabladen (`Sec-Purpose`/`Purpose: prefetch`),
- kein Widerspruch: `DNT: 1` (Do Not Track) oder `Sec-GPC: 1` (Global
  Privacy Control) zählen nicht.

Weiterleitungen und nicht vorhandene Seiten (404) zählen nicht.

Geschrieben wird erst **nach** der Antwort (`defer()`), die Seite wird
dadurch nicht langsamer. Ein Fehler beim Zählen macht keine Seite kaputt; er
landet höchstens als Warnung ohne Personendaten im Log.

## Wo man es sieht

Für Verwaltung, Vorstand und Admins, nur mit Funktion an:

- **Verwaltung → Aufrufe:** Zeitraum (letzte 7 Tage, 30 Tage, aktueller
  Monat, Vormonat, aktuelles Jahr), Summe und Verlauf als Grafik, Tabelle der
  meistaufgerufenen Seiten mit Titel (Beitrag, Veranstaltung, Seite,
  Startseite), sortier- und durchsuchbar, mit Link zur Seite.
- **Dashboard:** „Meistgelesen (30 Tage)“ mit den fünf meistaufgerufenen
  Seiten.

Beim **Bearbeiten eines Beitrags oder einer Seite** steht oben „Aufrufe:
30 Tage n, gesamt m“, sichtbar für alle, die ihn bearbeiten dürfen. So sehen
Autor*innen, ob ihr Beitrag gelesen wird.

## Löschfrist

Der Befehl `aufrufe:aufraeumen` läuft täglich um 03:40 Uhr und löscht
Zähler, die älter als **25 Monate** sind. Ein Vergleich mit dem Vorjahr bleibt
so möglich.

## Datenschutzerklärung

Vor dem Einschalten die Datenschutzerklärung ergänzen. Satz zum Kopieren
(beim DV als Ersatz für „Wir setzen keine Werkzeuge zur Webanalyse … ein“):

> Um zu erfahren, welche Inhalte gelesen werden, zählt die Website
> Seitenaufrufe. Gespeichert werden nur die aufgerufene Seite, der Tag und
> die Anzahl, keine IP-Adresse, kein Cookie und keine Kennung Ihres Geräts;
> eine Zuordnung zu Ihrer Person ist nicht möglich. Aufrufe mit aktiviertem
> „Do Not Track“ oder „Global Privacy Control“ zählen wir nicht. Die Zähler
> werden nach 25 Monaten gelöscht. Weitere Werkzeuge zur Webanalyse setzen
> wir nicht ein.

Beim DV setzt die Inhalts-Migration
`instanzen/dv-trier/migrations/2026_11_07_150100_datenschutz_seitenaufrufe.php`
den Satz, aber nur, wenn der alte Absatz noch wörtlich auf der Seite steht.
Eine im Backend bearbeitete Seite bleibt unverändert; dann den Satz von Hand
einfügen.

## Technik

| Was | Wo |
|---|---|
| Middleware (Gruppe `web`) | `app/Http/Middleware/AufrufZaehlen.php` |
| Pfad, Zählen, Auswertung | `app/Support/Aufrufe.php` |
| Tabelle, Model | `database/migrations/2026_11_07_150000_create_seitenaufrufe_table.php`, `app/Models/Seitenaufruf.php` |
| Backend | `app/Filament/Pages/Aufrufe.php`, `app/Filament/Widgets/AufrufeVerlauf.php`, `app/Filament/Widgets/Meistgelesen.php` |
| Aufräumen | `app/Console/Commands/AufrufeAufraeumenCommand.php`, `routes/console.php` |
| Tests | `tests/Feature/Aufrufe/AufrufeTest.php` |
