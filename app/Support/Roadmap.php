<?php

namespace App\Support;

use App\Models\Calendar;
use App\Models\Page;
use App\Models\User;
use App\Services\BrevoNewsletter;
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
                'key' => 'kalender',
                'phase' => '1b',
                'title' => 'Nextcloud-Kalender eintragen',
                'detail' => 'GitHub-Secret NEXTCLOUD_ALLOWED_HOSTS setzen (Nextcloud-Domain), dann im Backend unter „Kalender“ die öffentlichen ICS-Links anlegen.',
                'done' => filled(config('services.nextcloud.allowed_hosts')) && Calendar::query()->where('active', true)->exists(),
            ],
            [
                'key' => 'brevo-newsletter',
                'phase' => '1b',
                'title' => 'Brevo für den Georgsboten verbinden',
                'detail' => 'In Brevo Liste und Double-Opt-In-Template anlegen; GitHub-Secrets BREVO_API_KEY, BREVO_NEWSLETTER_LIST_ID, BREVO_DOI_TEMPLATE_ID setzen.',
                'done' => app(BrevoNewsletter::class)->isConfigured(),
            ],
            [
                'key' => 'domain-bolivien',
                'phase' => '1b',
                'title' => 'Domain bolivienpartnerschaft.de aufschalten',
                'detail' => 'DNS auf das Webhosting zeigen lassen, in Plesk als Alias der Website anlegen und Let’s-Encrypt-Zertifikat ausstellen.',
                'done' => null,
            ],
            [
                'key' => 'spenden-bank',
                'phase' => '1b',
                'title' => 'Bankverbindung für Bolivien-Spenden eintragen',
                'detail' => 'Seite „bolivien-spenden“ im Backend: Platzhalter durch Kontoinhaber, IBAN und Verwendungszweck ersetzen.',
                'done' => ($spenden = Page::query()->where('slug', 'bolivien-spenden')->value('body')) !== null && ! str_contains($spenden, 'TODO'),
            ],
            [
                'key' => 'notfall-login',
                'phase' => '1b',
                'title' => 'Notfall-Login einrichten und testen',
                'detail' => 'Per SSH „php artisan cms:create-superadmin“ ausführen, TOTP in einer Authenticator-App speichern, Anmeldung unter /notfall-login einmal testen. Zugangsdaten offline verwahren.',
                'done' => User::query()->where('is_local_admin', true)->exists(),
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
                'key' => 'instagram-zugang',
                'phase' => '1d',
                'title' => 'Instagram-Zugang vorbereiten',
                'detail' => 'Instagram als Business- oder Creator-Konto mit einer Facebook-Seite verknüpfen und eine Meta-App anlegen (Berechtigung instagram_content_publish beantragen).',
                'done' => null,
            ],
        ];
    }

    public function schedulerRuns(): bool
    {
        $last = Cache::get(self::HEARTBEAT_KEY);

        return $last !== null && now()->timestamp - (int) $last < 10 * 60;
    }
}
