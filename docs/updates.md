# Updates für eigenständige Installationen

Stämme (und andere Gliederungen ohne GitHub-Deploy) betreiben eine eigene
Kluft-Installation auf ihrem Webspace. Damit diese Installationen nicht
veralten, holt sich Kluft neue Versionen selbst – signiert, mit Backup
vorher und Rücknahme, wenn etwas schiefgeht.

DV Trier und Bezirk Saar-Hochwald werden weiter per GitHub-Deploy
aktualisiert (`KLUFT_UPDATES=off`, Standard).

## Für die Installation (Stamm)

In der `.env`:

| Variable | Bedeutung |
|---|---|
| `KLUFT_UPDATES` | `off` (Standard), `manual` (anzeigen, Installation per Knopf) oder `auto` (zusätzlich nachts automatisch) |
| `KLUFT_UPDATE_URL` | Adresse der `manifest.json` auf dem Update-Server, z. B. `https://updates.dpsg-trier.de/kluft/manifest.json` |
| `KLUFT_UPDATE_PUBLIC_KEY` | öffentlicher Signaturschlüssel (base64), siehe unten |

Voraussetzungen: Cronjob `php artisan schedule:run` jede Minute, PHP-Erweiterungen
`zip` und `sodium`, Backup eingerichtet (`BACKUP_PASSWORD`, siehe Backups).

Im Backend unter **Verwaltung → Updates** (nur Admins): installierte Version,
verfügbare Version mit Änderungen, Knopf „Aktualisieren“ und das Protokoll des
letzten Laufs. Admins bekommen eine Mail, wenn eine neue Version da ist, und
nach jedem Update eine mit dem Ergebnis.

## Was bei einem Update passiert

`php artisan kluft:update` läuft im Cronjob – nie im Web-Request:

1. `manifest.json` und `manifest.json.sig` holen und die **Ed25519-Signatur**
   prüfen. Ohne gültige Signatur gibt es kein Update, auch keine Anzeige.
2. Paket herunterladen, **SHA-256-Prüfsumme** aus dem signierten Manifest vergleichen,
   entpacken (keine Pfade außerhalb des Pakets), Version prüfen.
3. **Backup** von Datenbank und Dateien (`BackupService`). Klappt es nicht, wird abgebrochen.
4. **Wartungsmodus** (`artisan down`).
5. **Tausch** der Programmdateien durch `resources/update/austausch.php` – ein eigenes
   Skript ohne Laravel, damit das laufende Programm nichts aus dem halb getauschten
   Stand nachlädt. Je Eintrag der obersten Ebene ein `rename()`, in `database/`,
   `public/` und `instanzen/` eine Ebene tiefer. Nie angefasst: `.env`, `storage/`,
   `database/database.sqlite`, `public/uploads/`, `public/instanz/` und eigene
   Instanz-Ordner. Die alten Dateien liegen unter `storage/app/private/updates/rueck-<version>/`.
6. Im **neuen** Programm: `migrate --force`, `kluft:assets`, `optimize:clear`.
   Schlägt davon etwas fehl, werden die alten Dateien zurückgetauscht und die Website
   läuft mit der alten Version weiter. War die Datenbank schon teilweise umgestellt,
   steht der Hinweis auf das Backup (`php artisan backup:restore`) in der Mail.
7. Wartungsmodus aus, Protokoll und Mail (im neuen Programm: `kluft:update --abschluss`).

Befehle von Hand (per SSH):

```
php artisan kluft:update                 # prüfen
php artisan kluft:update --installieren  # neueste Version jetzt installieren
```

Zurück zur vorigen Version, solange die Rücksicherung noch da ist:

```
php artisan down
php storage/app/private/updates/austausch.php zurueck "$PWD" storage/app/private/updates/rueck-<alte-version>
php artisan up
```

## Ein Release veröffentlichen

1. `VERSION` erhöhen (z. B. `0.9.1`) und in `CHANGELOG.md` einen Abschnitt `## 0.9.1`
   mit Spiegelstrichen schreiben – sie erscheinen im Backend und in der Mail.
2. Committen und pushen, dann **Actions → „Release“ → „Run workflow“**
   (oder Tag `v0.9.1` pushen). Häkchen „automatisch“ ausschalten, wenn eine Version
   nur von Hand installiert werden soll (z. B. bei großen Umbauten).
3. Der Workflow testet, baut `vendor/` und die Assets, packt das Programm ohne Daten
   und Instanzen, schreibt und signiert `manifest.json` und legt alles als
   GitHub-Release ab. Mit den Secrets `UPDATE_SFTP_*` lädt er es zusätzlich auf den
   öffentlichen Update-Server – Paket zuerst, Beschreibung zuletzt.

Das Repository ist privat: Installationen können GitHub-Releases nicht abrufen.
Für Stämme braucht es daher einen öffentlichen Ablageort, z. B. eine Subdomain
`updates.dpsg-trier.de` auf dem DV-Webspace (eigener Ordner, nicht der der Website).

## Signaturschlüssel (einmalig)

Lokal ausführen, nicht auf einem Server und nicht im Chat:

```
php artisan kluft:update-schluessel
```

- **Geheimer Schlüssel** → nur in das Repository-Secret `KLUFT_RELEASE_SIGNING_KEY`.
- **Öffentlicher Schlüssel** → `KLUFT_UPDATE_PUBLIC_KEY` jeder Installation.

Ohne PHP geht es auch mit OpenSSL; der öffentliche Schlüssel ist dann aber im
DER-Format und muss umgewandelt werden – einfacher ist der Artisan-Befehl.

Gerät der geheime Schlüssel in falsche Hände, kann jemand Updates für alle
Installationen signieren: dann neues Schlüsselpaar erzeugen, Secret ersetzen und
`KLUFT_UPDATE_PUBLIC_KEY` in allen Installationen tauschen.
