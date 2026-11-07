# Bericht: Code-Review Kluft und Titelbild-Auftrag

Stand: 07.11.2026 · Von: Auftragnehmer-Sitzung „Titelbilder“ · An: Haupt-Sitzung
(DV Trier und gemeinsamer Kern), zur Kenntnis Stammes und Bezirk

Dieser Bericht fasst drei Dinge zusammen:

1. was mit dem Auftrag `docs/auftrag-titelbilder.md` in den gemeinsamen Kern
   gekommen ist (gemergt, auf dem Testsystem deployt),
2. das Ergebnis eines Code-Reviews von Struktur und Qualität,
3. die daraus bereits behobenen Fehler und die offenen Empfehlungen.

Bitte zuerst Abschnitt 4 (Befunde) und 6 (Was ihr tun solltet) lesen.

---

## 1. Kurzfazit

Kluft ist für seine Größe (etwa 39.000 Zeilen in `app`, 36 Backend-Bereiche,
rund 490 Feature-Tests) **gut gebaut**. Struktur, Sicherheit, Tests und Doku
liegen deutlich über dem Durchschnitt vergleichbarer Projekte.

Das Review hat **einen echten Rechtefehler** (Mediathek) und **einen
Fehlerpfad im Backend** (NaMi-Erreichbarkeit) gefunden. Beide sind behoben.
Daneben gibt es Baustellen, die mit weiterem Wachstum teurer werden: zu große
Filament-Klassen, eine unscharfe Grenze zwischen `app/Support` und
`app/Services`, viel statischer Code und keine Unit-Tests.

---

## 2. Umfang und Vorgehen

- Grundlage: Stand `claude/kluft-instanzen` nach Merge von
  son1x/kluft#1 (`bd670ee`).
- Nicht jede Datei gelesen. Stattdessen:
  - Kennzahlen (Dateigrößen, Anzahl Klassen, Tests, Larastan-Baseline),
  - gezielte Suche nach Risikomustern: rohe HTML-Ausgabe (`{!! !!}`),
    rohes SQL (`*Raw`), `env()` außerhalb von `config/`, pauschales
    `catch (Throwable)`, Resources ohne Rechteprüfung,
  - Stichproben in den größten Klassen.
- Zwei Verdachtsfälle mit Tests nachgeprüft, bevor sie als Befund gelten
  (B1, B2).
- Volle Testsuite für dv-trier, roden und saar-hochwald nacheinander
  ausgeführt, dazu Pint.

---

## 3. Was gut ist (bitte so beibehalten)

| Bereich | Beobachtung |
|---|---|
| Aufbau | Laravel-/Filament-typisch: dünne Controller, Policies, Formulare und Tabellen in eigenen Klassen (`Schemas/`, `Tables/`). Neue Leute finden sich schnell zurecht. |
| Instanzen | Eine Codebasis, Unterschiede in `instanzen/<x>`. 24 Funktionsschalter (`App\Support\Features`) mit Standard je Instanz und Umschaltung im Backend. Ist ein Schalter aus, gibt es auch keine Routen und Menüpunkte. |
| Sicherheit | Strenge CSP (`SecurityHeaders`), keine CDNs. Audit-Log ohne Personendaten, gekürzte IPs. Alle `*Raw`-SQL-Stellen sind feste Ausdrücke ohne Nutzereingaben. `{!! !!}` nur mit vorherigem `e()` oder in reinen Text-Mails (`ContactMessageMail` nutzt `Content(text: …)`). |
| Tests | Rund 490 Feature-Tests, laufen für alle drei Instanzen. Der Deploy testet zusätzlich gegen MariaDB. |
| Statische Analyse | Larastan Stufe 5, Baseline mit nur 71 Einträgen. Neuer Code darf keine neuen Meldungen erzeugen. |
| Doku | Pflichtenheft mit Anforderungs-IDs, die im Code zitiert werden (z. B. F-GB-01). Changelog aus Sicht der Redaktion, `docs/abstimmung.md` als Briefkasten. |
| Sprache | Fachbegriffe, Kommentare und Oberfläche durchgehend Deutsch und einheitlich. |
| Deploy | Bewusst ohne `route:cache`, damit Funktionsschalter sofort wirken (Kommentar in `.github/workflows/deploy.yml`). Für Webspace vertretbar, siehe B10. |

---

## 4. Befunde

Priorität: **hoch** = Fehler mit Auswirkung im Betrieb, **mittel** =
erschwert Wartung spürbar, **niedrig** = Verbesserung.

| ID | Prio | Befund | Status |
|---|---|---|---|
| B1 | hoch | Mediathek ohne Rechteprüfung | **behoben** (`ffd54fc`, PR #2) |
| B2 | hoch | NaMi-Erreichbarkeit lässt Modal abbrechen | **behoben** (`e281a46`, PR #2) |
| B3 | mittel | `MitgliederAbgleichTest` macht echte HTTP-Anfragen | **behoben** (`e281a46`, PR #2) |
| B4 | mittel | Tests insgesamt nicht gegen echte Anfragen abgesichert | **umgesetzt** (Haupt, `tests/TestCase.php`) |
| B5 | mittel | Zu große Filament-Klassen | offen |
| B6 | mittel | Grenze `Support` / `Services` unscharf, viel statischer Code | offen |
| B7 | niedrig | Keine Unit-Tests für reine Rechenlogik | offen |
| B8 | niedrig | 48 × `catch (Throwable)` | offen (Durchsicht) |
| B9 | niedrig | Larastan-Baseline und Stufe | offen |
| B10 | niedrig | Routen hängen beim Laden an `Features::enabled` | bewusst so, nur Hinweis |

Nachtrag (07.11.2026, Haupt): B1 bis B3 sind mit son1x/kluft#2 nach
`claude/kluft-instanzen` gemergt, B4 ist dort umgesetzt.

### B1 – Mediathek ohne Rechteprüfung (behoben)

- **Befund:** `app/Filament/Resources/Media/MediaResource.php` hatte weder
  eine Policy noch eigene `can*`-Methoden. Filament erlaubt dann alles. Jede
  Person mit Backend-Zugang, auch die Rolle „Autor“, durfte alle
  Mediendateien ändern und löschen, einzeln und per Mehrfachauswahl.
- **Nachweis:** Mit einem Autor-Konto lieferten `canAccess`, `canCreate` und
  `canDeleteAny` alle `true`.
- **Soll:** Pflichtenheft 5.4, Zeile „Medien hochladen“ – Autor, Stufen- und
  Sektionsadmin ◐ (nur im eigenen Scope), ab Vorstand ● (voll).
- **Umsetzung:** neue `app/Policies/MediaPolicy.php` (per Namenskonvention
  automatisch gefunden):
  - `viewAny`, `view`, `create`: alle Rollen (Auswahl im Beitrag braucht das),
  - `update`, `delete`: Vorstand, Verwaltung, Admin und Super-Admin alles;
    übrige Rollen nur bei `uploaded_by` = eigenes Konto,
  - `deleteAny` (Mehrfachauswahl): nur Vorstand, Verwaltung, Admins.
- **Auslegung:** Medien haben keinen Scope (keine Stufe, keine Sektion).
  „Eigener Scope“ heißt deshalb „eigene Uploads“. Falls Stufenadmins künftig
  Medien ihrer ganzen Stufe pflegen sollen, braucht `media` eine
  Scope-Spalte.
- **Nebenwirkung:** Medien aus dem WordPress-Import haben kein
  `uploaded_by`. Sie können nur noch Vorstand, Verwaltung und Admins
  ändern.
- **Tests:** `tests/Feature/Content/MediaPolicyTest.php` (3 Tests). Ohne die
  Policy schlagen sie fehl.

### B2 – NaMi-Erreichbarkeit lässt Modal abbrechen (behoben)

- **Befund:** `NamiVerzeichnis::erreichbar()`
  (`app/Services/Mitglieder/NamiVerzeichnis.php`) fing nur
  `ConnectionException`. Lehnt ein Proxy oder eine Firewall die Anfrage ab,
  kommt eine `RequestException`. Sie lief bis in das Modal „Mitglieder
  prüfen“ in `RegistrationsRelationManager` durch: Fehlerseite statt
  „nicht erreichbar“.
- **Nachweis:** In einer Umgebung mit gesperrtem `nami.dpsg.de` war das
  Modal kaputt. Der Test unten schlägt ohne die Korrektur fehl.
- **Umsetzung:** `catch (ConnectionException|RequestException)` und `false`.
- **Test:** `test_nami_erreichbar_faengt_ablehnung_ab` in
  `tests/Feature/Mitglieder/MitgliederAbgleichTest.php`.

### B3 – Test mit echter HTTP-Anfrage (behoben)

- **Befund:** `MitgliederAbgleichTest > test_backend_actions` öffnet das
  Modal aus B2 und rief dabei wirklich `https://nami.dpsg.de` an. In der CI
  war das grün, ohne Netz rot.
- **Umsetzung:** In `setUp()` jetzt `Http::preventStrayRequests()` und ein
  Fake für die NaMi-Startseite (`config('mitglieder.nami.url')`).

### B4 – Tests allgemein nicht gegen echte Anfragen abgesichert (offen)

- Nur **eine** Testdatei nutzt `Http::preventStrayRequests()`, 16 nutzen
  `Http::fake()`. Jeder Test, der vergisst zu faken, geht still ins Netz
  (NaMi, Brevo, Meta, Instagram, Nextcloud). B3 war genau so ein Fall.
- **Empfehlung:** `Http::preventStrayRequests()` zentral in
  `tests/TestCase.php` (`setUp`) einschalten und die dann roten Tests mit
  passenden Fakes versehen. So fällt jede neue echte Anfrage sofort auf.

### B5 – Zu große Filament-Klassen (offen)

| Datei | Zeilen | Bemerkung |
|---|---:|---|
| `app/Filament/Resources/Events/RelationManagers/RegistrationsRelationManager.php` | 738 | 18 Aktionen in einer Klasse: Teilnehmende anlegen und importieren, Mitglieder prüfen, Export, Bescheinigungen, Dokumente, Beitrag, Warteliste … |
| `app/Support/Einrichtung/Installer.php` | 518 | Einrichtung in einem Stück |
| `app/Filament/Resources/Umfragen/UmfrageResource.php` | 331 | |
| `app/Filament/Resources/Users/Schemas/UserForm.php` | 302 | |

- **Empfehlung:** Das Projekt hat das Gegenmittel schon:
  `RelationManagers/Concerns/ZuschussAktionen.php`. Gleiches Muster für
  „Bescheinigungen“, „Dokumente“, „Mitglieder prüfen“ und „Teilnehmende
  eintragen“ anwenden. Ziel: unter 300 Zeilen je Klasse.
- Beim Umbau die Closure-Parameter weiter über den Namen injizieren
  (`$record`, `$data`), sonst kommen leere Objekte an.

### B6 – Grenze `Support` / `Services`, statischer Code (offen)

- `app/Support` hat 47 Klassen mit **248 statischen Methoden**. Nur 3 davon
  haben einen Konstruktor.
- Darunter ist viel Fachlogik: `Zahlungen`, `Sammelbestellung`,
  `SammelbestellungAblauf`, `Zuschuesse/*`, `Beitraege`, `Umfragen`. Ähnliche
  Logik liegt in `app/Services` (z. B. `Services/Zahlungen/*`,
  `Services/Sammelbestellung/*`). Man muss also für ein Thema an zwei Orten
  suchen.
- Statische Klassen holen sich ihre Abhängigkeiten (`Features`, `Cache`,
  `config()`) selbst. Das macht sie schwer gezielt testbar und verbirgt,
  was sie brauchen.
- **Empfehlung (schrittweise, nicht als Großumbau):**
  - Regel festhalten, z. B. in einer `docs/architektur.md`: Fachlogik in
    `app/Services/<Bereich>` als normale Klassen mit Konstruktor-Injektion;
    `app/Support` nur für kleine, zustandslose Helfer (`PhoneNumber`,
    `AuditText` …).
  - Neue Fachlogik gleich so anlegen. Bestehendes nur verschieben, wenn
    man die Klasse ohnehin anfasst.

### B7 – Keine Unit-Tests (offen)

- Es gibt nur `tests/Feature`, kein `tests/Unit`. Reine Rechenlogik wie
  `app/Support/Zuschuesse/Foerderrechner.php` (383 Zeilen) wird nur über
  Feature-Tests (`tests/Feature/Zuschuss/*`) mit Datenbank geprüft.
- **Empfehlung:** Für reine Rechner und Parser (Förderrechner,
  `GeorgsboteIssue::erkennen()`, `PhoneNumber`, GiroCode) Unit-Tests mit
  Tabellen von Fällen (`#[DataProvider]`). Das ist schneller, und
  Grenzfälle sind leichter zu ergänzen.

### B8 – 48 × `catch (Throwable)` (offen, Durchsicht)

- Am häufigsten in `WordPressImporter` (4), `Installer`, `ContentConverter`,
  `Updater`, `ContactController`, `RegistrationsRelationManager`,
  `CalendarCheckCommand` (je 2).
- Bei externen Diensten ist das oft gewollt. Einzeln geprüft habe ich die
  Stellen **nicht**.
- **Empfehlung:** Einmal durchgehen. Je Stelle sollte es entweder eine
  Log-Zeile oder eine sichtbare Meldung geben. Ein `catch` ohne beides
  verschluckt Fehler still. Wo möglich die konkrete Ausnahme fangen (wie
  jetzt in B2).

### B9 – Larastan-Baseline und Stufe (niedrig)

- 71 Einträge, häufigste Art `method.nonObject` (20), z. B. „Cannot call
  method format() on string“. Das sind fast immer Datumsfelder ohne Cast.
- Meiste Einträge in `WpImportCommand` (6), `Event` (5),
  `RegistrationsRelationManager` (4).
- **Empfehlung:** Fehlende `'datetime'`-Casts ergänzen, Baseline neu
  erzeugen (Workflow „Qualität“ hat dafür den Schalter `baseline`). Danach
  Stufe 6 versuchen.

### B10 – Routen hängen an Funktionsschaltern (Hinweis)

- `routes/web.php` registriert Routen nur, wenn `Features::enabled(…)`.
  Das funktioniert, weil der Deploy bewusst kein `route:cache` ausführt.
- Wer das später ändert (z. B. `php artisan optimize` auf einem vServer),
  muss den Routen-Cache beim Umschalten eines Funktionsschalters leeren.
  Sonst wirkt der Schalter erst nach dem nächsten Deploy.

---

## 5. Was der Titelbild-Auftrag in den Kern gebracht hat

Gemergt mit son1x/kluft#1, deployt auf `dv-trier` (Testsystem) am
07.11.2026, Lauf Nr. 123 erfolgreich. **Nicht** deployt: `dv-trier-live`,
`roden`, `saar-hochwald`.

| Was | Wo | Hinweis für euch |
|---|---|---|
| Neue Routen `georgsbote.vorschau-pdf`, `georgsbote.titelbild` | `routes/web.php`, `app/Http/Controllers/Redaktion/GeorgsboteTitelbildController.php` | Nur mit `Features::GEORGSBOTE`. Neuer Namespace `Controllers\Redaktion` für Routen der angemeldeten Redaktion außerhalb von Filament. |
| Gäste auf `auth`-Routen → `/admin/login` | `bootstrap/app.php` (`redirectGuestsTo`) | Gilt für **alle** Instanzen. Vorher gab es kein Ziel (Laravel suchte eine Route `login`). Neue Routen mit `auth` funktionieren jetzt einfach. |
| `pdfjs-dist` 6.4.299 (feste Version) | `package.json` | Zieht optionale `@napi-rs/canvas-*`-Pakete in den Lockfile. Nur für Node, nicht im Bundle. |
| Vite: eigener Eintrag `resources/js/titelbild.js` | `vite.config.js` | Neu sind `build.assetsInlineLimit` und `rollupOptions.output.assetFileNames`. pdf.js-Daten landen unter `public/build/pdfjs-<version>/…`. **Alle anderen Assets** wie bisher unter `assets/[name]-[hash]`. Wer `vite.config.js` ändert, bitte diese Funktion erhalten. |
| Render-Hook nur auf Georgsbote-Seiten | `AdminPanelProvider` | Gleiches Muster wie `map.js`. |
| Trait `MeldetTitelbilder` | `app/Filament/Resources/GeorgsboteIssues/Pages/Concerns/` | Livewire-Event `titelbilder-erzeugt` → Meldung und Neuladen. |
| Scope `ohneTitelbild()`, `titelbildAuftrag()` | `app/Models/GeorgsboteIssue.php` | |
| Titelbild-Spalte in der Tabelle | `GeorgsboteIssuesTable` | Nur im Reiter Georgsbote. |
| Archiv: feste Bildmaße | `resources/views/georgsbote/index.blade.php` | `width`/`height`, `object-top`. |
| Tests | `tests/Feature/Georgsbote/GeorgsboteTitelbildTest.php` | 10 Tests. |

Manuell geprüft mit Chromium und eigenen Test-PDFs (eingebettete und nicht
eingebettete Schriften, Foto, CMYK-Foto): keine CSP-Fehler, keine
Konsolenfehler, keine Anfragen an fremde Hosts. Die echten 17 Hefte auf
test.dpsg-trier.de prüft der Auftraggeber.

---

## 6. Was ihr tun solltet

**Haupt-Sitzung**

1. Branch `claude/upbeat-fermi-dobmt7` (B1 bis B3) reviewen und mergen, falls
   der Auftraggeber das nicht schon veranlasst hat.
2. B4 umsetzen: `Http::preventStrayRequests()` in `tests/TestCase.php`.
   Kleiner Aufwand, großer Nutzen.
3. Bei der nächsten Arbeit an Veranstaltungen B5 angehen
   (`RegistrationsRelationManager` aufteilen).
4. Regel aus B6 in die Doku schreiben und ab sofort anwenden.
5. B8 und B9 bei Gelegenheit.

**Stammes- und Bezirks-Sitzung**

- B1 betrifft euch direkt: Autor*innen und Stufenadmins können fremde
  Medien nicht mehr ändern oder löschen. Falls das bei euch anders gewollt
  ist, bitte über den Auftraggeber klären, nicht die Policy lokal
  aufweichen.
- `redirectGuestsTo` (Abschnitt 5) gilt auch bei euch.
- Eure Tests mit externen Diensten bitte ebenfalls ohne echte Anfragen
  (B4).

---

## 7. Hinweise zur Arbeitsumgebung (Cloud-Sitzungen)

- **`composer install` schlug fehl** mit „Could not authenticate against
  github.com“. Die GitHub-API lieferte 403 für Paket-Archive fremder
  Repositories. Abhilfe: `composer install --prefer-source` (klont per git).
  `phpstan/phpstan` hat keine Quelle im Lockfile; das Archiv wurde von Hand
  per `git clone` erzeugt und in den Composer-Cache gelegt.
- **Tests brauchen gebaute Assets:** Ohne `npm run build` scheitern
  Seiten-Tests mit „Vite manifest not found“.
- **Tests nacheinander** pro Instanz ausführen (gemeinsames
  `Storage::fake`), wie in `docs/auftrag-titelbilder.md` beschrieben.

## 8. Prüfbefehle

```bash
php artisan test
KLUFT_INSTANZ=roden php artisan test
KLUFT_INSTANZ=saar-hochwald php artisan test
vendor/bin/pint --test
```

Ergebnis am 07.11.2026 auf `claude/upbeat-fermi-dobmt7` (`b55cbaa`):

| Instanz | Bestanden | Übersprungen | Fehlgeschlagen |
|---|---:|---:|---:|
| dv-trier | 478 | 13 | 0 |
| roden | 392 | 99 | 0 |
| saar-hochwald | 389 | 102 | 0 |

Pint: keine Meldungen.
