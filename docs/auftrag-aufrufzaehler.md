# Auftrag: Seitenaufrufe zählen (ohne Personenbezug)

Stand: 07.11.2026 · Auftraggeber: DV Trier (Repository-Inhaber `son1x`)
Ziel-Branch für den Pull Request: `claude/kluft-instanzen`

Dieses Dokument reicht aus, um das Feature ohne Vorwissen zu bauen. Bitte
vor dem Start ganz lesen, besonders die Abschnitte 6 („Regeln“) und 7
(„Zusammenarbeit“).

---

## 1. Worum es geht

Kluft ist das CMS der DPSG-Gliederungen (Laravel 12, Filament 4, Livewire,
Alpine, Tailwind, PHP 8.3). Eine Codebasis bedient mehrere Instanzen
(`instanzen/dv-trier`, `instanzen/roden`, `instanzen/saar-hochwald`).

Die Redaktion möchte wissen, **welche Seiten, Beiträge und Veranstaltungen
wie oft aufgerufen werden**. Matomo oder ein anderes Analyse-Werkzeug ist
bewusst **nicht** gewünscht: keine zusätzliche Software, kein JavaScript,
keine Cookies, keine Einwilligung.

**Ziel:** Kluft zählt Aufrufe öffentlicher Seiten **serverseitig als reine
Zähler**, je Pfad und Tag. Im Backend zeigt es die Zahlen an.

Grundsatz: **Es wird nichts gespeichert, was sich einer Person zuordnen
lässt.** Keine IP-Adresse (auch nicht gekürzt oder gehasht), kein
User-Agent, keine Sitzungs- oder Besucherkennung, kein Referrer mit
Pfad oder Parametern. Gespeichert werden nur „Pfad, Tag, Anzahl“. Damit ist
das wie der vorhandene Download-Zähler des Georgsboten
(`GeorgsboteIssue::downloads`), nur für alle öffentlichen Seiten.

Es gibt also **keine** Besuche oder Besucher, keine Verweildauer und keine
Wege durch die Seite. Das ist gewollt.

---

## 2. Ausgangslage im Code

| Was | Wo |
|---|---|
| Funktionsschalter | `app/Support/Features.php` (Konstanten, `KATALOG` mit `bereich`, `label`, `text`, `standard`, optional `ab`, `braucht`). Die Instanz schaltet in `instanzen/<x>/config.php` unter `features` ein. Im Backend umschaltbar (Verwaltung → Funktionen). |
| Öffentliche Routen | `routes/web.php`, z. B. `/` (`home`), `/aktuelles/{slug}` (`posts.show`), `/veranstaltungen/{slug}` (`events.show`), Fachbereiche `/{section}/…`, Seiten `/{slug}` (`pages.show`, ganz unten) |
| Middleware | `bootstrap/app.php` (`$middleware->web(append: …)`), Beispiele `app/Http/Middleware/SecurityHeaders.php`, `TestsystemSchutz.php` |
| Backend-Bereich | Filament-Panel `admin` (`app/Providers/Filament/AdminPanelProvider.php`), Widgets per `discoverWidgets(in: app_path('Filament/Widgets'))`, der Ordner existiert noch nicht. Seiten unter `app/Filament/Pages`, Gruppe „Verwaltung“ z. B. in `NamiTestabfrage.php` |
| Rollen | `App\Models\User`: `isAdmin()`, `isVerwaltung()`, `hasRole('vorstand')` usw. |
| Zeitgesteuerte Aufgaben | `routes/console.php` (Scheduler), Beispiel `audit:prune` → `app/Console/Commands/PruneAuditLogCommand.php` |
| Datenschutzerklärung DV | `instanzen/dv-trier/dateien/datenschutz.html`, Abschnitt II, Absatz „Wir setzen keine Werkzeuge zur Webanalyse …“. Die Seite wird per Inhalts-Migration gesetzt (`instanzen/dv-trier/migrations/2026_11_07_140000_datenschutzerklaerung_abgestimmt.php`). |
| Testsystem | `App\Support\Testsystem`, Band und noindex auf test.dpsg-trier.de |
| Tests | `tests/Feature/...`; Muster für Feature-Tests mit Instanz-Gruppe z. B. `tests/Feature/Georgsbote/GeorgsbriefArchivTest.php`. `tests/TestCase.php` sperrt echte HTTP-Anfragen. |

---

## 3. Aufgaben

### A1 – Funktionsschalter

- `Features::AUFRUFE = 'aufrufe'`, Bereich „Website“, Label „Seitenaufrufe
  zählen“.
- Text sinngemäß: „Zählt Aufrufe öffentlicher Seiten je Tag, ohne
  IP-Adresse, Cookie oder Skript; Übersicht im Backend. Vor dem Einschalten
  die Datenschutzerklärung um einen Satz ergänzen.“
- `standard` => `false`. Beim DV in `instanzen/dv-trier/config.php`
  einschalten, bei Roden und Saar-Hochwald nicht.
- Ist der Schalter aus, wird nichts gezählt und das Backend zeigt nichts an.

### A2 – Tabelle

- Migration in `database/migrations` (Schema, kein Inhalt): Tabelle
  `seitenaufrufe` mit
  - `datum` (date),
  - `pfad` (string 255, normalisiert, siehe A3),
  - `aufrufe` (unsigned int, Standard 0),
  - eindeutiger Index (`datum`, `pfad`) und Index auf `pfad`.
- Model `App\Models\Seitenaufruf`, ohne Timestamps.

### A3 – Zählen

Eine Middleware in der `web`-Gruppe, z. B. `App\Http\Middleware\AufrufZaehlen`.

**Gezählt wird nur, wenn alle Bedingungen zutreffen:**
- Schalter `AUFRUFE` an,
- Methode `GET` (kein `HEAD`),
- Antwort-Status 200, `Content-Type` beginnt mit `text/html`,
- Pfad ist **öffentlich**, also nicht unter `admin`, `livewire*`, `filament`,
  `api`, `build`, `storage`, `uploads` und keine Datei-Endung wie
  `.pdf`, `.xml`, `.ics`, `.json`, `.txt`, `.webmanifest`. PDFs zählt der
  Georgsbote selbst.
- **Nicht** angemeldet (Redaktion und Teilnehmenden-Login zählen nicht mit,
  `auth()->check()` bzw. der Guard der Teilnehmenden, falls vorhanden),
- **kein Bot:** User-Agent leer oder passend zu einer Liste wie
  `bot|crawl|spider|slurp|preview|monitor|uptime|curl|wget|python|httpclient|headless|lighthouse|facebookexternalhit|whatsapp`
  (case-insensitive). Der User-Agent wird nur geprüft, **nie gespeichert**.
- **Kein Vorabladen:** Header `Sec-Purpose` oder `Purpose` mit `prefetch`
  zählt nicht.
- **Kein Widerspruch:** Header `DNT: 1` oder `Sec-GPC: 1` zählen nicht.
  Rechtlich nicht nötig, aber ein freiwilliges Zeichen gegenüber der
  Datenschutzstelle.

**Pfad normalisieren:**
- ohne Query-String und ohne Fragment,
- ohne abschließenden `/`, Startseite = `/`,
- nur Kleinbuchstaben, höchstens 255 Zeichen.
- Pfade, die es nicht gibt, fallen schon durch die Bedingung „Status 200“
  heraus.

**Schreiben:**
- Erst **nach** der Antwort, mit Laravels `defer()` oder
  `terminate()`, damit die Seite nicht langsamer wird.
- Als Upsert mit Erhöhung (`INSERT … ON DUPLICATE KEY UPDATE aufrufe =
  aufrufe + 1` bzw. `upsert()` oder `updateOrInsert` + `increment` in
  einer Transaktion). Es muss mit MariaDB (Server) **und** SQLite (Tests)
  funktionieren.
- Fehler beim Zählen dürfen eine Seite **nie** kaputt machen: abfangen,
  höchstens eine Warnung ins Log, ohne Personendaten.

### A4 – Aufräumen

- Befehl `aufrufe:aufraeumen`: löscht Zeilen, die älter als 25 Monate
  sind (Vorjahresvergleich bleibt möglich).
- Im Scheduler täglich, z. B. 03:40 Uhr, in `routes/console.php` neben den
  anderen Aufräumbefehlen.

### A5 – Backend

- **Seite „Aufrufe“**, Gruppe „Verwaltung“, sichtbar für Verwaltung,
  Vorstand und Admins und nur mit Schalter an.
  - Zeitraum wählbar: letzte 7 Tage, 30 Tage, aktueller Monat, Vormonat,
    aktuelles Jahr. Standard: 30 Tage.
  - Summe im Zeitraum und Verlauf je Tag bzw. Monat als einfache Grafik.
    Filament `ChartWidget` (Chart.js ist bei Filament dabei und kommt vom
    eigenen Server, keine CDN).
  - Tabelle der meistaufgerufenen Pfade mit Aufrufen im Zeitraum,
    sortierbar und durchsuchbar.
  - Zu jedem Pfad, wo möglich, der **Titel** statt nur des Pfads:
    - `/aktuelles/{slug}` → Beitragstitel,
    - `/veranstaltungen/{slug}` → Titel der Veranstaltung,
    - `/{slug}` → Seitentitel,
    - `/` → „Startseite“.
    - Sonst bleibt der Pfad stehen.
  - Die Zeilen verlinken auf die öffentliche Seite.
  - Hinweis oben auf der Seite: „Gezählt werden Aufrufe ohne Bots und ohne
    angemeldete Redaktion. Es werden keine Daten über Besucher*innen
    gespeichert.“
- **Dashboard-Widget** „Meistgelesen (30 Tage)“ mit den Top 5 und Link zur
  Seite „Aufrufe“, gleiche Sichtbarkeit.
- **Beim Bearbeiten eines Beitrags und einer Seite:** dezente Angabe
  „Aufrufe: 30 Tage n, gesamt m“, z. B. als Platzhalter-Feld oder
  Beschreibung im Formular. Sichtbar für alle, die den Beitrag bearbeiten
  dürfen. Autor*innen sollen sehen, ob ihr Beitrag gelesen wird.

### A6 – Datenschutzerklärung DV

- In `instanzen/dv-trier/dateien/datenschutz.html`, Abschnitt II, den
  Absatz „Wir setzen keine Werkzeuge zur Webanalyse oder
  Reichweitenmessung ein. …“ ersetzen durch sinngemäß:

  > Um zu erfahren, welche Inhalte gelesen werden, zählt die Website
  > Seitenaufrufe. Gespeichert werden nur die aufgerufene Seite, der Tag und
  > die Anzahl, keine IP-Adresse, kein Cookie und keine Kennung Ihres
  > Geräts; eine Zuordnung zu Ihrer Person ist nicht möglich. Aufrufe mit
  > aktiviertem „Do Not Track“ oder „Global Privacy Control“ zählen wir
  > nicht. Die Zähler werden nach 25 Monaten gelöscht. Weitere Werkzeuge zur
  > Webanalyse setzen wir nicht ein. Schriftarten, Karten und alle übrigen
  > Bestandteile der Website liefern wir von unserem eigenen Server aus;
  > beim bloßen Aufruf werden keine Daten an fremde Anbieter übermittelt.

- **Neue Inhalts-Migration** in `instanzen/dv-trier/migrations`, die den
  alten Absatz auf der Seite `datenschutz` ersetzt, **nur wenn er dort
  wörtlich noch steht**. Ist die Seite im Backend bearbeitet worden, nichts
  tun. Vorhandene Migrationen **nicht ändern**.
- `tests/Feature/Content/DatenschutzerklaerungTest.php` anpassen bzw.
  ergänzen.
- Hinweis: Den Satz muss der Auftraggeber noch mit der Datenschutzstelle
  abstimmen. Das steht in der PR-Beschreibung.

### A7 – Tests (PHPUnit)

- Ein normaler Aufruf von `/`, einer Seite und eines Beitrags zählt genau
  einmal; ein zweiter Aufruf am selben Tag erhöht auf 2.
- **Nicht** gezählt werden:
  - Bot-User-Agent, `HEAD`, 404, Weiterleitungen,
  - `/admin/...`, `/api/status`, PDF-Routen,
  - Angemeldete,
  - `DNT: 1`, `Sec-GPC: 1`, Prefetch.
- Query-Strings werden entfernt (`/aktuelles?seite=2` → `/aktuelles`).
- Die Tabelle enthält nach den Aufrufen **nur** die Spalten `datum`,
  `pfad` und `aufrufe` mit erwarteten Werten. Keine IP und kein
  User-Agent landen in der Datenbank. Am besten über einen Test, der
  alle Spaltennamen prüft.
- Schalter aus → nichts gezählt, Backend-Seite und Widget unsichtbar.
- Rechte: Autor*in sieht die Seite „Aufrufe“ nicht, Verwaltung schon.
- Aufräumbefehl löscht nur Zeilen älter als 25 Monate.
- Eine Ausnahme beim Schreiben (z. B. fehlende Tabelle) liefert trotzdem
  Status 200.
- Inhalts-Migration der Datenschutzerklärung: ersetzt den Absatz nur, wenn
  er noch wörtlich da ist.
- `tests/Feature/Auth/AdminSmokeTest.php` muss grün bleiben. Falls er alle
  Backend-Seiten aufruft, das neue Feature dort wie andere Features
  behandeln.

### A8 – Doku

- `CHANGELOG.md`, oberste Version: ein Spiegelstrich aus Sicht der
  Redaktion.
- `docs/abstimmung.md`: neuer Eintrag oben, Absender **„Auftragnehmer
  Aufrufzähler → Haupt, Stammes, Bezirk“** (nicht „Haupt“), mit:
  - neuer Tabelle, neuer Middleware in der `web`-Gruppe, neuem Schalter
    (bei Stämmen aus), neuem Scheduler-Befehl,
  - dem Hinweis, dass Stämme vor dem Einschalten ihre
    Datenschutzerklärung ergänzen müssen.
- Kurze Doku `docs/aufrufe.md`: was gezählt wird, was nicht, wo man es
  sieht, Löschfrist, Satz für die Datenschutzerklärung zum Kopieren.

---

## 4. Abnahmekriterien

1. Nach dem Deploy auf test.dpsg-trier.de steigen die Zähler beim Aufruf
   öffentlicher Seiten in einem privaten Browserfenster. Mit Anmeldung im
   Backend steigen sie nicht.
2. In der Datenbank stehen nur Pfad, Tag und Anzahl.
3. Seite „Aufrufe“, Widget und Angabe beim Bearbeiten erscheinen nur mit
   Schalter an und nur für die genannten Rollen.
4. Keine messbare Verzögerung der Seiten, weil das Schreiben nach der
   Antwort erfolgt.
5. Tests für **alle drei** Instanzen grün, `vendor/bin/pint --test` ohne
   Meldungen.
6. Roden und Saar-Hochwald: ohne Einschalten keine Änderung, nichts wird
   gezählt.

---

## 5. Lokal arbeiten und testen

```bash
composer install                 # bei 403 von GitHub: composer install --prefer-source
npm ci && npm run build          # Tests brauchen das Vite-Manifest
cp .env.example .env             # KLUFT_INSTANZ=dv-trier setzen
php artisan key:generate
php artisan kluft:assets
php artisan migrate --seed
php artisan serve
```

Tests **nacheinander** ausführen, nicht parallel, weil sie sich
`Storage::fake` teilen:

```bash
php artisan test
KLUFT_INSTANZ=roden php artisan test
KLUFT_INSTANZ=saar-hochwald php artisan test
vendor/bin/pint --test
```

---

## 6. Regeln des Repositorys (bitte einhalten)

- **Geheimnisse:** keine Schlüssel, Passwörter oder Tokens ins Repository,
  in Commits oder Chats. Für dieses Feature braucht es keine.
- **Personendaten:** keine privaten Namen, Adressen oder E-Mails in Code,
  Tests, Doku. Funktionsadressen wie `buero@dpsg-trier.de` sind in
  Ordnung.
- **Kein JavaScript für das Zählen.** Neues JS im öffentlichen Teil nur über
  Vite/Alpine, keine Inline-Skripte, keine CDNs (CSP).
- **Filament-Closures** bekommen Parameter über den **Namen**: `$record`,
  `$data`, `$query`, `$get`. Abkürzungen wie `$q` liefern ein leeres
  Objekt.
- **Migrationen:** Schema in `database/migrations`, Inhalte in
  `instanzen/<x>/migrations`. Bereits gelaufene Migrationen nie ändern.
- **Oberfläche und Kommentare auf Deutsch**, kurz, aus Sicht der Redaktion.
- **Externe Anfragen in Tests** sind gesperrt (`Http::preventStrayRequests()`
  in `tests/TestCase.php`). Für dieses Feature braucht es keine.

---

## 7. Zusammenarbeit

1. Eigenen Branch von `claude/kluft-instanzen` abzweigen, z. B.
   `feature/aufrufe`.
2. Vor jedem Push: Tests aller drei Instanzen und Pint.
3. Pull Request gegen `claude/kluft-instanzen`. In die Beschreibung:
   - was gebaut wurde,
   - wie getestet,
   - offene Punkte,
   - den Hinweis auf den Satz in der Datenschutzerklärung.
4. **Nicht selbst mergen und nicht deployen**, es sei denn, der
   Auftraggeber sagt es ausdrücklich. GitHub-Actions-Minuten sind knapp
   (Free Tier).
5. **Nur diesen Auftrag umsetzen.** Fällt beim Arbeiten etwas anderes auf
   (Fehler, Verbesserungen, Review-Befunde), nicht selbst ändern. Stattdessen:
   - in der PR-Beschreibung unter „Aufgefallen“ notieren oder
   - als eigenen Eintrag in `docs/abstimmung.md` vorschlagen.
   Änderungen am gemeinsamen Kern außerhalb dieses Auftrags vorher mit der
   Haupt-Sitzung abstimmen.
6. Einträge in `docs/abstimmung.md` immer mit dem **eigenen** Absender
   („Auftragnehmer Aufrufzähler“).

---

## 8. Nicht Teil des Auftrags

- Matomo, Plausible, AWStats oder andere Analyse-Software.
- Besucher, Sitzungen, Verweildauer, Herkunft (Referrer), Geräte, Länder.
- Zählen im Backend, von PDFs (außer dem vorhandenen Georgsbote-Zähler)
  oder von Bildern.
- Export der Zahlen. Kann später kommen.
