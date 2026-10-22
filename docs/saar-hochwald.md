# Bezirks-Website Saar-Hochwald – Übergabe

Stand: 22.10.2026. Arbeitsgrundlage für eine eigene Claude-Code-Sitzung, die
nur die Website des Bezirks Saar-Hochwald betreut (wie `docs/roden.md` für
den Stamm Roden). Allgemeines zu Kluft steht in `README.md`,
`docs/deploy.md` und `docs/abstimmung.md`.

## Worum es geht

- **Bezirk:** DPSG Bezirk Saar-Hochwald, Diözesanverband Trier.
  - Fünf Stämme zwischen Saarlouis, Rehlingen-Siersburg und Perl.
  - Anlaufstelle ist die Bezirksleitung (`bezirksleitung@dpsg-saar-hochwald.de`), Donatusstraße 34a, 66740 Saarlouis.
- **Domain:** `dpsg-saar-hochwald.de`. Die bisherige Bezirkswebsite kommt aus dem Repository `son1x/dpsg-bezirk-saar-hochwald` (Release 1.0); dieses Repository bleibt unverändert.
- **Software:** Kluft (Laravel 12, Filament 4, Livewire, PHP 8.3). Ein Code für mehrere Instanzen:
  - `dv-trier`: test.dpsg-trier.de
  - `saar-hochwald`: dieser Bezirk
  - `roden`: Stamm Roden (www.dpsg-roden.de)
- **Repository:** `son1x/kluft`, Branch `claude/kluft-instanzen`.
  - Auf diesem Branch arbeiten auch die Haupt-Sitzung (DV Trier, gemeinsamer Kern) und die Stammes-Sitzung (Roden).
  - Vor jedem Push `git pull --rebase` ausführen und nur auf diesen Branch pushen.
  - Keine Pull Requests, außer sie werden ausdrücklich verlangt.

## Stand

- Die Instanz liegt seit dem 5.10.2026 im Repository (Gerüst aus der alten Bezirkswebsite). Die Tests laufen in der CI für jede Instanz mit.
- **Noch nicht auf Kluft live:** Im Environment `saar-hochwald` ist keine `MONITOR_URL` gesetzt (das Monitoring meldet „nicht eingerichtet“). Ob schon ein Deploy gelaufen ist, steht in Actions → „Deploy“ beim Lauf mit Instanz `saar-hochwald`.
- Die Inhalte sind ein Startgerüst und noch mit dem Bezirk abzustimmen (siehe „Offene Punkte“).

## Was zum Bezirk gehört

| Datei | Inhalt |
|---|---|
| `instanzen/saar-hochwald/config.php` | Name und Wortformen, Kontakt, Karte, Gremien, Newsletter, Startseiten-Kacheln, Einwilligungstext, Funktionen |
| `instanzen/saar-hochwald/README.md` | Kurzbeschreibung der Instanz und Regeln für die Inhalte |
| `instanzen/saar-hochwald/seeders/` | `BasisSeeder` (Grundbestand: Bezirk, Stämme, Glossar; läuft auch in den Tests), `SaarHochwaldSeeder` (Startinhalt beim ersten Deploy: Pflichtseiten, Startseite, Weiterleitungen der alten `.html`-Adressen, Kontaktthemen) und die einzelnen Inhalts-Seeder |
| `instanzen/saar-hochwald/public/` | Logo, Favicon, Titelbild (Saarschleife, CC BY-SA 4.0) |
| `instanzen/saar-hochwald/migrations/` | noch leer; Inhalte für eine schon laufende Installation kommen hierher, nie nach `database/migrations` (`docs/entscheidungen/kluft-inhalt-und-schema.md`) |
| `tests/Feature/Instanzen/SaarHochwaldTest.php` | Tests nur für diese Instanz (`#[Group('saar-hochwald')]`) |

### Konfiguration (Auszug)

- **Ebene:** `level` ist `Bezirk`. Stammes-Funktionen (Kasse, „Leiter*in werden“, Stufenfarben für Stämme) erscheinen hier nicht.
- **Arbeitskreise:** heißen „Bezirksarbeitskreis“.
- **Funktionen:**
  - aus: Georgsbote, Roadmap, Rechtsträger, WordPress-Import;
  - an: Anmeldung per E-Mail-Code (`code_login`).
  - Weitere schalten Admins unter Verwaltung → Funktionen.
- **Mail-Adressen von Personen:** nach dem Schema `Initial.Nachname@dpsg-saar-hochwald.de` (`person_email_domain`).
- **Karte:** eigene Kartendatei `maps/data/saar-hochwald-20260930-z13.pmtiles`. Der Deploy baut sie beim ersten Mal (dauert einige Minuten) und holt sie danach aus dem Cache.
- **Newsletter:** über Brevo, Seite `/newsletter`.

**Seeder legen nur einmal an:** Sie laufen beim ersten Deploy. Später gepflegte Inhalte nie überschreiben (`firstOrCreate` statt `updateOrCreate`). Änderungen an einer laufenden Website über das Backend oder eine Inhalts-Migration im Instanz-Ordner.

## Server

- **Hosting:** Netcup-Webhosting mit Plesk, derselbe Server wie test.dpsg-trier.de und www.dpsg-roden.de.
  - Für den Bezirk gibt es dort die Ordner `/dpsg-saar-hochwald.de/`, `/events.dpsg-saar-hochwald.de/` und `/cloud.dpsg-saar-hochwald.de/`.
  - Die Ordner anderer Domains nie anfassen.
- **Dokumentstamm:** Plesk lässt ihn für die Hauptdomain vermutlich nicht ändern (wie bei Roden). Dann gilt dieselbe Lösung wie bei test.dpsg-trier.de und Roden:
  - Kluft liegt direkt in `httpdocs`;
  - die Root-`.htaccess` leitet nach `public/` weiter und sperrt `.env`, `storage`, `vendor` usw.
- **Alte Website:** Liegt in `httpdocs` noch die alte Website, bricht der erste Deploy ab und zeigt, was dort liegt. Die Schutzregel verlangt einen leeren Ordner.
  - Erst sichern, dann leeren.
  - Oder zuerst in einen neuen Ordner deployen und danach umbenennen; so war es bei Roden.
- **Datenbank:** eigene MySQL-/MariaDB-Datenbank für den Bezirk anlegen (Plesk → Datenbanken).
- **Cronjob:** in Plesk jede Minute `php …/httpdocs/artisan schedule:run`. Ohne ihn laufen keine Warteschlange, Mails, Backups oder Löschfristen.
- **Bekannt aus Roden:** Ein frischer Server braucht die Laufzeitordner unter `storage/`. Die legt der Deploy inzwischen selbst an.

## Deploy

- **Nur von Hand:** Actions → „Deploy“ → Run workflow → Instanz `saar-hochwald`.
  - Ein Push deployt nichts (Actions-Minuten sparen).
  - Vorher prüfen, ob für dieselbe Instanz schon ein Deploy läuft.
- **Zuerst „Nur prüfen“:** baut, testet, meldet sich per SSH an und ändert nichts. Es zeigt:
  - PHP und Erweiterungen, Speicher;
  - die Datenbankverbindung;
  - ob und welche Kluft-Instanz im Zielordner liegt, offene Migrationen;
  - die letzten Fehler aus `storage/logs/laravel.log`.

  Fehlt der Zielordner, listet es die vorhandenen Ordner.
- **Schutzregeln:** Der Deploy bricht ab,
  - wenn im Zielordner eine andere Instanz läuft;
  - wenn der Ordner bei der Erstinstallation nicht leer ist;
  - wenn auf dem Server MariaDB läuft, im Environment aber die DB-Secrets fehlen.
- **Runner:** `github` (Standard, kostet Actions-Minuten) oder `nas` (eigener Runner, `docs/runner.md`).
- **Tests im Deploy:** Ist „Qualität und Sicherheit“ für denselben Commit grün, entfallen die SQLite-Tests. Gegen MariaDB wird immer getestet.
- **Was hochgeladen wird:** nur der eigene Instanz-Ordner unter `instanzen/`. `docs/` und `runner/` werden nicht hochgeladen.

## GitHub-Environment „saar-hochwald“

Liste mit Erklärung: `docs/deploy.md` und `docs/environment-vorlage/`. Werte
nur mit `scripts/environment-anlegen.sh` oder direkt in GitHub eintragen, nie
ins Repository oder in den Chat.

- **Pflicht:**
  - `APP_URL` (z. B. `https://dpsg-saar-hochwald.de` bzw. mit `www.`, je nachdem, worauf die Domain zeigt);
  - `MAIL_FROM_ADDRESS`;
  - `SSH_HOST`, `SSH_PORT`, `SSH_USERNAME`, `SSH_PRIVATE_KEY`, `SSH_TARGET_DIR`;
  - `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.
- **Variable oder Secret:** `APP_URL`, `APP_ENV`, `APP_NAME`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `PHP_BIN` gehen als Variable oder Secret. Ausnahme: `MONITOR_URL` nur als **Variable**.
- **Mail:** `BREVO_SMTP_LOGIN`, `BREVO_SMTP_KEY`. Für den Newsletter zusätzlich `BREVO_API_KEY`, `BREVO_NEWSLETTER_LIST_ID`, `BREVO_DOI_TEMPLATE_ID`.
  - Während der Testphase `MAIL_TEST_REDIRECT_TO` und `MAIL_TEST_ALLOWLIST`.
  - Die Absenderadresse muss in Brevo bestätigt sein.
- **Optional:** `BACKUP_*`, `MITGLIEDER_DRIVER`/`NAMI_*`, `ENTRA_*` (falls der Bezirk Microsoft 365 nutzt), `NTFY_*`, `WHATSAPP_*`.

## Erster Zugang

- **Ohne Vorbelegung:** Der Bezirk hat (anders als Roden) keinen vorbelegten ersten Zugang im Seeder.
- **Weg 1, Notfall-Admin:** nach dem ersten Deploy per SSH `php artisan cms:create-superadmin` im Projektordner. Fragt nach Mail und Passwort, zeigt das TOTP-Geheimnis einmalig. Beides in einen Passwortmanager, nicht in den Chat.
- **Weg 2, Seeder:**
  - einen Zugang für ein Funktionspostfach wie die Bezirksleitung anlegen, mit Anmeldung per E-Mail-Code (Muster: `StammStartSeeder::ersterZugang()`);
  - nur, solange noch niemand eine Rolle hat;
  - vorher mit dem Nutzer abstimmen.

## Offene Punkte (Stand Übergabe)

1. **Inhalte mit dem Bezirk abstimmen:**
   - Bezirksleitung (Personen, Fotos nur mit Einwilligung);
   - Stämme mit Standorten und Gruppenstunden;
   - Prävention;
   - Impressum und Datenschutz;
   - Startseite.
2. **Server:** Datenbank anlegen, Zielordner klären (alte Website sichern), GitHub-Environment füllen.
3. **Deploy:** erst „Nur prüfen“, dann echt. Danach `MONITOR_URL` als Variable setzen.
4. **Cronjob** in Plesk einrichten.
5. **Erster Zugang** (siehe oben) und Rollen für die Redaktion.
6. **Brevo:** Absender bestätigen, Newsletter-Liste und Double-Opt-in-Vorlage.
7. **Weiterleitungen** der alten Adressen prüfen (im `SaarHochwaldSeeder`).

## Aufteilung zwischen den Sitzungen

Drei Claude-Code-Sitzungen arbeiten auf `claude/kluft-instanzen`:

| Bezirks-Sitzung (diese) | Stammes-Sitzung | Haupt-Sitzung |
|---|---|---|
| Inhalte, Konfiguration und Deploys von `saar-hochwald` (`instanzen/saar-hochwald/**`, `SaarHochwaldTest`) | Roden und alles nur für Stämme (`docs/roden.md`) | DV Trier (`dv-trier`) und der gemeinsame Kern: Deploy-Workflow, Monitoring, Backups, Rollen, Anmeldung, NaMi-Anbindung, Funktionen für alle Ebenen |

Regeln dafür:

- **Nur im eigenen Ordner:** Die Bezirks-Sitzung ändert nur `instanzen/saar-hochwald/**`, `tests/Feature/Instanzen/SaarHochwaldTest.php` und diese Datei.
  - Fehlt etwas im gemeinsamen Code, z. B. eine Funktion nur für Bezirke: in `docs/abstimmung.md` beschreiben (oder direkt melden) und mit der Haupt-Sitzung abstimmen, bevor es gebaut wird.
  - Kleine, eindeutige Korrekturen am Kern sind nach Absprache möglich.
- **Nur für Bezirke schalten:** über `config('dpsg.site.level') === 'Bezirk'` oder einen Funktionsschalter mit `'ebene' => 'Bezirk'` in `App\Support\Features::KATALOG`. DV und Stämme dürfen sich dadurch nicht verändern.
- **Tests:** Wer gemeinsamen Code ändert, testet alle drei Instanzen (`KLUFT_INSTANZ=saar-hochwald`, `dv-trier`, `roden`).
- **Deploys:** nur von Hand und nur für die eigene Instanz. dv-trier deployt die Haupt-Sitzung, Roden die Stammes-Sitzung, jeweils nach Absprache mit dem Nutzer.
- **Actions-Minuten sparen:** Doku-Commits sammeln und selten pushen. Für längere Deploys gern den NAS-Runner nehmen, wenn er läuft.
- **Vor jedem Push `git pull --rebase`.** Nur eigene, noch nicht gepushte Commits umbauen, nie fremde.
- **Direkter Austausch:**
  - Die Haupt-Sitzung (`session_014CFjuVyS73EAT5ak9eGcZt`) kann (Stand 22.10.2026) nicht direkt senden und antwortet über `docs/abstimmung.md`.
  - Die Stammes-Sitzung ist `session_01DnjMEd7816yZMn8RwKe28G`.
  - Wer `send_message` hat, meldet sich direkt und trägt die eigene Sitzungs-ID beim ersten Eintrag in `docs/abstimmung.md` ein.
- **Briefkasten `docs/abstimmung.md`:** Rückweg und Protokoll für alle drei Sitzungen. Zu Beginn jeder Sitzung und nach jedem `git pull` die offenen Einträge lesen und beantworten. Absender und Empfänger heißen „Haupt“, „Stammes“ und „Bezirk“.
- **Neue Migrationen:** Dateinamen mit aktuellem Datum und Uhrzeit, damit sich die Sitzungen nicht überschneiden. Inhalts-Migrationen nur im Instanz-Ordner.

## Arbeitsweise und Regeln

- **Geheimes:** Secrets nur in GitHub-Environments oder der Server-`.env`, nie im Repository, in Commits oder im Chat. Was im Chat stand, gilt als kompromittiert.
  - NaMi- und Instagram-Zugangsdaten nie in den Chat.
  - Diagnose-Ausgaben ohne personenbezogene Daten.
- **Persönliche E-Mail-Adressen** nicht ins Repository. Funktionsadressen wie `bezirksleitung@dpsg-saar-hochwald.de` sind in Ordnung.
- **Personen und Fotos** nur mit Einwilligung (`consent`). Bildrechte und Urheber angeben.
- **Server:** keine TLS-Prüfung abschalten, keinen Proxy umgehen. Die Server-`.env` nicht ins Deploy übernehmen.
- **Andere Repositories:** `son1x/dpsg-dv-trier` und `son1x/dpsg-bezirk-saar-hochwald` bleiben unverändert (nur lesen, z. B. um alte Inhalte zu übernehmen), `son1x/dpsg-wittlich` nur lesen.
- **Tests lokal:**
  - `composer install`, dann `KLUFT_INSTANZ=saar-hochwald php artisan test`;
  - nach Änderungen am gemeinsamen Code zusätzlich `dv-trier` und `roden`;
  - Stil mit `vendor/bin/pint --test`;
  - Larastan läuft nur in der CI („Qualität und Sicherheit“).
- **Lokal ansehen:** `KLUFT_INSTANZ=saar-hochwald php artisan kluft:assets`, dann `php artisan migrate:fresh --seed`.
- **Commits:** deutsch, kurz und sachlich wie im übrigen Verlauf. `CHANGELOG.md` aus Sicht der Redaktion ergänzen, wenn sich für Nutzende etwas ändert.
