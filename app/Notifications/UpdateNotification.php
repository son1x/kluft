<?php

namespace App\Notifications;

use App\Filament\Pages\Updates;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mail an die Admins einer Installation: neues Kluft-Release verfügbar,
 * installiert oder fehlgeschlagen. Bewusst ohne Warteschlange, damit die
 * Mail auch kommt, wenn nach einem Update etwas hakt.
 */
class UpdateNotification extends Notification
{
    /**
     * @param  'verfuegbar'|'installiert'|'fehlgeschlagen'  $art
     * @param  list<string>  $aenderungen
     */
    public function __construct(
        public string $art,
        public string $version,
        public ?string $vorher = null,
        public array $aenderungen = [],
        public ?string $fehler = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $seite = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        $mail = (new MailMessage)->subject(match ($this->art) {
            'verfuegbar' => "Kluft {$this->version} ist verfügbar ({$seite})",
            'installiert' => "Kluft {$this->version} installiert ({$seite})",
            default => "Update auf Kluft {$this->version} fehlgeschlagen ({$seite})",
        });

        $mail->line(match ($this->art) {
            'verfuegbar' => "Für {$seite} gibt es ein Update von Kluft {$this->vorher} auf {$this->version}.",
            'installiert' => "{$seite} läuft jetzt mit Kluft {$this->version} (vorher {$this->vorher}). Vorher wurde ein Backup erstellt.",
            default => "Das Update von {$seite} auf Kluft {$this->version} hat nicht geklappt. Die Website läuft weiter mit Version {$this->vorher}.",
        });
        if ($this->fehler) {
            $mail->line('Grund: '.$this->fehler);
        }
        if ($this->aenderungen !== [] && $this->art !== 'fehlgeschlagen') {
            $mail->line('Neu in dieser Version:');
            foreach (array_slice($this->aenderungen, 0, 15) as $zeile) {
                $mail->line('• '.$zeile);
            }
        }

        return $mail->action($this->art === 'verfuegbar' ? 'Update ansehen' : 'Zum Update-Protokoll', Updates::getUrl(panel: 'admin'));
    }
}
