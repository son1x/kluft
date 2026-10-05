# Kluft: Inhalt raus aus den Migrationen

**Stand:** 5. Oktober 2026 · **Status:** umgesetzt

## Frage

Bis zur Umstellung auf Kluft haben viele Migrationen nicht nur Tabellen angelegt, sondern auch Inhalte des DV Trier: Seiten, Personen, Menüs, Bezirke und Stämme, Veranstaltungen, Bilder. Eine zweite Gliederung darf diese Inhalte nicht bekommen. Der Live-DV hat alle diese Migrationen aber schon ausgeführt, und sie dürfen dort weder ein zweites Mal laufen noch zurückgerollt werden.

## Möglichkeiten

| | A: Migrationen leeren, Inhalt in den DV-Seeder | B: Migrationen nur bei `KLUFT_INSTANZ=dv-trier` ausführen |
|---|---|---|
| Live-DV | Dateinamen bleiben gleich, also stehen sie als „gelaufen“ in der Tabelle `migrations`; es läuft nichts erneut, ein Rollback ist ein No-op | ebenfalls nichts erneut |
| Neue Gliederung | bekommt nur das Schema | jede Migration braucht eine DV-Abfrage, für immer |
| Code | Inhalt liegt an einer Stelle: `instanzen/dv-trier/seeders` | DV-Inhalt bleibt über ~25 Migrationen verteilt |
| Neue DV-Installation | `db:seed` erzeugt denselben Stand wie vorher | wie vorher |

## Entscheidung

**A.** Migrationen legen nur noch Schema an. Die 19 reinen Inhalts-Migrationen sind leere Hüllen mit gleichem Dateinamen. Bei 7 gemischten Migrationen bleibt der Schema-Teil unverändert, der Datenteil ist umgezogen.

Der Inhalt läuft jetzt als geordnete Schritte im DV-Seeder (`instanzen/dv-trier/seeders/InhaltSeeder.php`), in derselben Reihenfolge wie früher die Migrationen. Kein Daten-Schnappschuss: Die Schritte kopieren Bilder und PDFs aus `instanzen/dv-trier/dateien` in die Mediathek und erzeugen über die Models die Bildvarianten. Ein Schnappschuss der Datenbank könnte das nicht.

`db:seed` → `DatabaseSeeder` → `config dpsg.seeders.start` (beim DV `DvTrierSeeder`: Inhaltsschritte, dann Pflichtseiten und Demo-Inhalt, wie vorher).
Tests → `config dpsg.seeders.basis` (beim DV `InhaltSeeder`, wie früher die Migrationen in den Tests).

## Was sich ändert

- **Projektleitung als Superadmin:** Die Migration `2026_10_02_003004_grant_superadmin_to_project_lead` ist entfallen. Eine neue Installation richtet den ersten Zugang mit `php artisan cms:create-superadmin` ein (Notfall-Admin, siehe README).
- **Weiterleitungs-Cache leeren** (`2026_10_03_100100`): auf neuen Installationen ohne Bedeutung, entfallen.
- Inhaltsdateien liegen jetzt in `instanzen/dv-trier/dateien` statt in `database/content` und `database/seed-assets`.

## Nachweis

- Neue DV-Datenbank, alter gegen neuen Code: alle Tabellen Zeile für Zeile verglichen (gleiche Uhrzeit gesetzt).
  - Teststand: identisch, 3 Tabellen, 355 Zeilen.
  - Erstinstallation (migrate + db:seed): identisch bis auf den entfallenen Superadmin (1 Benutzer, 1 Rolle, 1 Audit-Eintrag).
  - Erzeugte Dateien (Gruppenfoto und Varianten): identisch.
- Bestehende Datenbank (Stand vor Kluft) mit neuem Code: `migrate --pretend` meldet „Nothing to migrate“, alle 52 Migrationen stehen als gelaufen.
- Testsuite grün.
