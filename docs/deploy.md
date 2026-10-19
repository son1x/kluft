# Deploy einer Kluft-Instanz

Ein Workflow für alle Gliederungen: **Actions → „Deploy“ → „Run workflow“**, Instanz wählen. Der Job läuft im gleichnamigen **GitHub-Environment** (Settings → Environments) und nimmt dessen Secrets und Variablen.

GitHub baut und testet (Tests der Instanz, Frontend, `vendor/`, Bilder der Instanz, Kartendaten) und lädt das Ergebnis per SFTP hoch. Auf dem Server laufen per SSH nur Entpacken, `migrate`, beim ersten Mal `db:seed`, und das Aufräumen der Caches. Auf dem Server sind kein `composer` und kein `npm` nötig. Das funktioniert auf einem Webspace (z. B. Netcup/Plesk) genauso wie auf einem vServer.

**„Nur prüfen“** baut und testet, meldet sich per SSH an und prüft PHP-Version, Erweiterungen, Zielordner und Speicher, dazu die Datenbankverbindung (Version, Tabellenzahl), die installierte Instanz, offene Migrationen und die letzten Fehler aus `storage/logs/laravel.log`. Fehlt der Zielordner, zeigt es, welche Ordner es gibt. Es lädt nichts hoch und ändert nichts. Vor dem ersten echten Deploy einer Instanz immer zuerst so starten; bei einem 500er auf dem Server ist es der schnellste Blick ins Log.

**Runner:** `github` (Standard, kostet Actions-Minuten) oder `nas` (eigener Runner, [runner.md](runner.md)).

**Schutzregeln** (auch beim echten Deploy): Läuft im Zielordner eine andere Instanz, bricht der Deploy ab. Bei einer Erstinstallation muss der Ordner leer sein (bis auf `.well-known` und `cgi-bin`), sonst würde der Abgleich eine vorhandene Website löschen. Hochgeladen wird nur der Ordner der eigenen Instanz unter `instanzen/`.

## Environment anlegen

Settings → Environments → **New environment**. Der Name ist der Schlüssel der Instanz: `dv-trier`, `saar-hochwald` bzw. `roden`.

Für einen weiteren Schlüssel muss der Name zusätzlich in den Auswahllisten der Workflows `deploy.yml` und `server-check.yml` (bei Bedarf `wp-import.yml`) sowie in den Matrix-Listen von `monitoring.yml`, `web-checks.yml` und `quality.yml` ergänzt werden.

### Bequemer: per Kommandozeile

Statt jedes Secret einzeln auf github.com einzutippen, legt `scripts/environment-anlegen.sh` das Environment an und setzt alle Werte aus zwei Dateien – mit der GitHub-CLI (`gh auth login`, Admin-Recht am Repository):

1. Ordner **außerhalb** des Repositories anlegen, z. B. `~/kluft-geheim/roden`, und die Vorlagen aus `docs/environment-vorlage/` hineinkopieren.
2. `variablen.env` und `secrets.env` ausfüllen; den privaten Deploy-Schlüssel als `ssh_key` daneben legen. Zeilen ohne Wert werden übersprungen.
3. `scripts/environment-anlegen.sh roden ~/kluft-geheim/roden`

Das Skript zeigt am Ende die Variablen und die Namen der Secrets. Ein zweiter Aufruf überschreibt nur, was in den Dateien steht. Den Ordner danach sicher aufbewahren oder löschen; die Werte lassen sich aus GitHub nicht zurücklesen. Einzelne Werte gehen auch direkt: `gh secret set DB_PASSWORD --env roden` (fragt den Wert verdeckt ab) bzw. `gh variable set APP_URL --env roden --body https://www.dpsg-roden.de`.

### Variablen (Environment variables)

Diese Werte dürfen auch als Secret eingetragen sein; der Deploy nimmt die Variable, sonst das Secret. Ausnahme: `MONITOR_URL` liest das Monitoring nur als Variable.

| Name | Pflicht | Beispiel / Bedeutung |
|---|---|---|
| `APP_URL` | ja | `https://test.dpsg-trier.de` |
| `MAIL_FROM_ADDRESS` | ja | `events@dpsg-trier.de` |
| `MAIL_FROM_NAME` | nein | Standard: Name der Instanz aus `config.php` |
| `APP_NAME` | nein | Standard: Name der Instanz |
| `APP_ENV` | nein | Standard `local` (wie bisher beim DV) |
| `PHP_BIN` | nein | Standard `/usr/bin/php`; auf Plesk z. B. `/opt/plesk/php/8.3/bin/php` |
| `MONITOR_URL` | nein | Adresse für Monitoring (alle 6 Stunden) und Web-Checks (montags); ohne sie werden beide übersprungen |

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
| Mitglieder | `MITGLIEDER_DRIVER`, `NAMI_AUTH`, `NAMI_USER`, `NAMI_PASSWORD`, `HITOBITO_URL`, `HITOBITO_TOKEN`, `HITOBITO_MEMBER_NUMBER_ATTRIBUTE` | Standard aus; für NaMi 2 mit persönlicher Anmeldung genügt `MITGLIEDER_DRIVER=nami`, siehe docs/mitgliederabgleich.md |

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

## Stammes-Instanz im Repository (Beispiel Roden)

Statt mit dem Einrichtungsassistenten auf eigenem Webspace kann eine Stammes-Website auch wie der DV über GitHub deployt werden, z. B. auf dem DV-Server in einem eigenen Verzeichnis:

- `instanzen/roden/config.php` (baut die Konfiguration mit `StammVorlage`), `angaben.json` (Angaben wie aus dem Assistenten, mit `ausbau` und fertigen `seiten`), `public/images/logo.png`. Keine Passwörter oder persönlichen Adressen.
- Auf dem Server: eigenes Verzeichnis (z. B. `/var/www/dpsg-roden`), VirtualHost für `www.dpsg-roden.de` mit DocumentRoot `<pfad>/public`, eigener php-fpm-Pool oder Deploy-Benutzer, eigene MariaDB-Datenbank, Cronjob für `<pfad>/artisan schedule:run`, Let's Encrypt. Nur `www` bzw. die Hauptdomain umziehen – Subdomains wie `webmail.` oder `cloud.` bleiben, wo sie sind.
- Mail: Die Absenderadresse (`MAIL_FROM_ADDRESS`, z. B. `website@dpsg-roden.de`) muss in Brevo als Absender bzw. Domain bestätigt sein (DNS-Einträge beim Domain-Anbieter). Während der Testphase das Postfach des ersten Zugangs in `MAIL_TEST_ALLOWLIST` aufnehmen, sonst kommt der Anmeldecode nicht an.
- Startinhalt beim ersten Deploy: `StammStartSeeder` legt Seiten, Stufen mit Gruppenstunden, Kontaktthemen und die vorbereiteten Seiten aus `angaben.json` an.
- Weitere Angaben in `angaben.json`: `alter` (je Stufe `from`, `to` und optional `text` für die Website, z. B. „3. bis 5. Klasse“) und `leitende` (je Stufe die Namen des Leitungsteams, ohne Adressen). Beides legt der erste Deploy an; später gepflegt unter Verwaltung → Stufen bzw. Personen.
- Erster Zugang: `zugang` in `angaben.json` (Roden: Rollen-Zugang „Stammesvorstand“ mit `kontakt@dpsg-roden.de`). Der Seeder legt ihn beim ersten Deploy als Admin mit Anmeldung per E-Mail-Code an, solange noch niemand eine Rolle hat. Anmelden unter `/redaktion/anmelden`; die Adresse muss in `MAIL_TEST_ALLOWLIST` stehen, solange `MAIL_TEST_REDIRECT_TO` gesetzt ist.
- Notfall: per SSH `php artisan cms:create-superadmin` (Notfall-Login mit Passwort und TOTP), falls der erste Zugang nicht klappt.
- Microsoft 365 (wenn der Stamm einen eigenen Mandanten hat, wie Roden): siehe nächster Abschnitt.

### Anmeldung mit Microsoft 365 für eine Stammes-Instanz

Kluft meldet mit Microsoft 365 an, sobald `ENTRA_TENANT_ID`, `ENTRA_CLIENT_ID` und `ENTRA_CLIENT_SECRET` im Environment stehen (Redirect-URI `<APP_URL>/auth/entra/callback` setzt der Deploy selbst). Die App-Registrierung im Mandanten des Stamms legt ein Admin dieses Mandanten mit der Azure-CLI an:

```
az login --tenant dpsg-roden.de --allow-no-subscriptions
scripts/entra-app-anlegen.sh roden ~/kluft-geheim/roden https://www.dpsg-roden.de
scripts/environment-anlegen.sh roden ~/kluft-geheim/roden
```

- Single-Tenant, nur delegierte Rechte: `openid`, `profile`, `email`, `User.Read`, `User.ReadBasic.All` (Suche nach Personen beim Anlegen von Zugängen), mit Administratorzustimmung. Weitere Adressen (z. B. eine Testadresse) einfach hinten anhängen.
- Das Client-Secret schreibt das Skript direkt in `secrets.env`, es erscheint nicht auf dem Bildschirm. Es gilt zwei Jahre; danach das Skript erneut ausführen und das Environment aktualisieren.
- Mit `KLUFT_ZUWEISUNG=1` gilt „Zuweisung erforderlich“: Dann können sich nur freigegebene Personen oder Gruppen anmelden. Ohne landet jede Person des Mandanten bei der ersten Anmeldung auf „wartet auf Freischaltung“, bis ihr im Backend eine Rolle gegeben wird; die Admins bekommen eine Mail.
- Rechte vergibt weiterhin Kluft (Benutzer → Rolle), nicht Microsoft. Vorab angelegte Zugänge werden bei der ersten Anmeldung über die E-Mail-Adresse verknüpft – auch der erste Zugang aus `angaben.json`, wenn es zu dieser Adresse ein Microsoft-365-Konto gibt.
- Die Anmeldeseite zeigt dann zusätzlich „Mit Microsoft 365 anmelden“. Wer nur noch Microsoft 365 will: unter Verwaltung → Funktionen „Anmeldung per E-Mail-Code“ ausschalten; dann führt `/admin` direkt zu Microsoft.

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
4. Danach den automatischen Deploy bei Push für `dv-trier` einschalten (eigener Commit). **Erledigt am 5. Oktober 2026:** Jeder Push auf `claude/kluft-instanzen` deployt den DV; reine Doku-Änderungen und Dateien von Saar-Hochwald lösen keinen Deploy aus. **Seit 19. Oktober 2026 wieder abgeschaltet** (Actions-Minuten): Alle Instanzen, auch der DV, werden nur noch von Hand deployt.
5. **Rückweg:** Im alten Repo den Workflow „Deploy auf Netcup-Webspace“ von Hand starten. Er spielt den bisherigen Code wieder auf, die Datenbank bleibt kompatibel. Die zusätzlichen Spalten stören den alten Code nicht.
