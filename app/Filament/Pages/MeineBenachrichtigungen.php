<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\Features;
use App\Support\MailEvents;
use App\Support\PushEvents;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Mein Konto → Meine Benachrichtigungen: wählbare Hinweise per Mail
 * (App\Support\MailEvents, users.mail_events) und der Stand der
 * Push-Nachrichten. Die Einrichtung von Push (ntfy) bleibt auf „Meine
 * Push-Nachrichten“; die Mail-Auswahl gab es vorher nur im
 * Nachrichten-Eingang.
 *
 * @property Schema $form
 */
class MeineBenachrichtigungen extends Page
{
    protected string $view = 'filament.pages.meine-benachrichtigungen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected static string|UnitEnum|null $navigationGroup = 'Mein Konto';

    protected static ?string $navigationLabel = 'Meine Benachrichtigungen';

    protected static ?string $title = 'Meine Benachrichtigungen';

    protected static ?string $slug = 'benachrichtigungen';

    protected static ?int $navigationSort = 5;

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** Sichtbar, wenn es etwas zu wählen gibt: Hinweise per Mail oder Push. */
    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isActive()
            && (MailEvents::availableFor($user) !== [] || Features::enabled(Features::PUSH));
    }

    public function mount(): void
    {
        $user = $this->user();
        $this->form->fill(['mail' => array_values(array_intersect($user->mail_events ?? MailEvents::STANDARD, array_keys(MailEvents::availableFor($user))))]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                CheckboxList::make('mail')
                    ->label('Per Mail an '.$this->user()->email)
                    ->options(fn () => MailEvents::availableFor($this->user()))
                    ->helperText('Du siehst nur, was zu deinen Rollen passt. Mails zu deinem Konto (z. B. Anmeldecodes, neue Rechte) bekommst du immer.'),
            ]);
    }

    /** Nur die hier wählbaren Hinweise ändern; andere Einträge bleiben, wie sie sind. */
    public function speichern(): void
    {
        $user = $this->user();
        $waehlbar = array_keys(MailEvents::availableFor($user));
        $gewaehlt = array_values(array_intersect((array) ($this->form->getState()['mail'] ?? []), $waehlbar));
        $uebrige = array_values(array_diff($user->mail_events ?? MailEvents::STANDARD, $waehlbar));

        $user->forceFill(['mail_events' => [...$uebrige, ...$gewaehlt]])->save();
        Notification::make()->success()->title('Gespeichert')->send();
    }

    public function mailWaehlbar(): bool
    {
        return MailEvents::availableFor($this->user()) !== [];
    }

    public function pushAn(): bool
    {
        return Features::enabled(Features::PUSH);
    }

    public function pushEingerichtet(): bool
    {
        return filled($this->user()->ntfy_topic);
    }

    /** @return list<string> Beschriftungen der gewählten Push-Hinweise */
    public function pushHinweise(): array
    {
        $erlaubt = PushEvents::availableFor($this->user());

        return array_values(array_map(fn (string $e) => $erlaubt[$e], array_filter($this->user()->ntfy_events ?? [], fn (string $e) => isset($erlaubt[$e]))));
    }

    public function user(): User
    {
        $user = Filament::auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
