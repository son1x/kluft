# Auftrag: Titelbilder der Georgsboten automatisch aus dem PDF

Stand: 07.11.2026 · Auftraggeber: DV Trier (Repository-Inhaber `son1x`)
Ziel-Branch für den Pull Request: `claude/kluft-instanzen`

Dieses Dokument reicht aus, um das Feature ohne Vorwissen zu bauen. Bitte
vor dem Start ganz lesen, besonders die Abschnitte „Regeln“ und
„Zusammenarbeit“.

---

## 1. Worum es geht

Kluft ist das CMS der DPSG-Gliederungen (Laravel 12, Filament 4, Livewire,
Alpine, Tailwind, PHP 8.3). Eine Codebasis bedient mehrere Instanzen
(`instanzen/dv-trier`, `instanzen/roden`, `instanzen/saar-hochwald`).

Der DV Trier hat ein Archiv seines Verbandshefts **Georgsbote**
(`/georgsbote`). Dort sind 17 Ausgaben (2017–2025) als PDF hochgeladen.
Daneben gibt es 37 **Georgsbriefe** (Newsletter als PDF). Jede
Georgsbote-Ausgabe kann ein **Titelbild** haben. Fehlt es, zeigt das
Archiv einen Platzhalter mit „Georgsbote 2/2019“.

**Ziel:** Kluft erzeugt das Titelbild automatisch aus **Seite 1 des PDFs**:
- für alle vorhandenen Ausgaben ohne Titelbild auf Knopfdruck,
- für einzelne Ausgaben auf der Bearbeiten-Seite.

**Nachtrag 10.11.2026:** Georgsbriefe bekommen jetzt auch ein Titelbild; das
Archiv zeigt es klein in der Liste, wenn es eins gibt. In der Übersicht gibt
es „Fehlende Titelbilder erzeugen“ und „Alle Titelbilder neu erzeugen“
(ersetzt auch vorhandene), jeweils für Georgsboten und Georgsbriefe auf
einmal. Die Abschnitte unten beschreiben den ursprünglichen Auftrag.

### Entscheidung: im Browser rendern (pdf.js), nicht auf dem Server

Der Webspace (Plesk, Shared Hosting) hat kein garantiertes Ghostscript,
Poppler oder Imagick mit PDF-Unterstützung, und eigenständige
Stammes-Installationen auf einfachem Webspace erst recht nicht. Deshalb:

- Der Browser der angemeldeten Person lädt das PDF, rendert Seite 1 mit
  **pdf.js** (`pdfjs-dist`) auf ein Canvas und schickt das Bild (WebP, sonst
  JPEG) an Kluft.
- Kluft prüft das Bild und speichert es als Titelbild.

Serverseitiges Rendern ist **nicht** Teil des Auftrags (siehe 8.).

---

## 2. Ausgangslage im Code

| Was | Wo |
|---|---|
| Model | `app/Models/GeorgsboteIssue.php`: `art` (`bote`/`brief`), `year`, `issue_no`, `title`, `inhalt`, `pdf_path` (Disk `local`, privat), `cover_path` (Disk `public`), `published`, `privacy_checked`, `published_at`, `downloads`. `coverUrl()`, `label()`, `istBrief()` |
| Tabelle | `georgsbote_issues`, Migrationen in `database/migrations/*georgsbote*` |
| Backend-Ressource | `app/Filament/Resources/GeorgsboteIssues/`: `GeorgsboteIssueResource.php` (Rechte: nur Rolle Verwaltung/Admin, Feature `Features::GEORGSBOTE`), `Pages/ListGeorgsboteIssues.php` (Reiter Georgsbote/Georgsbrief, Aktionen „Mehrere PDFs hochladen“ und „Infos aus der Archivliste übernehmen“), `Pages/EditGeorgsboteIssue.php`, `Pages/CreateGeorgsboteIssue.php`, `Schemas/GeorgsboteIssueForm.php` (Feld `cover_path`: FileUpload, Disk `public`, Ordner `georgsbote`), `Tables/GeorgsboteIssuesTable.php` |
| Öffentliche Seite | `app/Http/Controllers/Public/GeorgsboteController.php`, `resources/views/georgsbote/index.blade.php` (Titelbild links in der Karte, Format 3:4) |
| Routen | `routes/web.php`, Block `if (Features::enabled(Features::GEORGSBOTE))` |
| Tests | `tests/Feature/Georgsbote/GeorgsboteTest.php`, `tests/Feature/Georgsbote/GeorgsbriefArchivTest.php` (Muster für Helfer `issue()` und `verwaltung()`) |
| CSP | `app/Http/Middleware/SecurityHeaders.php`: `worker-src 'self' blob:`, `img-src 'self' data: blob:`, `connect-src 'self'`. Im Backend ist `'unsafe-inline'` erlaubt, neues JS trotzdem über Vite |
| Vorbild für JS im Backend | `resources/js/map.js` (MapLibre mit Worker per `?worker&url`), in `vite.config.js` als eigener Eintrag, eingebunden in `app/Providers/Filament/AdminPanelProvider.php` per `renderHook(PanelsRenderHook::HEAD_END, …, scopes: [...])` nur auf den Seiten, die es brauchen |
| Audit | `App\Support\AuditLogger::log('aktion', $user)` |

Der Deploy baut die Assets selbst (`npm ci && npm run build` in
`.github/workflows/deploy.yml`). Auf dem Server laufen kein npm und kein
composer.

---

## 3. Aufgaben

### A1 – PDF für angemeldete Redaktion ausliefern

- Neue Route, nur für Angemeldete mit Recht `update` auf die Ausgabe,
  z. B. `GET /admin/georgsbote/{issue}/pdf` (Name
  `georgsbote.vorschau-pdf`). Sie liegt in der Web-Middleware mit `auth`,
  nicht im öffentlichen Feature-Block.
- Liefert auch **unveröffentlichte** Ausgaben (die Titelbilder entstehen vor
  der Veröffentlichung), mit `Cache-Control: private, no-store` und
  `Content-Type: application/pdf`.
- Zählt **keine** Downloads (`downloads` bleibt unverändert).
- Gäste werden zur Anmeldung umgeleitet, Angemeldete ohne Recht bekommen 403.

### A2 – Titelbild entgegennehmen

- `POST /admin/georgsbote/{issue}/titelbild` (Name
  `georgsbote.titelbild`), gleiche Rechteprüfung, CSRF-geschützt (Token aus
  `<meta name="csrf-token">`, das Filament ausgibt).
- Validierung:
  - Pflichtfeld `bild`: `image`, `mimes:webp,jpeg,png`, höchstens 2 MB,
    `dimensions:max_width=2000,max_height=3000`.
  - **Kein SVG.**
  - Feld `ueberschreiben` (boolean).
- Nur für `art = bote`, bei `brief` 422.
- Speichern auf Disk `public` unter
  `georgsbote/titel-{year}-{issue_no}-{zufall}.{ext}`. Der zufällige Teil
  verhindert, dass Browser ein altes Bild aus dem Cache zeigen.
- `cover_path` wird nur gesetzt, wenn es leer ist oder `ueberschreiben`
  wahr ist. Ein ersetztes altes Bild wird gelöscht.
- Antwort als JSON `{ ok, cover_url }` bzw. `{ ok: false, grund }`.
- Audit-Eintrag `georgsbote.titelbild` (ohne Personendaten außer dem
  handelnden Konto, wie bei den übrigen Einträgen).

### A3 – Renderer im Browser

- Abhängigkeit `pdfjs-dist` in `package.json` mit **fester Version**
  (keine `^`-Spanne; eine Version, die mindestens zwei Wochen alt ist).
- Neue Datei `resources/js/titelbild.js`, in `vite.config.js` als eigener
  Eintrag.
- Worker wie bei MapLibre über Vite-URL-Import einbinden (gleiche Herkunft,
  passt zur CSP). Bei pdf.js `isEvalSupported: false` setzen.
- Funktion `renderSeiteEins(pdfUrl) → Blob`:
  - Seite 1 auf **800 px Breite** skalieren (Höhe nach Seitenverhältnis),
    weißer Hintergrund.
  - `canvas.toBlob('image/webp', 0.85)`. Liefert der Browser kein WebP,
    JPEG mit 0.85 nehmen.
- Fonts: Viele Hefte betten ihre Schriften ein. Falls pdf.js Standard-Fonts
  oder CMaps nachladen will, die Dateien aus `node_modules/pdfjs-dist` über
  Vite mit ausliefern (gleiche Herkunft) und `standardFontDataUrl` bzw.
  `cMapUrl` setzen. **Keine CDN-Adressen** (CSP, Datenschutz).
- Alpine-Komponente `titelbilder` (Registrierung auf `alpine:init`, weil
  Filament Alpine selbst mitbringt):
  - nimmt eine Liste `[{ id, label, pdfUrl, uploadUrl }]`,
  - arbeitet sie **nacheinander** ab (Speicher schonen) und zeigt den
    Fortschritt „3 von 17 – Ausgabe 1/2018“,
  - zeigt am Ende „Erzeugt: n, Fehler: m (Ausgabe …)“,
  - lädt danach die Tabelle neu (Livewire `$wire.$refresh()` oder ein
    Livewire-Event).
- Die Datei nur auf den Seiten laden, die sie brauchen: per `renderHook`
  mit `scopes: [ListGeorgsboteIssues::class, EditGeorgsboteIssue::class]`.

### A4 – Backend-Aktionen

- **Liste** (`ListGeorgsboteIssues`):
  - Kopfaktion „Titelbilder erzeugen“.
  - Sichtbar nur, wenn die Person anlegen darf und es Georgsboten ohne
    `cover_path` gibt.
  - Das Modal enthält die Alpine-Komponente mit allen Boten ohne
    Titelbild.
- **Bearbeiten** (`EditGeorgsboteIssue`):
  - Aktion „Titelbild aus PDF erzeugen“, nur bei `art = bote`.
  - Mit Bestätigung, wenn schon ein Titelbild da ist (`ueberschreiben`).
- **Nach „Mehrere PDFs hochladen“:** Sind neue Boten ohne Titelbild
  angelegt, trägt die Erfolgsmeldung einen Knopf „Titelbilder jetzt
  erzeugen“, der die Kopfaktion öffnet.

### A5 – Öffentliche Seite

- Keine neue Logik nötig, das Archiv nutzt bereits `coverUrl()`.
- Prüfen, dass das Bild im 3:4-Rahmen gut aussieht (`object-cover`) und
  `width`/`height` gesetzt sind, damit die Seite beim Laden nicht springt.

### A6 – Tests (PHPUnit, `#[Group('dv-trier')]`)

- A1:
  - Gast → Umleitung zur Anmeldung.
  - Autor ohne Recht → 403.
  - Verwaltung → 200 auch bei unveröffentlichter Ausgabe.
  - `downloads` bleibt unverändert.
- A2:
  - Ein gültiges WebP/PNG wird gespeichert und `cover_path` gesetzt.
  - Ein vorhandenes Titelbild bleibt ohne `ueberschreiben` und wird mit
    `ueberschreiben` ersetzt, das alte gelöscht.
  - SVG, PDF und über 2 MB werden abgelehnt.
  - Georgsbrief → 422.
  - Ohne Recht → 403.
- A4:
  - Die Aktion ist bei fehlenden Titelbildern sichtbar und unsichtbar, wenn
    alle eins haben.
  - Die Aktion auf der Bearbeiten-Seite fehlt bei Georgsbriefen.
- Das Rendern selbst (JS) wird nicht per PHPUnit getestet. Stattdessen
  manuell mit echten Heften prüfen (siehe 5.) und das Ergebnis im PR
  beschreiben, gern mit Screenshot.
- Vorhandene Tests dürfen nicht rot werden, besonders
  `tests/Feature/Auth/AdminSmokeTest.php` und die Georgsbote-Tests.

### A7 – Doku

- `CHANGELOG.md` unter der obersten Version: ein Spiegelstrich aus Sicht der
  Redaktion.
- `docs/pflichtenheft.md`, F-GB-01: „automatisch aus Seite 1 erzeugt“ als
  umgesetzt vermerken.
- `docs/abstimmung.md`: neuer Eintrag oben „… → Haupt, Stammes, Bezirk:
  Titelbilder aus PDF (gemeinsamer Kern)“ mit neuen Routen, neuer
  npm-Abhängigkeit und neuem Vite-Eintrag (Format steht oben in der Datei).

---

## 4. Abnahmekriterien

1. Auf test.dpsg-trier.de erzeugt „Titelbilder erzeugen“ für alle 17
   Georgsboten ein Titelbild. Es ist in der Backend-Tabelle und im Archiv
   sichtbar.
2. Ein vorhandenes, von Hand hochgeladenes Titelbild wird nicht ohne
   Nachfrage ersetzt.
3. Georgsbriefe bleiben ohne Titelbild, und es gibt keine Aktion dafür.
4. In der Browser-Konsole stehen keine CSP-Fehler, und es gehen keine
   Anfragen an fremde Server (Netzwerk-Tab).
5. Die Tests laufen für **alle drei** Instanzen durch, Pint meldet nichts.
6. Gäste und Rollen ohne Recht kommen weder an die PDFs noch an den Upload.

---

## 5. Lokal arbeiten und testen

```bash
composer install
npm ci && npm run build          # oder npm run dev
cp .env.example .env             # KLUFT_INSTANZ=dv-trier setzen
php artisan key:generate
php artisan kluft:assets         # Logo und Favicon der Instanz
php artisan migrate --seed       # Schema und Startinhalt
php artisan serve
```

Tests **nacheinander** ausführen, nicht parallel: Sie teilen sich
`Storage::fake` und werden sonst zufällig rot.

```bash
php artisan test                                   # DV Trier
KLUFT_INSTANZ=roden php artisan test               # Stamm
KLUFT_INSTANZ=saar-hochwald php artisan test       # Bezirk
vendor/bin/pint --dirty                            # Codestil
```

Zum manuellen Prüfen des Renderns braucht es ein echtes Heft-PDF. Bitte
**kein Georgsbote-PDF ins Repository legen.** Ein eigenes Test-PDF genügt,
idealerweise eines mit Fotos und eingebetteten Schriften. Die echten Hefte
prüft der Auftraggeber nach dem Deploy auf dem Testsystem.

---

## 6. Regeln des Repositorys (bitte einhalten)

- **Geheimnisse:** keine Schlüssel, Passwörter oder Tokens ins Repository,
  in Commits oder in Chats. Für dieses Feature braucht es keine.
- **Personendaten:** keine privaten Namen, Adressen oder E-Mails in Code,
  Tests oder Doku. Test-Konten wie `buero@dpsg-trier.de` (Funktionsadresse)
  sind in Ordnung.
- **JS nur über Vite/Alpine**, keine Inline-Skripte oder Inline-Handler im
  öffentlichen Teil (CSP). Keine externen CDNs.
- **Filament-Closures** bekommen Parameter über den **Namen**: `$record`,
  `$data`, `$query`, `$get`. Abkürzungen wie `$q` gehen nicht, dann kommt
  ein leeres Objekt an.
- **Migrationen:** Schema in `database/migrations`, Inhalte in
  `instanzen/<x>/migrations`. Bereits gelaufene Migrationen nie ändern. Für
  dieses Feature ist voraussichtlich **keine** Migration nötig
  (`cover_path` gibt es schon).
- **Oberflächentexte auf Deutsch**, kurz und aus Sicht der Redaktion.
  Kommentare im Code ebenfalls auf Deutsch, wie im übrigen Code.
- **Funktionsschalter:** Alles hängt am vorhandenen
  `Features::GEORGSBOTE`. Bei Roden und Saar-Hochwald ist es aus, dort darf
  sich nichts ändern.
- **Kein Deploy** durch den Auftragnehmer. Der Auftraggeber deployt nach
  dem Merge. GitHub-Actions-Minuten sind knapp (Free Tier): bitte keine
  Workflows starten.

---

## 7. Zusammenarbeit

1. Eigenen Branch von `claude/kluft-instanzen` abzweigen, z. B.
   `feature/titelbilder`.
2. Kleine, verständliche Commits; vor jedem Push Tests (alle drei
   Instanzen) und Pint.
3. Pull Request gegen `claude/kluft-instanzen`, Beschreibung mit:
   - was gebaut wurde,
   - wie getestet (inkl. manueller Render-Prüfung, Screenshot),
   - offene Punkte.
4. Nicht direkt auf `claude/kluft-instanzen` pushen.
5. Fragen zur Fachlichkeit an den Auftraggeber. Technische Hinweise für die
   anderen Sitzungen gehören als Eintrag in `docs/abstimmung.md`.

---

## 8. Nicht Teil des Auftrags

- Serverseitiges Rendern (Ghostscript, Poppler, Imagick). Kann später als
  Ergänzung kommen, wenn der Server es kann.
- Titelbilder für Georgsbriefe.
- Texterkennung oder Volltextsuche in den PDFs.
- Verkleinern der PDFs.
- Änderungen an Upload-Grenzen, Archivliste oder Reitern. Das ist bereits
  erledigt (Commits `c30c5e9`, `a444a8e`).

---

## 9. Offene Fragen (vor dem Start klären, falls relevant)

- Soll das Titelbild nach dem Upload eines **einzelnen** neuen Georgsboten
  (Formular „Erstellen“) automatisch entstehen oder nur per Knopf?
  Vorschlag: nur per Knopf, die Erfolgsmeldung weist darauf hin.
- Format: 800 px Breite reichen für die Karten im Archiv (angezeigt etwa
  96–200 px breit). Soll es größer sein, z. B. für Social-Media-Vorschauen?
  Vorschlag: 800 px.
