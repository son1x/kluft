<p><img src="docs/logo/kluft-logo.svg" alt="Kluft" width="260"></p>

# Kluft

**Das Verbands-CMS für Gliederungen der DPSG.** Eine Codebasis für Diözesanverbände und Bezirke: Jede Gliederung bekommt ihre eigene Website mit Redaktion, Veranstaltungen und Anmeldungen, betrieben auf ihrem eigenen Webspace.

Der Name: Die Kluft tragen alle gleich, das Halstuch zeigt, wo man herkommt. Genauso hier: Der Code ist für alle derselbe, Name, Logo, Inhalte und eingeschaltete Funktionen gehören der jeweiligen Gliederung.

## Was Kluft kann

- **Redaktion:** Seiten und Beiträge, Mediathek mit Bildvarianten, Menüs, Glossar (Begriffe wie DAK oder FAK mit Erklärung), Weiterleitungen
- **Gliederung zeigen:** Verzeichnis der Bezirke und Stämme mit selbst gehosteter Karte, Personen und Gremien mit Porträts, Stufen und Fachbereiche mit eigenen Seiten
- **Veranstaltungen:** Anmeldung mit Double-Opt-In, „Meine Anmeldungen“ per E-Mail-Code, Teilnehmendenliste und Export, Dokumente, Löschfristen, optional Mitgliederabgleich mit der NaMi
- **Downloads:** Methoden, Formulare und Arbeitshilfen mit Suche und Filter; Typ, Größe, Seiten und Stand ermittelt das CMS
- **Kontakt und Newsletter:** Kontaktformular mit Themen, Newsletter über Brevo, optional WhatsApp-Kanal und Push-Nachrichten
- **Betrieb:** Anmeldung mit Microsoft 365 oder per E-Mail-Code, Notfall-Zugang, Rollen und Audit-Log, verschlüsselte Backups, Monitoring, Datenschutzauskunft (KDG), signierte Updates für eigenständige Installationen ([docs/updates.md](docs/updates.md))

Technik: Laravel 12, Filament 4, Livewire, Tailwind 4, MariaDB oder SQLite. Auf dem Server reichen PHP 8.3 und ein Cronjob. Ein normaler Webspace genügt.

## Instanzen

| Schlüssel | Gliederung | Stand |
|---|---|---|
| `dv-trier` | DPSG Diözesanverband Trier | in Betrieb (test.dpsg-trier.de) |
| `saar-hochwald` | DPSG Bezirk Saar-Hochwald | im Aufbau |

Stämme bekommen je eine eigene Installation ohne GitHub: Release-Paket hochladen, Domain aufrufen, Einrichtungsassistent ausfüllen ([docs/einrichtung.md](docs/einrichtung.md)). Ihre Instanz entsteht dabei auf dem Webspace (`instanzen/stamm-<name>/`), Updates kommen signiert über Kluft selbst ([docs/updates.md](docs/updates.md)).

Die Umgebungsvariable `KLUFT_INSTANZ` wählt die Instanz, Standard ist `dv-trier`. Alles, was eine Gliederung ausmacht, liegt in ihrem Ordner:

```
instanzen/<schlüssel>/
├── config.php      Name und Wortformen, Kontakt, Karte, Gremien, Funktionen, Newsletter, Einwilligung
├── seeders/        Startinhalt (Start-Seeder) und Grundbestand für Tests (Basis-Seeder)
├── migrations/     Inhalte, die eine laufende Installation später noch bekommen soll (optional)
├── public/         Logo und Favicon; landen beim Deploy unter public/instanz/
└── dateien/        Bilder und PDFs für den Startinhalt (optional)
```

Migrationen unter `database/migrations` legen nur Tabellen an, nie Inhalte. Warum: [docs/entscheidungen/kluft-inhalt-und-schema.md](docs/entscheidungen/kluft-inhalt-und-schema.md).

## Lokal starten

```bash
composer install
npm ci && npm run build
cp .env.example .env            # KLUFT_INSTANZ setzen
php artisan key:generate
php artisan kluft:assets        # Logo und Favicon der Instanz bereitstellen
php artisan migrate --seed      # Schema und Startinhalt
php artisan serve
```

Tests: `php artisan test` für den DV, `KLUFT_INSTANZ=saar-hochwald php artisan test` für den Bezirk. Tests, die nur für eine Instanz gelten, tragen `#[Group('<schlüssel>')]` und werden bei den anderen übersprungen. Die CI prüft jede Instanz einzeln, dazu Pint und Larastan.

## Neue Gliederung einrichten

**1. Ordner anlegen.** Schlüssel wählen: Kleinbuchstaben mit Bindestrich, z. B. `bezirk-musterstadt`. `instanzen/saar-hochwald` als Vorlage kopieren; das ist die kleinste vollständige Instanz.

**2. `config.php` anpassen.**
- `site`: Name, Kurzname, Ebene mit Wortformen (`level`, `level_genitive`, …), Domain, Beschreibung, Leitung und Geschäftsstelle (`office` mit Artikel)
- `contact`, `gremien`, `person_email_domain`
- `map`: Ausschnitt (`bbox`) und Mittelpunkt
- `features` und `sections`: einschalten, was die Gliederung nutzt (Standard; Admins können einzelne Funktionen im Backend unter „Verwaltung → Funktionen“ an- und abschalten, siehe `App\Support\Features::KATALOG`)
- `newsletter`: `null`, wenn sie keinen hat
- `event_consent`: Einwilligungstext mit Version

**3. Logo und Favicon** nach `instanzen/<schlüssel>/public/`, im Format wie bei Saar-Hochwald. In `config.php` auf `instanz/…` verweisen und `logo_size` angeben.

**4. Seeder.**
- Namespace `Instanzen\<Name>\Seeders`.
- Der **Basis-Seeder** legt den Grundbestand an (Gliederung, Stämme, Glossar); er läuft auch in den Tests.
- Der **Start-Seeder** legt einmalig beim ersten Deploy den Startinhalt an: Pflichtseiten wie Impressum und Datenschutz, Startseite, Kontaktthemen.
- Beide in `config.php` unter `seeders` eintragen und danach `composer dump-autoload` ausführen.
- Später gepflegte Inhalte nie überschreiben: `firstOrCreate` statt `updateOrCreate`.

**5. Tests.** Unter `tests/Feature/Instanzen/` einen Test mit `#[Group('<schlüssel>')]` anlegen, wie `SaarHochwaldTest`. Den Schlüssel in die Matrix von `.github/workflows/quality.yml` aufnehmen.

**6. Workflows.** Den Schlüssel in die Auswahllisten von `deploy.yml`, `server-check.yml` und `wp-import.yml` und in die Matrix von `monitoring.yml` und `web-checks.yml` eintragen.

**7. Server und GitHub-Environment.** Den Webspace oder vServer vorbereiten. Dann in GitHub unter Settings → Environments ein Environment mit dem Schlüssel als Namen anlegen, mit Variablen und Secrets. Alles Nötige steht in [docs/deploy.md](docs/deploy.md).

**8. Erster Deploy.** Actions → „Deploy“ → Instanz wählen, zuerst mit **„Nur prüfen“**, dann echt. Der erste echte Lauf legt die Datenbank an und spielt den Startinhalt ein.

**9. Notfall-Admin.** Per SSH im Projektordner `php artisan cms:create-superadmin` ausführen. Der Befehl fragt nach E-Mail und Passwort (mindestens 16 Zeichen) und zeigt das TOTP-Geheimnis für die Authenticator-App einmalig an. Beides gehört in einen Passwortmanager. Unter `https://<domain>/notfall-login` anmelden und die weiteren Konten anlegen. Der Notfall-Zugang ist nur für den Anfang und für Notfälle gedacht.

**10. Anmeldung der Redaktion.**
- **Mit Microsoft 365:** Im Entra-Admin-Center eine App registrieren.
  - Nur eigener Mandant.
  - Umleitungs-URI `https://<domain>/auth/entra/callback`.
  - Ein Client-Geheimnis anlegen.
  - Delegierte Berechtigungen `openid`, `profile`, `email` und `User.Read`; für die Verzeichnissuche beim Anlegen von Konten zusätzlich `User.ReadBasic.All` mit Administratorzustimmung.
  - Die Werte kommen als `ENTRA_CLIENT_ID`, `ENTRA_CLIENT_SECRET` und `ENTRA_TENANT_ID` ins Environment.

  Konten legt die Verwaltung vorab an, per E-Mail oder UPN. Wer sich ohne Konto anmeldet, wartet auf Freischaltung.
- **Ohne eigenen M365-Mandanten:** noch nicht gebaut. Vorschlag: [docs/entscheidungen/anmeldung-ohne-m365.md](docs/entscheidungen/anmeldung-ohne-m365.md).

## Weitere Dokumentation

- [docs/deploy.md](docs/deploy.md): Deploy, Environments, Server, Umstellung des DV
- [docs/mitgliederabgleich.md](docs/mitgliederabgleich.md): NaMi und hitobito
- [docs/pflichtenheft.md](docs/pflichtenheft.md): Anforderungen (ursprünglich für den DV Trier)
- [docs/entscheidungen/](docs/entscheidungen/): Architekturentscheidungen
- [docs/logo/](docs/logo/): Logo (Entwurf) als SVG, mit und ohne Schriftzug
