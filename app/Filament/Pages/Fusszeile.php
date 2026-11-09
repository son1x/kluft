<?php

namespace App\Filament\Pages;

use App\Models\Media;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Features;
use App\Support\Fusszeile as FusszeileDaten;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Verwaltung → Fußzeile: Kontaktangaben, zusätzliche Service-Links,
 * „DPSG im Netz“ und die dritte Spalte anpassen (App\Support\Fusszeile).
 * Ohne Änderung gilt der Standard der Instanz.
 *
 * @property Schema $form
 */
class Fusszeile extends Page
{
    protected string $view = 'filament.pages.fusszeile';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3BottomLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Verwaltung';

    protected static ?string $navigationLabel = 'Fußzeile';

    protected static ?string $title = 'Fußzeile der Website';

    protected static ?string $slug = 'fusszeile';

    protected static ?int $navigationSort = 46;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->isVerwaltung();
    }

    public function mount(): void
    {
        $g = FusszeileDaten::gespeichert();
        $this->form->fill([
            'kontakt' => $g['kontakt'],
            'service' => $g['service'],
            'netz_eigen' => $g['netz'] !== null,
            'netz' => $g['netz'] ?? FusszeileDaten::netzStandard(),
            'spalte3' => $g['spalte3'],
            'bild_media_id' => $g['bild_media_id'],
            'bild_link' => $g['bild_link'],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $standard = (array) config('dpsg.contact');
        $kontaktFeld = fn (string $key, string $label) => TextInput::make("kontakt.{$key}")->label($label)
            ->placeholder((string) ($standard[$key] ?? ''))->maxLength(150);
        $link = fn () => [
            TextInput::make('label')->label('Text')->required()->maxLength(80),
            TextInput::make('url')->label('Adresse')->required()->maxLength(500)
                ->regex('#^(https?://|/)#i')
                ->validationMessages(['regex' => 'Mit https:// beginnen oder mit / für eine Seite dieser Website.'])
                ->placeholder('https://… oder /seite'),
        ];

        return $schema->statePath('data')->components([
            Section::make('Erste Spalte: Kontakt')
                ->description('Leer lassen = Angabe der Instanz (grau im Feld). Gilt nur für die Fußzeile; Impressum, Kontaktseite und Mails nutzen weiter die Angaben der Instanz.')
                ->columns(2)
                ->components([
                    $kontaktFeld('name', 'Name'),
                    $kontaktFeld('office', 'Zusatz (fett, z. B. Büro)'),
                    $kontaktFeld('street', 'Straße'),
                    $kontaktFeld('city', 'PLZ und Ort'),
                    $kontaktFeld('phone', 'Telefon'),
                    $kontaktFeld('email', 'E-Mail')->email(),
                ]),
            Section::make('Zweite Spalte: Service')
                ->description('Kontakt, Meine Anmeldungen, Datenschutz-Einstellungen, Redaktions-Login und Systemstatus stehen immer da. Hier kommen eigene Links darunter, z. B. „Satzung“ oder „Spenden“.')
                ->components([
                    Repeater::make('service')->hiddenLabel()->schema($link())->columns(2)
                        ->addActionLabel('Link hinzufügen')->reorderable()->maxItems(10)->defaultItems(0),
                ]),
            Section::make('„DPSG im Netz“')
                ->description('Links zu anderen DPSG-Seiten. Ohne eigene Liste: dpsg.de und die übergeordnete Gliederung der Instanz.')
                ->components([
                    Toggle::make('netz_eigen')->label('Eigene Liste verwenden')->live(),
                    Repeater::make('netz')->hiddenLabel()->schema($link())->columns(2)
                        ->addActionLabel('Link hinzufügen')->reorderable()->maxItems(10)
                        ->visible(fn (Get $get) => (bool) $get('netz_eigen')),
                ]),
            Section::make('Dritte Spalte')
                ->description('Ohne Newsletter-Kasten steht „DPSG im Netz“ oben in der dritten Spalte, darunter das Gewählte. Auf dem Handy entfallen Zelte und Bild.')
                ->components([
                    Radio::make('spalte3')->hiddenLabel()
                        ->options(fn () => Features::newsletter() ? FusszeileDaten::SPALTE3 : array_diff_key(FusszeileDaten::SPALTE3, ['newsletter' => true]))
                        ->default('standard')->required()->live(),
                    Select::make('bild_media_id')->label('Bild aus der Mediathek')
                        ->options(fn () => Media::query()->orderByDesc('created_at')->limit(200)->pluck('alt_text', 'id')->all())
                        ->searchable()
                        ->required(fn (Get $get) => $get('spalte3') === 'bild')
                        ->visible(fn (Get $get) => $get('spalte3') === 'bild')
                        ->helperText('Am besten ein Bild mit transparentem oder hellem Hintergrund, etwa 600 × 320 Pixel. Es steht wie die Zelte unten auf dem blauen Balken.'),
                    TextInput::make('bild_link')->label('Bild verlinken (optional)')->maxLength(500)
                        ->regex('#^(https?://|/)#i')
                        ->validationMessages(['regex' => 'Mit https:// beginnen oder mit / für eine Seite dieser Website.'])
                        ->visible(fn (Get $get) => $get('spalte3') === 'bild'),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $daten = $this->form->getState();
        if (empty($daten['netz_eigen'])) {
            $daten['netz'] = null;
        }
        $user = Filament::auth()->user();
        FusszeileDaten::speichern($daten, $user?->getKey());
        AuditLogger::log('fusszeile.gespeichert', $user instanceof User ? $user : null, request: request());
        Notification::make()->success()->title('Gespeichert')->body('Die Fußzeile ist ab dem nächsten Seitenaufruf geändert.')->send();
        $this->mount();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ansehen')->label('Auf der Website ansehen')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                ->url(url('/impressum'), shouldOpenInNewTab: true),
            Action::make('zuruecksetzen')->label('Auf Standard zurücksetzen')->icon('heroicon-o-arrow-uturn-left')->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Alle Anpassungen der Fußzeile werden gelöscht; danach gelten wieder die Angaben der Instanz.')
                ->action(function () {
                    abort_unless(static::canAccess(), 403);
                    FusszeileDaten::zuruecksetzen();
                    $user = Filament::auth()->user();
                    AuditLogger::log('fusszeile.zurueckgesetzt', $user instanceof User ? $user : null, request: request());
                    Notification::make()->success()->title('Standard wiederhergestellt')->send();
                    $this->mount();
                }),
        ];
    }
}
