# Deploy einer Kluft-Instanz

Ein Workflow für alle Gliederungen: **Actions → „Deploy“ → „Run workflow“**, Instanz wählen. Der Job läuft im gleichnamigen **GitHub-Environment** (Settings → Environments) und nimmt dessen Secrets und Variablen.

GitHub baut und testet (Tests der Instanz, Frontend, `vendor/`, Bilder der Instanz, Kartendaten) und lädt das Ergebnis per SFTP hoch. Auf dem Server laufen per SSH nur Entpacken, `migrate`, beim ersten Mal `db:seed`, und das Aufräumen der Caches. Auf dem Server sind kein `composer` und kein `npm` nötig. Das funktioniert auf einem Webspace (z. B. Netcup/Plesk) genauso wie auf einem vServer.

**„Nur prüfen“** baut und testet, meldet sich per SSH an und prüft PHP-Version, Erweiterungen, Zielordner und Speicher. Es lädt nichts hoch und ändert nichts. Vor dem ersten echten Deploy einer Instanz immer zuerst so starten.

## Environment anlegen

Settings → Environments → **New environment**. Der Name ist der Schlüssel der Instanz: `dv-trier` bzw. `saar-hochwald`.

Für einen weiteren Schlüssel muss der Name zusätzlich in den Auswahllisten der Workflows `deploy.yml`, `server-check.yml` und `wp-import.yml` sowie in den Matrix-Listen von `monitoring.yml`, `web-checks.yml` und `quality.yml` ergänzt werden.

### Variablen (Environment variables)

| Name | Pflicht | Beispiel / Bedeutung |
|---|---|---|
| `APP_URL` | ja | `https://test.dpsg-trier.de` |
| `MAIL_FROM_ADDRESS` | ja | `events@dpsg-trier.de` |
| `MAIL_FROM_NAME` | nein | Standard: Name der Instanz aus `config.php` |
| `APP_NAME` | nein | Standard: Name der Instanz |
| `APP_ENV` | nein | Standard `local` (wie bisher beim DV) |
| `PHP_BIN` | nein | Standard `/usr/bin/php`; auf Plesk z. B. `/opt/plesk/php/8.3/bin/php` |
| `MONITOR_URL` | nein | Adresse für Monitoring und Web-Checks; ohne sie werden beide übersprungen |

### Secrets (Environment secrets)

| Gruppe | Namen | Hinweis |
|---|---|---|
| Server | `SSH_HOST`, `SSH_PORT`, `SSH_USERNAME`, `SSH_PRIVATE_KEY`, `SSH_TARGET_DIR` | `SSH_TARGET_DIR` = absoluter Pfad des Projekts (DocumentRoot zeigt auf `<pfad>/public`) |
| Upload | `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`, `FTP_SERVER_DIR` | Webspace mit SFTP-Passwort. **Ohne `FTP_PASSWORD`** (vServer) wird per SFTP mit dem SSH-Schlüssel hochgeladen; Host, Benutzer und Ziel kommen dann aus `SSH_*`. |
| Datenbank | `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | MariaDB (utf8mb4); ohne sie SQLite |
| Anmeldung | `ENTRA_CLIENT_ID`, `ENTRA_CLIENT_SECRET`, `ENTRA_TENANT_ID` | Microsoft 365 (optional) |
| Mail | `BREVO_SMTP_LOGIN`, `BREVO_SMTP_KEY`, `MAIL_TEST_REDIRECT_TO`, `MAIL_TEST_ALLOWLIST` | Echte Mails nur mit SMTP-Zugang **und** Test-Umleitung |
| Newsletter | `BREVO_API_KEY`, `BREVO_NEWSLETTER_LIST_ID`, `BREVO_DOI_TEMPLATE_ID` | optional |
| Backup | `BACKUP_PASSWORD`, `BACKUP_WEBDAV_URL`, `BACKUP_WEBDAV_USER`, `BACKUP_WEBDAV_PASSWORD` | Passwort zusätzlich offline aufbewahren |
| Push | `NTFY_SERVER`, `NTFY_TOKEN` | optional |
| WhatsApp | `WHATSAPP_MODE`, `WHATSAPP_CHANNEL_URL`, `WHATSAPP_TEST_ALLOWLIST` | Standard aus |
| Kalender | `NEXTCLOUD_ALLOWED_HOSTS` | optional |
| Mitglieder | `MITGLIEDER_DRIVER`, `NAMI_USER`, `NAMI_PASSWORD`, `HITOBITO_URL`, `HITOBITO_TOKEN`, `HITOBITO_MEMBER_NUMBER_ATTRIBUTE` | Standard aus, siehe docs/mitgliederabgleich.md |

Secrets nie ins Repository oder in einen Chat. Was dort einmal stand, gilt als kompromittiert.

## Server vorbereiten

**Webspace (Netcup/Plesk):**
- Domain mit DocumentRoot `<pfad>/public` anlegen.
- SSH-Schlüssel im Panel hinterlegen.
- MariaDB-Datenbank anlegen.
- Cronjob `<PHP_BIN> <pfad>/artisan schedule:run` jede Minute.

**vServer (Apache 2.4, php8.3-fpm, MariaDB):**
1. Deploy-Benutzer mit SSH-Schlüssel und Schreibrecht nur auf das Projektverzeichnis. Den öffentlichen Schlüssel in `~/.ssh/authorized_keys`, den privaten als Secret `SSH_PRIVATE_KEY`.
2. **php-fpm-Pool unter diesem Benutzer**, damit Webserver und Deploy dieselben Dateirechte haben (`storage/`, `bootstrap/cache/`, `public/uploads/`).
3. Apache-VirtualHost:
   - `DocumentRoot <pfad>/public`
   - `<Directory <pfad>/public> AllowOverride All </Directory>`
   - PHP über `proxy_fcgi` an den Pool
   - HTTPS per Let's Encrypt
4. PHP-Erweiterungen: intl, mbstring, gd, zip, xml, curl, pdo_mysql, sodium.
5. MariaDB: eigene Datenbank und eigener Benutzer.
6. Cronjob des Deploy-Benutzers: `* * * * * cd <pfad> && php artisan schedule:run >> /dev/null 2>&1`.
7. Ein bisheriges eigenes Deploy-Skript erst abschalten, wenn der Workflow einmal erfolgreich durchgelaufen ist.

## Erster Deploy einer neuen Instanz

1. Environment mit Variablen und Secrets anlegen.
2. Deploy mit **„Nur prüfen“**: alles grün?
3. Deploy ohne „Nur prüfen“: legt die Datenbank an, seedet den Startinhalt (`config dpsg.seeders.start`) und schreibt `KLUFT_INSTANZ` in die `.env`.
4. Notfall-Admin anlegen: per SSH `php artisan cms:create-superadmin`. Danach unter `/notfall-login` anmelden und die weiteren Konten vergeben.
5. `MONITOR_URL` setzen; Monitoring und Web-Checks laufen ab dann mit.

## Umstellung des DV Trier (einmalig)

Bis zur Umstellung deployt weiter das alte Repo son1x/dpsg-dv-trier, das unverändert als Archiv bleibt.

1. In son1x/kluft das Environment `dv-trier` mit denselben Secrets wie im alten Repo anlegen. Variablen setzen: `APP_URL` = `https://test.dpsg-trier.de`, `MAIL_FROM_ADDRESS` = `events@dpsg-trier.de`, `PHP_BIN` wie bisher (`/usr/bin/php`), `MONITOR_URL`.
2. Deploy `dv-trier` mit **„Nur prüfen“**. Die Ausgabe zeigt die installierte Version, dass `KLUFT_INSTANZ` noch fehlt, und „Datenbank: jetzt mariadb, nach dem Deploy mariadb“. Fehlen die `DB_*`-Secrets, bricht die Prüfung (und jeder echte Deploy) ab, statt die Seite auf SQLite umzustellen.
3. Deploy `dv-trier` ohne „Nur prüfen“. Ab jetzt läuft der DV mit dem Kluft-Code.
   - Die Migrationen melden nur die neue Migration des Mitgliederabgleichs (zusätzliche Spalten, Funktion bleibt aus).
   - Inhalte und Seiten bleiben, wie sie sind.
4. Danach den automatischen Deploy bei Push für `dv-trier` einschalten (eigener Commit).
5. **Rückweg:** Im alten Repo den Workflow „Deploy auf Netcup-Webspace“ von Hand starten. Er spielt den bisherigen Code wieder auf, die Datenbank bleibt kompatibel. Die zusätzlichen Spalten stören den alten Code nicht.
