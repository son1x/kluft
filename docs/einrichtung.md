# Neue Stammes-Website einrichten

Jeder Stamm bekommt eine eigene Kluft-Installation auf eigenem Webspace
(Entscheidung vom 9.10.2026, Roadmap → Meilenstein Stämme). Dafür braucht es
weder GitHub noch Programmierkenntnisse: Paket hochladen, Adresse aufrufen,
Formular ausfüllen.

## Was der Stamm braucht

- Webspace mit **PHP 8.3** oder neuer (Erweiterungen pdo_sqlite, mbstring,
  openssl, sodium, zip, fileinfo, gd, intl – bei üblichen Hostern Standard)
- eine **Domain** mit SSL-Zertifikat (Let’s Encrypt im Hosting-Panel)
- ein **E-Mail-Postfach** mit SMTP-Zugang (für Anmeldecodes und Bestätigungen)
- einen **Cronjob**, der jede Minute läuft (fast alle Hoster bieten das an)
- optional eine MariaDB/MySQL-Datenbank; ohne sie nimmt Kluft eine Datei (SQLite)

## Schritte

1. **Paket holen:** neueste `kluft-x.y.z.zip` vom Update-Server
   (z. B. `https://updates.dpsg-trier.de/kluft/`) bzw. aus dem GitHub-Release.
2. **Hochladen und entpacken** in einen Ordner auf dem Webspace, z. B. `kluft/`.
3. **Dokumentenstamm** der Domain im Hosting-Panel auf den Unterordner
   `public/` stellen (z. B. `kluft/public`). Nur dieser Ordner ist von außen erreichbar.
4. **Domain aufrufen.** Ohne `.env` leitet Kluft auf `/einrichtung.php` weiter.
5. **Einrichtungscode** per FTP oder Dateimanager aus
   `storage/app/private/einrichtungscode.txt` lesen und eintragen. So kann
   niemand Fremdes eine frisch hochgeladene Website übernehmen.
6. **Formular ausfüllen:** Name des Stamms, Ort, DPSG-Nummer (für den
   NaMi-Abgleich), Stufen, Logo, Kontakt und Angaben fürs Impressum, Datenbank,
   E-Mail-Versand und der erste Zugang zur Redaktion – am besten als
   Rollen-Zugang mit dem Funktionspostfach des Vorstands.
7. **Backup-Passwort** von der Abschlussseite im Passwortmanager des Stamms
   speichern. Es wird nur einmal angezeigt.
8. **Cronjob** anlegen: `php <pfad>/artisan schedule:run` jede Minute.
9. Unter `/redaktion/anmelden` mit der Adresse des ersten Zugangs anmelden.

## Was der Assistent anlegt

- `instanzen/stamm-<name>/angaben.json` mit den Angaben und `config.php`, die
  daraus die Konfiguration baut (`App\Support\Einrichtung\StammVorlage`).
  Ohne Stämme-Verzeichnis und Karte; Anmeldung per E-Mail-Code an.
- Logo unter `instanzen/stamm-<name>/public/images/` (ohne Upload ein
  schlichtes Schriftlogo)
- Datenbank mit Startinhalt (`Database\Seeders\Einrichtung\StammStartSeeder`):
  Startseite, „Unsere Gruppen“ mit einer Seite je Stufe und Platz für die
  Gruppenstunde, Stammesvorstand, Kontakt mit Themen (u. a. „Schnupperstunde /
  Mitmachen“), Impressum und Datenschutz als Vorlage mit markierten Lücken, den
  Stamm als Gruppierung (für „Nur für Mitglieder“)
- ersten Zugang mit Admin-Rechten
- `.env` mit zufälligem Schlüssel, Backup-Passwort und – wenn das Release
  sie mitbringt – Update-Adresse und Signaturschlüssel (`KLUFT_UPDATES=manual`)

Erst ganz zum Schluss wird die `.env` geschrieben. Bricht vorher etwas ab
(z. B. Datenbank nicht erreichbar), lässt sich der Assistent einfach noch
einmal aufrufen. Mit `.env` ist er gesperrt und zeigt nur noch „bereits
eingerichtet“. Eine MariaDB-Datenbank muss leer sein; bestehende Daten werden
nie überschrieben.

## Danach im Redaktionsbereich

- Impressum und Datenschutz prüfen und die markierten Stellen ergänzen
- Gruppenstunden bei den Stufen eintragen
- Titelbild der Startseite wählen (Seite „start“)
- weitere Zugänge anlegen (Benutzer → Person oder Rollen-Zugang)

## Vor jedem Release prüfen

Der Assistent läuft ohne Laravel-Testumgebung; die Bausteine prüft
`tests/Feature/Instanzen/EinrichtungTest.php`. Den ganzen Ablauf vor einem
Release einmal in einer frischen Kopie durchspielen: Repository ohne
`instanzen/`, `storage/` und `.env` kopieren, mit `php -S` ausliefern,
`/` aufrufen, Formular ausfüllen, danach Startseite, Stufenseite und
`/redaktion/anmelden` ansehen.
