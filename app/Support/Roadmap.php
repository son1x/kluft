<?php

namespace App\Support;

use App\Mail\TestRecipientFilter;
use App\Models\Calendar;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use App\Services\Backup\BackupService;
use App\Services\BrevoNewsletter;
use App\Services\Instagram\InstagramClient;
use App\Services\WhatsApp\WhatsAppSettings;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;

/**
 * Daten für /roadmap: Umsetzungsstand je Pflichtenheft-Abschnitt
 * (resources/data/roadmap.php) und die offenen Punkte der Projektleitung.
 */
class Roadmap
{
    public const HEARTBEAT_KEY = 'scheduler:heartbeat';

    public const STATUSES = [
        'done' => 'Erledigt',
        'partial' => 'In Arbeit / teilweise',
        'open' => 'Offen',
        'later' => 'Später (Phase 2 / Kann)',
        'decide' => 'Zu entscheiden',
        'info' => 'Beschreibung, keine Umsetzung',
    ];

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return require resource_path('data/roadmap.php');
    }

    /**
     * Kapitel (## im Pflichtenheft) mit ihren Unterabschnitten (###).
     *
     * @return list<array{num: string, title: string, status: string, note: string, children: list<array{num: string, title: string, status: string, note: string}>}>
     */
    public function chapters(): array
    {
        $chapters = [];

        foreach ($this->data()['sections'] as $num => $section) {
            $num = (string) $num;
            $entry = ['num' => $num, ...$section];

            if (! str_contains($num, '.')) {
                $chapters[$num] = [...$entry, 'children' => []];
            } else {
                $chapters[strtok($num, '.')]['children'][] = $entry;
            }
        }

        return array_values($chapters);
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = array_fill_keys(array_keys(self::STATUSES), 0);

        foreach ($this->data()['sections'] as $num => $section) {
            // Kapitel mit Unterabschnitten nicht doppelt zählen.
            if (str_contains((string) $num, '.') || ! $this->hasChildren((string) $num)) {
                $counts[$section['status']]++;
            }
        }

        return $counts;
    }

    private function hasChildren(string $num): bool
    {
        foreach (array_keys($this->data()['sections']) as $key) {
            if (str_starts_with((string) $key, $num.'.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Was die Projektleitung selbst erledigen muss. `done` wird, wo
     * möglich, automatisch geprüft; null = nur manuell abhakbar.
     *
     * @return list<array{key: string, phase: string, title: string, detail: string, done: bool|null}>
     */
    public function todos(): array
    {
        // Nicht automatisch prüfbare Punkte werden in resources/data/
        // roadmap.php unter 'todos_done' als erledigt eingetragen.
        $manual = $this->data()['todos_done'] ?? [];

        return array_map(fn (array $todo) => $todo['done'] === null && in_array($todo['key'], $manual, true)
            ? [...$todo, 'done' => true]
            : $todo, $this->todoList());
    }

    /**
     * @return list<array{key: string, phase: string, title: string, detail: string, done: bool|null}>
     */
    private function todoList(): array
    {
        return [
            [
                'key' => 'cron',
                'phase' => '1b',
                'title' => 'Cronjob im Hosting-Panel anlegen',
                'detail' => 'Plesk → Geplante Aufgaben: „php artisan schedule:run“ jede Minute im Projektverzeichnis. Ohne ihn werden keine Kalender abgerufen und keine Anmeldungen fristgerecht gelöscht.',
                'done' => $this->schedulerRuns(),
            ],
            [
                'key' => 'mariadb',
                'phase' => '0',
                'title' => 'MariaDB anbinden',
                'detail' => 'Datenbank in Plesk anlegen (utf8mb4) und als GitHub-Secrets DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD hinterlegen. Beim nächsten Deploy werden die Inhalte automatisch aus SQLite übernommen.',
                'done' => in_array(config('database.connections.'.config('database.default').'.driver'), ['mysql', 'mariadb'], true),
            ],
            [
                'key' => 'kalender',
                'phase' => '1b',
                'title' => 'Nextcloud-Kalender eintragen',
                'detail' => 'GitHub-Secret NEXTCLOUD_ALLOWED_HOSTS setzen (Nextcloud-Domain), dann im Backend unter „Kalender“ die öffentlichen ICS-Links anlegen.',
                'done' => filled(config('services.nextcloud.allowed_hosts')) && Calendar::query()->where('active', true)->exists(),
            ],
            [
                'key' => 'brevo-newsletter',
                'phase' => '1b',
                'title' => 'Brevo für den Georgsbrief (Newsletter) verbinden',
                'detail' => 'In Brevo Liste und Double-Opt-In-Template anlegen; GitHub-Secrets BREVO_API_KEY, BREVO_NEWSLETTER_LIST_ID, BREVO_DOI_TEMPLATE_ID setzen.',
                'done' => app(BrevoNewsletter::class)->isConfigured(),
            ],
            [
                'key' => 'domain-bolivien',
                'phase' => 'Bolivien',
                'title' => 'Domain bolivienpartnerschaft.de aufschalten',
                'detail' => 'Bei der Migration: DNS auf das Webhosting zeigen lassen, in Plesk als Alias der Website anlegen und Let’s-Encrypt-Zertifikat ausstellen.',
                'done' => null,
            ],
            [
                'key' => 'whatsapp-meta',
                'phase' => 'WhatsApp',
                'title' => 'WhatsApp-Newsletter mit Meta verbinden',
                'detail' => 'Erst nach Freigabe durch die/den Datenschutzbeauftragte:n und Klärung des Budgets: Setup-Anleitung in docs/whatsapp_newsletter.md (Abschnitt 9) – Meta-Business-Konto verifizieren, Büro-Festnetznummer per Sprachanruf verifizieren, Systembenutzer-Token erzeugen, im Backend unter „WhatsApp-Newsletter → Verbindung“ eintragen, Webhook bei Meta hinterlegen, Startvorlagen einreichen. Für test.dpsg-trier.de die kostenlose Testnummer von Meta nutzen und das Secret WHATSAPP_TEST_ALLOWLIST setzen. Rolle „WhatsApp-Newsletter-Admin“ unter Benutzer vergeben.',
                'done' => app(WhatsAppSettings::class)->configured(),
            ],
            [
                'key' => 'spenden-bank',
                'phase' => 'Bolivien',
                'title' => 'Bankverbindung für Bolivien-Spenden eintragen',
                'detail' => 'Seite „bolivien-spenden“ im Backend: Platzhalter durch Kontoinhaber, IBAN und Verwendungszweck ersetzen.',
                'done' => ($spenden = Page::query()->where('slug', 'bolivien-spenden')->value('body')) !== null && ! str_contains($spenden, 'TODO'),
            ],
            [
                'key' => 'beratung-postfach',
                'phase' => 'Beraten',
                'title' => 'Postfach beratung@dpsg-trier.de anlegen',
                'detail' => 'Dorthin gehen die Kontaktanfragen zu „BeratungsPower & Coaching“ (Thema „Beratung & Coaching“). Gruppenfoto der Berater*innen nur mit Einwilligung der Abgebildeten nachreichen.',
                'done' => null,
            ],
            [
                'key' => 'beraten-online',
                'phase' => 'Beraten',
                'title' => 'Domain beraten-online.de umstellen',
                'detail' => 'Bei der Migration: DNS von beraten-online.de auf das Webhosting, in Plesk als Alias mit Zertifikat, dann die alte WordPress-Seite abschalten. Die Inhalte sind schon übernommen (Backend → Seiten → Reiter „beraten-online.de“).',
                'done' => null,
            ],
            [
                'key' => 'notfall-login',
                'phase' => '1b',
                'title' => 'Notfall-Login einrichten und testen',
                'detail' => 'Per SSH „php artisan cms:create-superadmin“ (bzw. mit --reset zum Neu-Einrichten) ausführen, möglichst mit eigener Adresse wie notfall@dpsg-trier.de statt der eigenen M365-Adresse; TOTP in einer Authenticator-App speichern, Anmeldung unter /notfall-login einmal testen. Zugangsdaten offline verwahren.',
                'done' => $this->breakGlassUsable(),
            ],
            [
                'key' => 'update-schluessel',
                'phase' => 'Stämme',
                'title' => 'Updates 1/4: Signaturschlüssel erzeugen',
                'detail' => 'Auf dem eigenen Rechner im Kluft-Ordner „php artisan kluft:update-schluessel“ ausführen. Den GEHEIMEN Schlüssel nur bei GitHub eintragen: Repository → Settings → Secrets and variables → Actions → „New repository secret“, Name KLUFT_RELEASE_SIGNING_KEY – nicht ins Repository, nicht in Chat oder Mail. Den ÖFFENTLICHEN Schlüssel aufheben (Passwortmanager) und an die Technik geben; er kommt als KLUFT_UPDATE_PUBLIC_KEY in jede Stammes-Installation. Details: docs/updates.md.',
                'done' => null,
            ],
            [
                'key' => 'update-server',
                'phase' => 'Stämme',
                'title' => 'Updates 2/4: Öffentlichen Ablageort für Releases einrichten',
                'detail' => 'Das Repository ist privat, Stämme können Releases nicht bei GitHub abholen. In Plesk eine Subdomain wie updates.dpsg-trier.de mit eigenem Ordner (nicht dem der Website) und Let’s-Encrypt-Zertifikat anlegen, darin einen Ordner „kluft“. Für den Upload einen SSH-Schlüssel mit Zugriff auf diesen Ordner erzeugen und als Repository-Secrets hinterlegen: UPDATE_SFTP_HOST, UPDATE_SFTP_PORT (meist 22), UPDATE_SFTP_USER, UPDATE_SFTP_KEY (privater Schlüssel), UPDATE_SFTP_DIR (absoluter Pfad des Ordners „kluft“).',
                'done' => null,
            ],
            [
                'key' => 'update-release',
                'phase' => 'Stämme',
                'title' => 'Updates 3/4: Erstes Release veröffentlichen',
                'detail' => 'Wenn Schlüssel und Ablageort stehen: zuerst unter Settings → Secrets and variables → Actions → Reiter „Variables“ die öffentlichen Werte KLUFT_UPDATE_URL (z. B. https://updates.dpsg-trier.de/kluft/manifest.json) und KLUFT_UPDATE_PUBLIC_KEY anlegen – damit bekommen neue Installationen aus dem Einrichtungsassistenten die Updates gleich eingerichtet. Dann Actions → „Release“ → „Run workflow“ (Version 0.9.0 aus der Datei VERSION, Änderungen aus CHANGELOG.md). Danach muss https://updates.dpsg-trier.de/kluft/manifest.json im Browser erscheinen. Künftig je Release VERSION erhöhen und einen Abschnitt in CHANGELOG.md schreiben.',
                'done' => null,
            ],
            [
                'key' => 'update-test',
                'phase' => 'Stämme',
                'title' => 'Updates 4/4: Update einmal komplett durchspielen',
                'detail' => 'In einer Testinstallation (z. B. erster Stamm) in der .env KLUFT_UPDATES=manual, KLUFT_UPDATE_URL=https://updates.dpsg-trier.de/kluft/manifest.json und KLUFT_UPDATE_PUBLIC_KEY setzen, Backup einrichten. Dann ein Release 0.9.1 veröffentlichen, im Backend unter Verwaltung → Updates „Aktualisieren“ klicken und nach etwa zwei Minuten Protokoll, Mail und Website prüfen. Einmal auch die Rücksicherung ausprobieren (docs/updates.md).',
                'done' => null,
            ],
            [
                'key' => 'staemme-daten',
                'phase' => '1b',
                'title' => 'Stämme-Daten bestätigen lassen',
                'detail' => 'Bei Bezirken/Stämmen abfragen: Website-Links (19 noch ungeprüft), Standort und Bibergruppen. Biber sind bisher nur bei Friedrichsthal, Roden, Hülzweiler und Hillesheim aktiv.',
                'done' => null,
            ],
            [
                'key' => 'brevo-smtp',
                'phase' => '1c',
                'title' => 'Brevo-SMTP für Transaktionsmails einrichten',
                'detail' => 'GitHub-Secrets BREVO_SMTP_LOGIN, BREVO_SMTP_KEY und MAIL_TEST_REDIRECT_TO (Testadresse) setzen. Erst dann können Anmeldungen bestätigt werden.',
                'done' => config('mail.default') === 'smtp',
            ],
            [
                'key' => 'test-empfaenger',
                'phase' => 'Testing',
                'title' => 'Tester*innen für echte Mails freischalten',
                'detail' => 'GitHub-Secret MAIL_TEST_ALLOWLIST anlegen: bis zu zehn Einträge, getrennt durch Komma, Semikolon oder Zeilenumbruch – einzelne Adressen (z. B. „a@example.org,b@dpsg-trier.de“). Eine ganze Domain („@dpsg-trier.de“) ginge auch, schließt aber Ehrenamtliche mit Verbandsadresse ein. Diese Personen bekommen Mails auf test.dpsg-trier.de direkt, alle anderen Mails gehen weiter an MAIL_TEST_REDIRECT_TO. Nach dem nächsten Deploy aktiv; prüfen mit Actions → „Server-Prüfung“ → mail:check-allowlist. Vor dem Livegang wieder löschen.',
                'done' => TestRecipientFilter::allowlist() !== [],
            ],
            [
                'key' => 'brevo-domain',
                'phase' => '1c',
                'title' => 'Absenderdomain bei Brevo authentifizieren',
                'detail' => 'dpsg-trier.de in Brevo freigeben (SPF, DKIM, DMARC im DNS) und Öffnungs-/Klick-Tracking für Transaktionsmails abschalten.',
                'done' => null,
            ],
            [
                'key' => 'test-anmeldung',
                'phase' => '1c',
                'title' => 'Testanmeldung durchspielen',
                'detail' => 'Bei einer Beispielveranstaltung anmelden, Bestätigungslink und Stornolink ausprobieren, Teilnehmerliste exportieren.',
                'done' => null,
            ],
            [
                'key' => 'personen-freigabe',
                'phase' => 'Abnahme',
                'title' => 'Personenvorstellung mit den Personen abstimmen',
                'detail' => 'Die 15 Personen auf den Seiten Diözesanleitung und Diözesanbüro samt Vorstellungstexten und Gruppenfoto stammen von der bisherigen Website und sind als „abgestimmt“ markiert. Bitte kurz bestätigen lassen; Porträtfotos lassen sich im Backend unter „Personen“ ergänzen.',
                'done' => null,
            ],
            [
                'key' => 'fachbereiche-dak',
                'phase' => 'Abnahme',
                'title' => 'Fachbereiche und Diözesanarbeitskreise mit Inhalt füllen',
                'detail' => 'Die Seiten „Internationale Gerechtigkeit“ und „Inklusion“ haben einen allgemeinen Starttext – bitte von den Fachbereichen prüfen bzw. ergänzen lassen und eine Ansprechperson zuordnen (Personen → „Ansprechperson für Fachbereich“). DAK-Mitglieder je Stufe im Backend unter „Personen“ mit Gremium „Diözesanarbeitskreis“ und der Stufe eintragen (mit Einwilligung).',
                'done' => null,
            ],
            [
                'key' => 'rechtstraeger-angaben',
                'phase' => 'Abnahme',
                'title' => 'Rechtsträger und Impressum vervollständigen',
                'detail' => 'Seiten „Rechtsträger“, „Stiftung“ und „Freunde & Förderer“ prüfen (Struktur, Bankverbindungen, Vorstand des Fördervereins). Im Impressum Vorstand und Vereinsregisternummer des Landesamts St. Georg e. V. ergänzen. Fördergrundsätze, Flyer und Anmeldeformulare als PDF in die Mediathek laden und verlinken.',
                'done' => ($impressum = Page::query()->where('slug', 'impressum')->value('body')) !== null && ! str_contains($impressum, 'TODO'),
            ],
            [
                'key' => 'backups',
                'phase' => 'Abnahme',
                'title' => 'Backups einschalten',
                'detail' => 'GitHub-Secret BACKUP_PASSWORD setzen (mind. 16 Zeichen, zusätzlich offline im Passwortmanager ablegen – ohne es ist kein Backup lesbar). Empfohlen: Kopie außerhalb des Webspace in einen Nextcloud-Ordner über BACKUP_WEBDAV_URL, BACKUP_WEBDAV_USER und BACKUP_WEBDAV_PASSWORD (App-Passwort). Läuft dann täglich um 2 Uhr über den Cronjob.',
                'done' => $this->backupsHealthy(),
            ],
            [
                'key' => 'wp-import-durchsicht',
                'phase' => '1e',
                'title' => 'Importierte WordPress-Beiträge durchsehen',
                'detail' => 'Backend → „WordPress-Import“: Jeden importierten Beitrag öffnen, die Nacharbeitsliste abarbeiten (Personenbezug, Bildrechte/Bildnachweis, Alternativtexte, externe Links), „Durchgesehen“ anhaken und dann veröffentlichen oder löschen.',
                'done' => Post::query()->whereNotNull('wp_id')->exists() && ! Post::query()->whereNotNull('wp_id')->where('import_reviewed', false)->exists(),
            ],
            [
                'key' => 'instagram-zugang',
                'phase' => '1d',
                'title' => 'Instagram-Zugang vorbereiten',
                'detail' => 'Instagram als Business- oder Creator-Konto einrichten, in einer Meta-App „Instagram API mit Instagram-Login“ hinzufügen (Berechtigung instagram_business_content_publish), ein Long-Lived-Token erzeugen und im Backend unter „Integrationen“ eintragen.',
                'done' => app(InstagramClient::class)->isConfigured(),
            ],
        ];
    }

    /**
     * Notfall-Account vorhanden UND sein TOTP-Secret lesbar (nach einem
     * APP_KEY-Wechsel wäre er trotz Datensatz unbenutzbar).
     */
    public function breakGlassUsable(): bool
    {
        $user = User::query()->where('is_local_admin', true)->first();

        try {
            return $user !== null && filled($user->totp_secret);
        } catch (DecryptException) {
            return false;
        }
    }

    /**
     * Letztes Backup höchstens 36 h alt und letzter Wiederherstellungstest
     * erfolgreich.
     */
    public function backupsHealthy(): bool
    {
        $run = BackupService::lastRun();
        $verify = BackupService::lastVerify();

        return $run !== null && $verify !== null && ($verify['ok'] ?? false)
            && now()->diffInHours($run['at'], true) < 36;
    }

    public function schedulerRuns(): bool
    {
        $last = Cache::get(self::HEARTBEAT_KEY);

        return $last !== null && now()->timestamp - (int) $last < 10 * 60;
    }
}
