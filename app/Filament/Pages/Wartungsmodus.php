<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Wartung;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Verwaltung → Wartungsmodus (App\Support\Wartung): Website für
 * Besucher*innen vorübergehend schließen, z. B. beim Umzug. Nur Admins.
 *
 * @property Schema $form
 */
class Wartungsmodus extends Page
{
    protected string $view = 'filament.pages.wartungsmodus';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = 'Verwaltung';

    protected static ?string $navigationLabel = 'Wartungsmodus';

    protected static ?string $title = 'Wartungsmodus';

    protected static ?string $slug = 'wartungsmodus';

    protected static ?int $navigationSort = 47;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->isAdmin();
    }

    public static function getNavigationBadge(): ?string
    {
        return Wartung::aktiv() ? 'an' : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function mount(): void
    {
        $s = Wartung::stand();
        $this->form->fill([
            'text' => $s['text'] !== Wartung::TEXT ? $s['text'] : null,
            'bis' => $s['bis']?->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
            'nur_admins' => $s['nur_admins'],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Wartungsseite')->components([
                Textarea::make('text')->label('Text für Besucher*innen')->rows(3)->maxLength(500)
                    ->placeholder(Wartung::TEXT)
                    ->helperText('Leer: der Standardtext. Z. B. „Wir ziehen gerade auf einen neuen Server um. Ab etwa 14 Uhr sind wir wieder da.“'),
                DateTimePicker::make('bis')->label('Voraussichtlich wieder da (optional)')->seconds(false)->native(false)
                    ->displayFormat('d.m.Y H:i')->minDate(now()->startOfDay())
                    ->helperText('Steht auf der Wartungsseite. Der Modus endet dadurch nicht von selbst.'),
                Toggle::make('nur_admins')->label('Backend nur für Admins (Redaktionsstopp)')
                    ->helperText('Alle anderen Angemeldeten sehen dann auch im Backend nur die Wartungsseite, z. B. während die Datenbank kopiert wird.'),
            ]),
        ]);
    }

    public function einschalten(): void
    {
        abort_unless(static::canAccess(), 403);
        $d = $this->form->getState();
        $user = Filament::auth()->user();
        Wartung::an($d['text'] ?? null, filled($d['bis'] ?? null) ? Carbon::parse((string) $d['bis'], config('app.timezone')) : null, (bool) ($d['nur_admins'] ?? false), $user?->getKey());
        AuditLogger::log('wartung.an', $user instanceof User ? $user : null, request: request());
        Notification::make()->warning()->title('Wartungsmodus ist an')->body('Besucher*innen sehen jetzt die Wartungsseite. Du siehst die Website weiter, solange du angemeldet bist.')->send();
    }

    public function ausschalten(): void
    {
        abort_unless(static::canAccess(), 403);
        $user = Filament::auth()->user();
        Wartung::aus($user?->getKey());
        AuditLogger::log('wartung.aus', $user instanceof User ? $user : null, request: request());
        Notification::make()->success()->title('Wartungsmodus ist aus')->body('Die Website ist wieder für alle erreichbar.')->send();
    }

    /** @return array{aktiv: bool, text: string, bis: ?Carbon, nur_admins: bool, seit: ?Carbon} */
    public function stand(): array
    {
        return Wartung::stand();
    }
}
