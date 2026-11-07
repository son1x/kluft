<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use App\Models\User;
use App\Support\Features;
use App\Support\MailEvents;
use App\Support\Mailversand;
use App\Support\PushEvents;
use App\Support\Spamverdacht;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListContactMessages extends ListRecords
{
    protected static string $resource = ContactMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Täglicher Sammelhinweis bei Spamverdacht (nachrichten:spamverdacht-melden), je Person wählbar.
            Action::make('benachrichtigung')
                ->label('Benachrichtigung')
                ->icon('heroicon-o-bell')
                ->color('gray')
                ->visible(fn () => ($user = Filament::auth()->user()) instanceof User && MailEvents::spamverdachtErlaubt($user))
                ->modalHeading('Benachrichtigung bei Spamverdacht')
                ->modalDescription('Nachrichten mit Spamverdacht werden nicht gemailt, sondern hier zurückgehalten und nach '.Spamverdacht::TAGE.' Tagen gelöscht. Damit nichts Echtes verloren geht, gibt es einmal am Morgen einen Sammelhinweis, wenn neue dazugekommen sind. Ohne Inhalt und ohne Namen.')
                ->fillForm(fn () => [
                    'mail' => $this->ich()->wantsMail(MailEvents::SPAMVERDACHT),
                    'push' => in_array(PushEvents::SPAMVERDACHT, $this->ich()->ntfy_events ?? [], true),
                ])
                ->schema(fn () => [
                    Toggle::make('mail')->label('Per Mail an '.$this->ich()->email),
                    Toggle::make('push')->label('Als Push-Nachricht aufs Handy')
                        ->disabled(fn () => ! $this->pushMoeglich())
                        ->helperText(fn () => $this->pushMoeglich() ? null : 'Erst unter „Mein Konto → Meine Push-Nachrichten“ einschalten.'),
                ])
                ->modalSubmitActionLabel('Speichern')
                ->action(function (array $data) {
                    $user = $this->ich();
                    abort_unless(MailEvents::spamverdachtErlaubt($user), 403);

                    $mail = array_values(array_diff($user->mail_events ?? MailEvents::STANDARD, [MailEvents::SPAMVERDACHT]));
                    if (! empty($data['mail'])) {
                        $mail[] = MailEvents::SPAMVERDACHT;
                    }
                    $werte = ['mail_events' => $mail];
                    if ($this->pushMoeglich()) {
                        $push = array_values(array_diff($user->ntfy_events ?? [], [PushEvents::SPAMVERDACHT]));
                        if (! empty($data['push'])) {
                            $push[] = PushEvents::SPAMVERDACHT;
                        }
                        $werte['ntfy_events'] = $push;
                    }
                    $user->forceFill($werte)->save();

                    Notification::make()->success()->title('Gespeichert')->send();
                }),
        ];
    }

    private function ich(): User
    {
        $user = Filament::auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function pushMoeglich(): bool
    {
        return Features::enabled(Features::PUSH) && filled($this->ich()->ntfy_topic);
    }

    public function getSubheading(): ?string
    {
        $user = Filament::auth()->user();
        $alle = $user instanceof User && $user->isVerwaltung();

        return ($alle ? 'Alle Nachrichten aus dem Kontaktformular und Fragen zu Veranstaltungen.' : 'Fragen zu den Veranstaltungen deiner Stufe bzw. deines Bereichs.')
            .(Mailversand::nurProtokoll()
                ? ' Achtung: Mails werden derzeit nur ins Server-Protokoll geschrieben, die zuständigen Personen bekommen sie nicht (Verwaltung → E-Mail-Versand). Bitte weiterleiten oder direkt antworten.'
                : ' Die zuständige Person bekommt jede auch per Mail.')
            .' Gelöscht wird nach '.ContactMessage::aufbewahrungMonate().' Monaten.'
            .$this->spamHinweis();
    }

    /** Zurückgehaltene Nachrichten (Spamverdacht) sind nicht gemailt worden. */
    private function spamHinweis(): string
    {
        $n = ContactMessageResource::getEloquentQuery()->where('status', 'spam')->count();

        return $n > 0
            ? ' '.$n.' '.($n === 1 ? 'Nachricht' : 'Nachrichten').' mit Spamverdacht wurden nicht gemailt (Filter „Status“ → Spamverdacht); gelöscht nach '.Spamverdacht::TAGE.' Tagen.'
            : '';
    }
}
