# Stammes-Website Roden – Übergabe

Stand: 19.10.2026. Arbeitsgrundlage für eine eigene Claude-Code-Sitzung, die
nur die Website des Stamms Roden betreut. Allgemeines zu Kluft steht in
`README.md`, `docs/deploy.md` und `docs/einrichtung.md`.

## Worum es geht

- **Stamm:** DPSG Stamm Graf Folke Bernadotte, Saarlouis-Roden, DPSG-Nummer 210601.
  - Bezirk Saar-Hochwald, Diözese Trier.
  - Vorstand: Kimberly Lamprecht und Flavio Russello (Russello mit Doppel-s, ebenso Julia Russello).
- **Website:** https://www.dpsg-roden.de. Die alte WordPress-Seite in `httpdocs` ist entfernt.
- **Software:** Kluft (Laravel 12, Filament 4, Livewire, PHP 8.3). Ein Code für mehrere Instanzen:
  - `dv-trier`: test.dpsg-trier.de
  - `saar-hochwald`: der Bezirk
  - `roden`: dieser Stamm
- **Repository:** `son1x/kluft`, Branch `claude/kluft-instanzen`.
  - Auf diesem Branch arbeiten auch andere Sitzungen (DV Trier, Bezirk).
  - Vor jedem Push `git pull` ausführen und nur auf diesen Branch pushen.
  - Keine Pull Requests, außer sie werden ausdrücklich verlangt.

## Was zu Roden gehört

| Datei | Inhalt |
|---|---|
| `instanzen/roden/config.php` | baut die Konfiguration mit `App\Support\Einrichtung\StammVorlage` |
| `instanzen/roden/angaben.json` | Angaben wie aus dem Einrichtungsassistenten (siehe unten) |
| `instanzen/roden/public/images/logo.png` | Logo |
| `database/seeders/Einrichtung/StammStartSeeder.php` | legt beim ersten Deploy Inhalte aus `angaben.json` an |
| `docs/deploy.md`, Abschnitt „Stammes-Instanz im Repository (Beispiel Roden)“ | Einrichtung, Microsoft 365, erster Zugang |
| `scripts/entra-app-anlegen.sh` | App-Registrierung für die Anmeldung mit Microsoft 365 |

### `angaben.json`

- `stamm`, `ort`, `nummer`, `bezirk`, `dioezese`, `domain`, `email` (kontakt@dpsg-roden.de).
- Adresse: Donatusstraße 34a, 66740 Saarlouis.
- `ausbau`: 4.
- `zugang`: erster Zugang, Rollen-Zugang „Stammesvorstand“ mit kontakt@dpsg-roden.de.
- `gruppenstunden`, `gruppenstunden_orte`:

  | Stufe | Gruppenstunde |
  |---|---|
  | Biber | dienstags, 16:00–17:00 Uhr |
  | Wölflinge | donnerstags, 16:30–18:00 Uhr |
  | Jungpfadfinder | dienstags, 17:15–18:45 Uhr |
  | Pfadfinder | donnerstags, 16:30–18:00 Uhr |
  | Rover | donnerstags, 18:00–19:30 Uhr |

- `alter`: Jahre je Stufe plus Angabe als Klassenstufe:

  | Stufe | Jahre | Angabe auf der Website |
  |---|---|---|
  | Biber | 4–7 | „4 Jahre bis 2. Klasse“ |
  | Wölflinge | 7–11 | „3. bis 5. Klasse“ |
  | Jungpfadfinder | 11–14 | „6. bis 8. Klasse“ |
  | Pfadfinder | 14–16 | „9. und 10. Klasse“ |
  | Rover | 16–21 | „ab der 11. Klasse bis 21 Jahre“ |

- `leitende`: nur Namen je Stufe, erscheinen als „Im Leitungsteam“:

  | Stufe | Leitende |
  |---|---|
  | Biber | Michele Franke, Kimberly Lamprecht |
  | Wölflinge | Julia Russello, Hendrik Schuler |
  | Jungpfadfinder | Michele Franke, Eva Schmidt, Less Knoll |
  | Pfadfinder | Less Knoll, Ruben Schmidt, Flavio Russello |
  | Rover | Gian-Luca Blasius, Sebastian Hafner, Flavio Russello |

- `seiten`: fertige Seiten.
- `email_schema: "vorname"`, `email_leitende: true`: Alle Leitenden zeigen vorname@dpsg-roden.de (Bindestriche fallen weg, z. B. gianluca@). Der Vorstand ist unter vorstand@dpsg-roden.de erreichbar. Das wird erzeugt, steht also nicht als Liste im Repository.
- Ämter (Migration `2026_10_19_101500_roden_stammesvorstand_und_aemter`, einmalig): Hendrik Schuler ist Kassenwart und Mitgliederbeauftragter, Michele Franke ist für Rüsthaus-Bestellungen zuständig. Dazu der Stammesvorstand als Personen mit Gruppenfoto (`instanzen/roden/dateien/stammesvorstand.jpg`, ohne EXIF; Foto: Julia Hafner).
- `stufenfarben`: Die Biber tragen in Roden Gelb (`#ffed00`, DPSG-Gelb, mit dunkler Schrift). Wirkt sofort nach dem Deploy, ohne Datenbank.

**Wichtig:** Zugang, Seiten, Gruppenstunden, Alter und Leitende legt der
`StammStartSeeder` nur beim **ersten** Deploy an. Der ist am 19.10.2026
gelaufen. Spätere Änderungen in `angaben.json` kommen nicht mehr in die
Datenbank. Sie werden im Backend gepflegt (`/redaktion`, z. B. Verwaltung →
Stufen, Personen, Seiten) oder bei Bedarf mit einer Inhalts-Migration
nachgezogen. `angaben.json` trotzdem aktuell halten, sie ist die Vorlage für
eine Neuinstallation.

Für Personen gilt: nur Namen, keine privaten Adressen oder Telefonnummern im
Repository.

## Server

- **Hosting:** Netcup-Webhosting mit Plesk, derselbe Server wie test.dpsg-trier.de.
  - Daneben liegen dort andere Domains: dpsg-trier.de, dpsg-saar-hochwald.de, cloud.*, wp.dpsg-roden.de, lam.dpsg-roden.de usw.
  - Diese Ordner nie anfassen.
- **Zielordner:** `/dpsg-roden.de/httpdocs` (GitHub-Secret `SSH_TARGET_DIR`).
- **Dokumentstamm:** Plesk lässt ihn für die Hauptdomain nicht ändern. Er zeigt deshalb auf `httpdocs` statt auf `httpdocs/public`.
  - Die `.htaccess` im Projekt-Hauptordner leitet alles nach `public/` weiter und sperrt `.env`, `storage`, `vendor`, `*.md` usw. Das ist dieselbe Lösung wie bei test.dpsg-trier.de.
  - Dauerhaft besser: Netcup-Support bitten, den Dokumentstamm auf `httpdocs/public` zu stellen.
- **Datenbank:** MySQL 8.4, eigene Datenbank für Roden (Zugang nur in GitHub-Secrets).
- **Ordner `/dpsg-roden.de/kluft`:** Rest eines ersten Deploy-Versuchs. Kann gelöscht werden, wenn er noch existiert und `httpdocs` läuft.

## Deploy

- **Starten:** nur von Hand: Actions → „Deploy“ → Run workflow → Instanz `roden`.
  - Ein Push deployt automatisch nur `dv-trier`.
  - Pushes, die nur `instanzen/roden/**`, `docs/**` oder `*.md` ändern, deployen gar nichts.
  - Pushes auf Code außerhalb davon deployen dv-trier mit. Vor so einem Push prüfen, ob gerade ein Deploy läuft.
- **„Nur prüfen“:** baut, testet, meldet sich per SSH an und ändert nichts. Es zeigt:
  - PHP-Version und Erweiterungen, freien Speicher;
  - die Datenbankverbindung (Version, Tabellenzahl);
  - ob Kluft installiert ist (`KLUFT_INSTANZ` aus der Server-`.env`), offene Migrationen;
  - die letzten drei Fehler aus `storage/logs/laravel.log`.

  Damit lassen sich 500er ohne SSH-Zugang untersuchen.
- **Schutzregeln in der Serverprüfung:**
  - Abbruch, wenn im Zielordner eine andere Instanz läuft.
  - Abbruch, wenn der Ordner bei einer Erstinstallation nicht leer ist (bis auf `.well-known`/`cgi-bin`).
  - Abbruch, wenn auf dem Server MariaDB läuft, im Environment aber die DB-Secrets fehlen.
- **Was hochgeladen wird:** nur `instanzen/roden/`, nicht die Ordner der anderen Instanzen. `mirror --delete` spiegelt den Zielordner.
  - Ausgenommen sind `.env`, `storage/app/private`, `storage/logs`, Sessions/Cache, `public/uploads`.
  - Der Deploy legt die Laufzeitordner unter `storage/` an.
- **Kartendaten:** Stämme haben keine eigene Karte, der Kartenbau wird übersprungen.
- **Bekannte Stolpersteine von der Ersteinrichtung (alle behoben):**
  - Kartendaten-Schritt ohne Autoloader;
  - Werte als Secret statt Variable (jetzt geht beides);
  - fehlendes `storage/framework/sessions` (Fehler 500);
  - instabiler RSS-Test.

## GitHub-Environment „roden“

Alle Werte stehen als **Secrets** im Environment. Der Deploy liest `APP_URL`,
`APP_ENV`, `APP_NAME`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` und `PHP_BIN` als
Variable oder als Secret.

**Gesetzt:**
- `APP_ENV`, `APP_URL`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `PHP_BIN`
- `SSH_HOST`, `SSH_PORT`, `SSH_USERNAME`, `SSH_PRIVATE_KEY`, `SSH_TARGET_DIR`
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `BREVO_SMTP_LOGIN`, `BREVO_SMTP_KEY` (Roden hat ein eigenes Brevo-Konto)
- `MITGLIEDER_DRIVER`, `BACKUP_PASSWORD`

**Fehlt noch:**
- `MONITOR_URL`: als **Variable** (nicht Secret), sonst überspringt das Monitoring Roden.
- `MAIL_TEST_REDIRECT_TO` und `MAIL_TEST_ALLOWLIST`: Ohne sie landen Mails nur im Log. Für den Anmeldecode muss kontakt@dpsg-roden.de in der Allowlist stehen.
- `ENTRA_TENANT_ID`, `ENTRA_CLIENT_ID`, `ENTRA_CLIENT_SECRET`:
  - Roden hat Microsoft 365 für alle Funktionsträger.
  - Erzeugen mit `scripts/entra-app-anlegen.sh` (Anleitung in `docs/deploy.md`).
  - Redirect-URI: `https://www.dpsg-roden.de/auth/entra/callback`.
- `NAMI_AUTH` (für NaMi) und `BACKUP_WEBDAV_*` (externes Backup), jeweils optional.

Vorlage aller Werte: `docs/environment-vorlage/`. Werte nur mit
`scripts/environment-anlegen.sh` oder in GitHub eintragen, nie ins Repository
oder in den Chat.

## Anmeldung und Backend

- **Backend:** `https://www.dpsg-roden.de/redaktion`. Anmelden unter `/redaktion/anmelden` per E-Mail-Code.
- **Erster Zugang:** „Stammesvorstand“, kontakt@dpsg-roden.de, Admin. Weitere Personen bekommen ihre Rollen unter „Benutzer“.
- **Funktionen auf Stammesebene:**
  - Kasse/Mitgliedsbeiträge (`docs/beitraege.md`): Rolle „Kasse“, NaMi-Abruf, halbjährliche Abrechnung. Bei Übernahme zahlt der Stamm keinen Bundesbeitrag.
  - Nachrichten-Eingang (`docs/nachrichten.md`): standardmäßig aus, unter Verwaltung → Funktionen einschaltbar.
  - Stufen-Altersangaben mit eigener Angabe als Text (Verwaltung → Stufen).

## Offene Punkte (Stand Übergabe)

1. ~~**Website prüfen**~~ (erledigt 19.10.2026): Startseite und `/redaktion/anmelden` liefern 200. `.env` und `composer.json` sind gesperrt (403).
   `/api/status` liefert 503, weil der Cronjob fehlt (Punkt 2). Datenbank und Mails: ok. Backups: eingeschaltet, aber noch keins gelaufen.
   Kalender und Instagram: aus.
   Bei späteren 500ern: „Nur prüfen“ starten und den Log-Auszug lesen.
2. **Cronjob in Plesk:** jede Minute `/usr/bin/php /dpsg-roden.de/httpdocs/artisan schedule:run`, den genauen Pfad wie bei test.dpsg-trier.de. Ohne Cronjob laufen keine Warteschlangen, Backups oder Mails.
3. **Mail:**
   - Absender-Domain dpsg-roden.de in Brevo bestätigen (DNS);
   - `MAIL_TEST_REDIRECT_TO`/`MAIL_TEST_ALLOWLIST` setzen;
   - erste Anmeldung mit kontakt@dpsg-roden.de testen.
4. **`MONITOR_URL`** als Variable setzen.
5. **Microsoft 365:** Entra-App anlegen, `ENTRA_*` setzen, neu deployen.
6. **Dokumentstamm:** Netcup bitten, ihn auf `httpdocs/public` umzustellen. Danach die Weiterleitung über die Root-`.htaccess` nicht mehr nötig.
7. Den alten Ordner `/dpsg-roden.de/kluft` löschen, falls noch vorhanden.
8. **Inhalte mit dem Stamm durchgehen:** Texte, Fotos, Impressum/Datenschutz, Leitungsteams. Ansprechpartner ist Flavio Russello.
9. **Demo:** Die Demo-Seite (Artifact) aus der Planungsphase zeigt noch die alten Gruppenstunden-Zeiten. Sie ist nicht mehr maßgeblich.

## Aufteilung zwischen den Sitzungen

Zwei Claude-Code-Sitzungen arbeiten auf demselben Branch `claude/kluft-instanzen`:

| Stammes-Sitzung (diese) | Haupt-Sitzung |
|---|---|
| Inhalte, Konfiguration und Deploys von Roden | DV Trier (`dv-trier`) und Bezirk Saar-Hochwald (`saar-hochwald`) |
| Alles nur für Stämme: `StammVorlage`, `StammStartSeeder`, Einrichtungsassistent | Gemeinsamer Kern: Deploy-Workflow, Monitoring, Backups, Rollen, Anmeldung, NaMi-Anbindung |
| Stammes-Funktionen: Kasse/Mitgliedsbeiträge (`docs/beitraege.md`), Leitergewinnung (`App\Support\Leitergewinnung`, Seite „Leiter*in werden“), Stufenseiten auf Stammesebene | Funktionen für alle Ebenen, z. B. Veranstaltungen, Nachrichten-Eingang |

Regeln dafür:

- **Nur für Stämme schalten:** über `config('dpsg.site.level') === 'Stamm'` (z. B. `Leitergewinnung::aufStammesebene()`) oder einen Funktionsschalter mit `'ebene' => 'Stamm'` in `App\Support\Features::KATALOG`. DV und Bezirk dürfen sich dadurch nicht verändern.
- **Gemeinsamer Code wird automatisch auf dv-trier deployt:** Vor dem Push die Tests für alle drei Instanzen laufen lassen (`KLUFT_INSTANZ=roden`, `dv-trier`, `saar-hochwald`) und in Actions prüfen, dass gerade kein Deploy läuft.
- **Vor jedem Push `git pull --rebase`.** Nur eigene, noch nicht gepushte Commits umbauen, nie fremde.
- **Direkter Austausch:** Die Stammes-Sitzung (`session_01DnjMEd7816yZMn8RwKe28G`) schreibt der Haupt-Sitzung (`session_014CFjuVyS73EAT5ak9eGcZt`) direkt per `send_message`. Die Haupt-Sitzung kann (Stand 19.10.2026) nicht direkt senden und antwortet über `docs/abstimmung.md`.
- **Briefkasten `docs/abstimmung.md`:** Rückweg und Protokoll. Zu Beginn jeder Sitzung und nach jedem `git pull` die offenen Einträge lesen und beantworten.
- **Änderungen am gemeinsamen Kern:** Muss eine Sitzung dort etwas ändern (Workflow, Rollen, Modelle anderer Bereiche), meldet sie das der anderen (direkt oder per Eintrag im selben Commit). Größere Umbauten nur nach Absprache.
- **Neue Migrationen:** Dateinamen mit aktuellem Datum und Uhrzeit, damit sich die beiden Sitzungen nicht überschneiden.

## Arbeitsweise und Regeln

- **Geheimes:** Secrets nur in GitHub-Environments oder der Server-`.env`, nie im Repository, in Commits oder im Chat. Was im Chat stand, gilt als kompromittiert.
  - NaMi- und Instagram-Zugangsdaten nie in den Chat.
  - Das NaMi-Passwort wird nicht gespeichert, höchstens verschlüsselt im eigenen Profil.
  - Diagnose-Ausgaben ohne personenbezogene Daten.
- **Persönliche E-Mail-Adressen** nicht ins Repository. Funktionsadressen wie kontakt@dpsg-roden.de sind in Ordnung.
- **Server:** keine TLS-Prüfung abschalten, keinen Proxy umgehen. Die Server-`.env` nicht ins Deploy übernehmen.
- **Andere Repositories:** `son1x/dpsg-dv-trier` und `son1x/dpsg-bezirk-saar-hochwald` bleiben unverändert, `son1x/dpsg-wittlich` nur lesen.
- **Tests lokal:**
  - `composer install`, dann `KLUFT_INSTANZ=roden php artisan test`;
  - zusätzlich `KLUFT_INSTANZ=dv-trier` und `KLUFT_INSTANZ=saar-hochwald`, wenn gemeinsamer Code geändert wurde;
  - Stil mit `vendor/bin/pint --test`;
  - Larastan (Stufe 5) läuft nur in der CI („Qualität und Sicherheit“). Nach einem Push den Lauf prüfen.
- **Commits:** deutsch, kurz und sachlich wie im übrigen Verlauf. `CHANGELOG.md` aus Sicht der Redaktion ergänzen, wenn sich für Nutzende etwas ändert.
