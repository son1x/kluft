# Livegang www.dpsg-trier.de

**Termin:** Samstag, 21. November 2026 (Umschalten am 21., spätestens Totensonntag 22.11.). Meilenstein „Livegang“ in der Roadmap (`/roadmap`).

## Entscheidungen

- **26.10.2026:**
  - Die Live-Website startet mit allen Inhalten und Einstellungen aus test.dpsg-trier.de.
  - test.dpsg-trier.de bleibt als Testsystem, deutlich gekennzeichnet bzw. geschlossen.
  - Nach dem Livegang werden Inhalte nur noch auf Live gepflegt.
- **06.11.2026, Weg B:** **Kopie mit Plesk** in einen neuen Ordner mit eigener Datenbank, dazu ein eigenes GitHub-Environment `dv-trier-live` mit neuem Deploy-Pfad.
  - Grund: Die Ordner sollen danach einheitlich heißen. Live liegt in `www.dpsg-trier.de`, Test in `test.dpsg-trier.de`, die alte Seite in `alt.dpsg-trier.de`.
  - Kein eigener Kopier-Workflow und kein Update-System.

## Aufbau nachher

| | Test | Live | Alte Seite |
|---|---|---|---|
| Adresse | test.dpsg-trier.de | www.dpsg-trier.de (Probelauf: neu.dpsg-trier.de) | alt.dpsg-trier.de |
| Ordner in Plesk | `test.dpsg-trier.de` (wie bisher) | `www.dpsg-trier.de` (neu) | `alt.dpsg-trier.de` (Kopie von `httpdocs`) |
| Dokumentstamm | `test.dpsg-trier.de/public` | `www.dpsg-trier.de/public` | `alt.dpsg-trier.de` |
| Datenbank | wie bisher | neu, eigener Benutzer | WordPress wie bisher |
| GitHub-Environment | `dv-trier` | `dv-trier-live` | – |
| Mails | an die Testadresse (außer Test-Empfänger*innen) | an die echten Empfänger*innen | – |
| Kennzeichnung | `KLUFT_TESTSYSTEM=geschlossen`: gelbes Band, noindex, nur nach Anmeldung | keine | Passwortschutz oder noindex |

Beide Kluft-Installationen nutzen den Instanz-Ordner `instanzen/dv-trier`. Der Deploy für das Environment `dv-trier-live` setzt `KLUFT_INSTANZ=dv-trier`. Unterschiede kommen nur aus den Secrets und Variablen der beiden Environments.

## Was die Technik dafür gebaut hat (Stand 06.11.)

- **Deploy auf zwei Ziele:** Actions → Deploy → Instanz `dv-trier` (Test) oder `dv-trier-live` (Live). Ebenso die Server-Prüfung.
- **Schalter „Testsystem“** (Variable `KLUFT_TESTSYSTEM` im Environment):
  - leer: aus.
  - `offen`: gelbes Band „Testsystem“ auf jeder Seite und im Backend, Suchmaschinen ausgesperrt (Meta-Tag und Header).
  - `geschlossen`: zusätzlich nur nach Anmeldung. Anmeldung, Backend und Systemstatus bleiben erreichbar.
  - `KLUFT_LIVE_URL` (z. B. `https://www.dpsg-trier.de`) ist das Ziel des Links im Band.

## Zeitplan

| Bis | Was | Wer |
|---|---|---|
| 13.11. | Ordner, Datenbank, Subdomain neu.dpsg-trier.de (Roadmap „Livegang 1“) | Projektleitung |
| 13.11. | Environment `dv-trier-live` und Entra-Adressen (Roadmap „Livegang 2–3“) | Projektleitung |
| 15.11. | Inhalte fertig, Datenschutzerklärung (Roadmap „Livegang 4“) | Redaktion |
| ~18.11. | **Probelauf:** Kopie nach neu.dpsg-trier.de, Deploy, Prüfen (unten) | beide |
| 21.11. | **Umschalten** (unten) | beide |

## Vorbereitung (einmalig)

1. **Ordner und Datenbank in Plesk** (Abo dpsg-trier.de):
   - Subdomain `neu.dpsg-trier.de` mit dem Ordner `www.dpsg-trier.de` anlegen, Dokumentstamm `www.dpsg-trier.de/public`, Let’s Encrypt. PHP-Version und -Einstellungen wie bei Test.
   - Neue MariaDB-Datenbank (utf8mb4) mit eigenem Benutzer, z. B. `dpsg_live`.
2. **Environment `dv-trier-live`:**
   - Am einfachsten mit `scripts/environment-anlegen.sh dv-trier-live <ordner>` (Vorlagen in `docs/environment-vorlage/`), sonst auf github.com unter Settings → Environments.
   - Die Werte sind dieselben wie bei `dv-trier`, mit folgenden Ausnahmen:

   | Name | Live |
   |---|---|
   | `SSH_TARGET_DIR` | absoluter Pfad des Ordners `www.dpsg-trier.de` |
   | `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | die neue Datenbank |
   | `APP_URL` (Variable) | Probelauf `https://neu.dpsg-trier.de`, am Livetag `https://www.dpsg-trier.de` |
   | `MAIL_TEST_REDIRECT_TO`, `MAIL_TEST_ALLOWLIST` | Probelauf wie bei Test (keine echten Mails), am Livetag **löschen** |
   | `KLUFT_TESTSYSTEM` (Variable) | Probelauf `offen`, am Livetag **löschen** |
   | `KLUFT_MAIL_LIVE` (Variable) | Probelauf nicht setzen, am Livetag `ja` (sonst gehen ohne Umleitung keine Mails raus, docs/deploy.md „Mail“) |
   | `BACKUP_WEBDAV_URL` | **eigener** Zielordner, sonst überschreiben sich die Sicherungen von Test und Live |
   | `MONITOR_URL` (Variable) | am Livetag `https://www.dpsg-trier.de` |

   - Secrets nie in Chat oder Mail. Die Werte stehen im Passwortmanager oder in der `.env` von Test auf dem Server.
3. **Entra:** In der App-Registrierung unter Authentifizierung zwei Umleitungs-URIs ergänzen:
   - `https://neu.dpsg-trier.de/auth/entra/callback`
   - `https://www.dpsg-trier.de/auth/entra/callback`

## Kopieren (Probelauf und Livetag gleich)

1. **Datenbank:**
   - Plesk → Datenbanken → Test-Datenbank → Exportieren.
   - Den Dump in die Live-Datenbank importieren. Beim zweiten Mal die Live-Datenbank vorher leeren.
2. **Dateien:** Plesk → test.dpsg-trier.de → „Website kopieren“, oder im Dateimanager den **ganzen** Ordner nach `www.dpsg-trier.de` kopieren. Wichtig sind:
   - `storage/app/private/app.key`, der Schlüssel für verschlüsselte Daten (Umfrage-Antworten, Zuschuss- und NaMi-Daten, Notfall-Login). Ohne ihn sind diese Daten auf Live unlesbar.
   - `public/uploads/` (Mediathek, Dokumente) und `storage/app/`.
   - Die `.env` wird beim Deploy ersetzt und muss nicht angepasst werden.
3. **Sofort danach Deploy `dv-trier-live`.** Bis dahin zeigt die kopierte `.env` noch auf die Test-Datenbank. Deshalb:
   - die Adresse vorher nicht aufrufen,
   - noch keinen Cronjob für den neuen Ordner anlegen.
4. **Cronjob** für den neuen Ordner in Plesk anlegen (wie bei Test: `php artisan schedule:run` jede Minute), erst nach dem Deploy.

**Probelauf (~18.11.) prüfen:**
- Startseite, `/status` und das Backend mit Microsoft-Anmeldung
- Mediathek-Bilder, ein Umfrage-Ergebnis (verschlüsselte Daten lesbar?)
- Kontaktformular (geht an die Testadresse)
- eine alte WordPress-Adresse (Weiterleitung)
- Bolivien, Beraten und Scoutergy
- Backend → Verwaltung → Aufrufe: „Alle Zähler löschen“ ausprobieren, danach steht dort „Noch keine Aufrufe gezählt (0).“

## Anmeldungen vor dem Livegang auf der alten Website

Einzelne Anmeldungen laufen schon vor dem 21.11. über test.dpsg-trier.de, z. B. die Wö- und Biber-Weihnachtspostkartenaktion (Anmeldeschluss 23.11.). Am Livetag wird die Test-Datenbank nach Live kopiert, die Anmeldungen kommen also mit.

- **Schalter:** Veranstaltung bearbeiten → „Vorab extern freigeben“. Bei der Weihnachtsaktion setzt ihn eine Migration. Damit:
  - ist die Veranstaltung auf dem geschlossenen Testsystem ohne Anmeldung erreichbar, ohne gelbes Band, weiter mit noindex. Erreichbar sind die Seite, das Formular, die Links zum Bestätigen und Stornieren, Datenschutz und Impressum;
  - gehen die Mails zu ihren Anmeldungen an die echten Adressen, alle anderen Mails weiter an die Testadresse;
  - lässt sich das Formular unter `/veranstaltungen/<slug>/einbetten` als iframe einbinden. Erlaubt sind nur die Websites in `einbetten_von` (`instanzen/dv-trier/config.php`, jetzt www.dpsg-trier.de und dpsg-trier.de).
- **Voraussetzung:** Deploy `dv-trier` (Test) mit diesem Stand.
- **In WordPress:** einen Block „Individuelles HTML“ einfügen:

  ```html
  <iframe id="kluft-anmeldung" src="https://test.dpsg-trier.de/veranstaltungen/weihnachtspostkartenaktion-2026/einbetten"
          title="Anmeldung Weihnachtspostkartenaktion" style="width:100%;border:0;min-height:1700px"></iframe>
  <script>
  window.addEventListener('message', function (e) {
    if (e.origin === 'https://test.dpsg-trier.de' && e.data && e.data.kluftHoehe) {
      document.getElementById('kluft-anmeldung').style.height = e.data.kluftHoehe + 'px';
    }
  });
  </script>
  ```

  Das Skript passt die Höhe an. Entfernt WordPress es (nur Admins dürfen Skripte), reicht die Mindesthöhe; dann scrollt das Formular im Rahmen.
- **Livetag:** Mit dem Umzug von WordPress nach alt.dpsg-trier.de fällt das iframe weg, ab dann gilt die Anmeldung auf www.dpsg-trier.de.
  - Anmeldungen, die zwischen dem Kopieren und dem Umschalten auf Test eingehen, fehlen auf Live. Deshalb die Seite in WordPress beim Redaktionsstopp abschalten.
  - Bestätigungslinks aus Mails vor dem Livegang zeigen auf test.dpsg-trier.de. Sie funktionieren dort auch im Wartungsmodus (nur Bestätigen und Stornieren, nur bei freigegebenen Veranstaltungen). Wer erst nach dem Kopieren bestätigt, ist nur auf Test bestätigt. Am 22.11. auf Test unter der Veranstaltung nachsehen und solche Anmeldungen auf Live von Hand nachtragen.
  - Danach den Schalter ausschalten und `einbetten_von` leeren.

## Ablauf am Livetag (21.11.)

1. **Redaktionsstopp auf Test** ab einer verabredeten Uhrzeit, an alle Redakteur*innen schicken.
   - Auf Test **Verwaltung → Wartungsmodus** einschalten, mit „Backend nur für Admins“ und einem Text wie „Wir ziehen um, ab etwa 16 Uhr sind wir wieder da.“ Der Schalter liegt in der Datenbank und wandert beim Kopieren mit: Live startet geschlossen, Admins sehen beim Prüfen (Schritt 8) trotzdem alles.
2. **Sichern:** Plesk-Backup von Test und der WordPress-Seite.
3. **Alte Seite umbenennen:**
   - Subdomain `alt.dpsg-trier.de` mit dem Ordner `alt.dpsg-trier.de` anlegen.
   - Die WordPress-Seite mit dem WP Toolkit dorthin **klonen**. Das Klonen passt die Adressen in WordPress (`home`, `siteurl`) mit an.
   - Danach Passwortschutz oder „noindex“ setzen.
4. **Aufräumen auf Test**, nach dem Redaktionsstopp und vor dem Kopieren. Dort ist die Mail-Umleitung noch aktiv, und auf Live landet nichts davon.
   - Backend → Verwaltung → E-Mail-Versand: Warteschlange leeren.
   - Testanmeldungen, Testzahlungen und Testbestellungen löschen.
   - Testbenutzer ohne Funktion entfernen.
5. **Kopieren** wie oben: Datenbank, Dateien. Vorher im Environment `dv-trier-live`:
   - `APP_URL` auf `https://www.dpsg-trier.de`;
   - `MAIL_TEST_REDIRECT_TO`, `MAIL_TEST_ALLOWLIST` und `KLUFT_TESTSYSTEM` löschen;
   - Variable `KLUFT_MAIL_LIVE=ja` setzen.
6. **Deploy `dv-trier-live`**, danach den Cronjob anlegen. Ab jetzt gehen Mails an echte Empfänger*innen. Prüfen: Das Deploy-Protokoll zeigt „Mailer: smtp“ und keine Warnung zu Mails; Backend → Verwaltung → E-Mail-Versand zeigt keinen Hinweis „Mails werden nicht verschickt“.
7. **Domain umstellen:**
   - Plesk → dpsg-trier.de → Hosting-Einstellungen: Dokumentstamm auf `www.dpsg-trier.de/public`.
   - Let’s-Encrypt-Zertifikat für dpsg-trier.de und www.
   - Die Subdomain neu.dpsg-trier.de entfernen.
8. **Prüfen:**
   - Startseite, `/status`;
   - Anmeldung mit Microsoft 365;
   - Kontaktformular mit echter Mail;
   - eine alte WordPress-Adresse;
   - Systemstatus im Backend;
   - im privaten Fenster: Die Wartungsseite erscheint.

   Danach auf Live **Verwaltung → Wartungsmodus → Ausschalten**. Geht das Backend nicht: Actions → Server-Prüfung → `dv-trier-live` → `wartung:aus`. Das Monitoring meldet die Website in dieser Zeit als nicht erreichbar (503).
9. **Statistik auf null setzen**, erst nach den Prüfungen, weil die Datenbank samt Testaufrufen von Test kommt und auch die Prüfaufrufe mitzählen würden:
   - Backend → Verwaltung → Aufrufe → „Alle Zähler löschen“ (nur Admins). Alternativ auf dem Server `php artisan aufrufe:zuruecksetzen --force`.
   - **Prüfen:** Oben auf der Seite steht „Noch keine Aufrufe gezählt (0).“ Nach dem ersten Besuch von außen steht dort „Gezählt seit 21.11.2026“.
10. **Test schließen:**
   - Im Environment `dv-trier` die Variablen `KLUFT_TESTSYSTEM=geschlossen` und `KLUFT_LIVE_URL=https://www.dpsg-trier.de` setzen.
   - Danach Deploy `dv-trier`.
11. **Danach:**
    - `MONITOR_URL` im Environment `dv-trier-live` setzen. `dv-trier-live` kommt zu Monitoring und Web-Checks (Technik).
    - Backups der Live-Website prüfen.
    - bolivienpartnerschaft.de und beraten-online.de auf Live umstellen.
    - Die alte Seite abschalten, sobald alles läuft.

**Zurück, wenn etwas schiefgeht:** In Plesk den Dokumentstamm von dpsg-trier.de wieder auf `httpdocs` stellen, dort liegt die WordPress-Seite unverändert. Test und die Sicherungen bleiben unberührt.

## Ab dem 22.11.

- Inhalte nur noch auf www.dpsg-trier.de pflegen.
- Deploys: `dv-trier-live` für die Website. `dv-trier` nur, wenn eine neue Funktion vorher ausprobiert werden soll.
- Test bekommt bei Bedarf eine frische Kopie von Live, auf demselben Weg in umgekehrter Richtung.
