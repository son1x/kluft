<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Notifications\Channels\NtfyChannel;
use App\Notifications\Messages\NtfyMessage;
use App\Support\AuditLogger;
use App\Support\PushEvents;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * Push-Benachrichtigungen über ntfy – jede Person schaltet sie für sich
 * selbst frei und wählt, worüber sie informiert werden will.
 *
 * @property Schema $form
 */
class PushSettings extends Page
{
    protected string $view = 'filament.pages.push-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?string $navigationLabel = 'Meine Push-Nachrichten';

    protected static ?string $title = 'Push-Nachrichten aufs Handy';

    protected static ?string $slug = 'push';

    protected static ?int $navigationSort = 95;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->status === 'active';
    }

    public function mount(): void
    {
        $this->form->fill(['events' => $this->user()->ntfy_events ?? []]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                CheckboxList::make('events')
                    ->label('Worüber möchtest du informiert werden?')
                    ->options(PushEvents::availableFor($this->user()))
                    ->helperText('Du siehst nur, was zu deinen Rechten passt. Anmeldungen z. B. nur für Veranstaltungen, die du im Backend betreuen darfst.'),
            ]);
    }

    public function enable(): void
    {
        $user = $this->user();
        if (blank($user->ntfy_topic)) {
            $user->forceFill([
                'ntfy_topic' => self::newTopic(),
                'ntfy_events' => array_keys(PushEvents::availableFor($user)),
            ])->save();
            $this->form->fill(['events' => $user->ntfy_events]);
            AuditLogger::log('push.eingeschaltet', $user, request: request());
        }
    }

    public function save(): void
    {
        $user = $this->user();
        $events = array_values(array_intersect(
            (array) ($this->form->getState()['events'] ?? []),
            array_keys(PushEvents::availableFor($user)),
        ));
        $user->forceFill(['ntfy_events' => $events])->save();

        Notification::make()->success()->title('Gespeichert')->send();
    }

    public function sendTest(): void
    {
        $user = $this->user();
        if (blank($user->ntfy_topic)) {
            return;
        }

        $sent = NtfyChannel::publish((string) $user->ntfy_topic, new NtfyMessage(
            title: 'Test von '.config('dpsg.site.domain'),
            message: 'Wenn du das liest, kommen deine Push-Nachrichten an.',
            click: static::getUrl(),
            tags: ['white_check_mark'],
        ));

        $sent
            ? Notification::make()->success()->title('Testnachricht verschickt')->body('Sie sollte in wenigen Sekunden in der ntfy-App erscheinen.')->send()
            : Notification::make()->danger()->title('Testnachricht ging nicht raus')->body('Der Push-Dienst ist gerade nicht erreichbar. Bitte später noch einmal versuchen.')->send();
    }

    /**
     * Neues Thema, z. B. wenn das alte weitergegeben wurde. Das alte Abo in
     * der App bekommt danach nichts mehr.
     */
    public function regenerate(): void
    {
        $user = $this->user();
        $user->forceFill(['ntfy_topic' => self::newTopic()])->save();
        AuditLogger::log('push.neues_thema', $user, request: request());

        Notification::make()->warning()->title('Neues Thema erzeugt')->body('Bitte in der ntfy-App das alte Thema löschen und das neue abonnieren.')->send();
    }

    public function disable(): void
    {
        $user = $this->user();
        $user->forceFill(['ntfy_topic' => null, 'ntfy_events' => null])->save();
        $this->form->fill(['events' => []]);
        AuditLogger::log('push.ausgeschaltet', $user, request: request());

        Notification::make()->success()->title('Push-Nachrichten ausgeschaltet')->body('Das Thema in der ntfy-App kannst du löschen.')->send();
    }

    public function topic(): ?string
    {
        return $this->user()->ntfy_topic;
    }

    public function server(): string
    {
        return rtrim((string) config('services.ntfy.server'), '/');
    }

    private function user(): User
    {
        $user = Filament::auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private static function newTopic(): string
    {
        // Wirkt wie ein Passwort: wer das Thema kennt, liest mit.
        return 'dpsg-trier-'.Str::lower(Str::random(28));
    }
}
