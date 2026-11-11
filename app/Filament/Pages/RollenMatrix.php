<?php

namespace App\Filament\Pages;

use App\Models\Rolle;
use App\Models\User;
use App\Support\Rechte;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Rollen und Rechte (docs/rollen.md): Matrix aus Einzelrechten (Zeilen) und
 * Rollen (Spalten). Mitgelieferte Rollen sind feste Vorlagen; eigene Rollen
 * legen Admins an (meist als Kopie einer Vorlage) und haken Rechte per Klick
 * an. Vergeben werden Rollen wie gewohnt unter Benutzer.
 */
class RollenMatrix extends Page
{
    protected string $view = 'filament.pages.rollen-matrix';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = 'Verwaltung';

    protected static ?string $navigationLabel = 'Rollen und Rechte';

    protected static ?string $title = 'Rollen und Rechte';

    protected static ?string $slug = 'rollen-und-rechte';

    protected static ?int $navigationSort = 41;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isAdmin();
    }

    public function getSubheading(): ?string
    {
        return 'Welche Rolle was darf. Mitgelieferte Rollen sind feste Vorlagen; eigene Rollen legst du als Kopie einer Vorlage an und hakst Rechte an. Vergeben werden Rollen unter Benutzer.';
    }

    /**
     * Rechte nach Gruppen.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function gruppen(): array
    {
        return collect(Rechte::KATALOG)->groupBy('gruppe', preserveKeys: true)->map->all()->all();
    }

    /** @return array<string, array{name: string, geltung: string, rechte: list<string>}> */
    public function vorlagen(): array
    {
        return Rechte::vorlagen();
    }

    /** @return array<string, Rolle> */
    public function eigene(): array
    {
        return Rechte::eigene();
    }

    /** Recht einer eigenen Rolle an- oder abhaken. */
    public function umschalten(int $rolleId, string $recht): void
    {
        abort_unless(static::canAccess(), 403);
        $rolle = Rolle::query()->findOrFail($rolleId);
        abort_unless(isset(Rechte::KATALOG[$recht]), 422);
        if (! Rechte::passtZuGeltung($recht, $rolle->geltung)) {
            Notification::make()->title('Gilt nur für die ganze Website')
                ->body('„'.Rechte::KATALOG[$recht]['label'].'“ passt nicht zu einer Rolle je Bereich.')->warning()->send();

            return;
        }
        $rechte = (array) $rolle->rechte;
        $rolle->rechte = in_array($recht, $rechte, true) ? array_values(array_diff($rechte, [$recht])) : [...$rechte, $recht];
        $rolle->save();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('neu')->label('Neue Rolle')->icon('heroicon-o-plus')
                ->modalHeading('Neue Rolle')
                ->modalDescription('Am einfachsten als Kopie einer Vorlage; danach in der Matrix Rechte an- oder abhaken.')
                ->schema([
                    TextInput::make('name')->label('Name')->required()->maxLength(80)->placeholder('z. B. Redaktion Fachbereich, Lagerleitung'),
                    Select::make('vorlage')->label('Vorlage')->placeholder('Ohne (leer beginnen)')->native(false)
                        ->options(fn () => collect(Rechte::vorlagen())->map(fn (array $v) => $v['name'])->all())
                        ->live()
                        ->afterStateUpdated(fn ($state, $set) => $set('geltung', Rechte::vorlagen()[$state]['geltung'] ?? 'global')),
                    ToggleButtons::make('geltung')->label('Geltung')->options(Rolle::GELTUNG)->default('global')->required()->inline()->grouped()
                        ->helperText('„Je Bereich“: Bei der Vergabe wählst du die Stufe oder den Fachbereich, z. B. „Name – Spiritualität“.'),
                    Textarea::make('beschreibung')->label('Beschreibung (für „Meine Rollen“ und die Mail bei Vergabe)')->rows(2)->maxLength(300),
                ])
                ->action(function (array $data) {
                    $rolle = Rolle::query()->create([
                        'name' => trim($data['name']),
                        'beschreibung' => $data['beschreibung'] ?? null,
                        'geltung' => $data['geltung'],
                        'vorlage' => $data['vorlage'] ?? null,
                        'rechte' => Rechte::vorlagen()[$data['vorlage'] ?? '']['rechte'] ?? [],
                    ]);
                    Notification::make()->title('Rolle „'.$rolle->name.'“ angelegt')->success()->send();
                }),
        ];
    }

    public function bearbeitenAction(): Action
    {
        return Action::make('bearbeiten')->label('Bearbeiten')->icon('heroicon-m-pencil-square')->iconButton()->size('sm')
            ->modalHeading(fn (array $arguments) => 'Rolle „'.(Rolle::query()->find($arguments['rolle'] ?? 0)?->name ?? '').'“')
            ->fillForm(fn (array $arguments) => Rolle::query()->findOrFail($arguments['rolle'])->only(['name', 'beschreibung']))
            ->schema([
                TextInput::make('name')->label('Name')->required()->maxLength(80),
                Textarea::make('beschreibung')->label('Beschreibung')->rows(2)->maxLength(300),
            ])
            ->action(function (array $data, array $arguments) {
                abort_unless(static::canAccess(), 403);
                Rolle::query()->findOrFail($arguments['rolle'])->update(['name' => trim($data['name']), 'beschreibung' => $data['beschreibung'] ?? null]);
                Notification::make()->title('Gespeichert')->success()->send();
            });
    }

    public function loeschenAction(): Action
    {
        return Action::make('loeschen')->label('Löschen')->icon('heroicon-m-trash')->iconButton()->size('sm')->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments) => 'Rolle „'.(Rolle::query()->find($arguments['rolle'] ?? 0)?->name ?? '').'“ löschen?')
            ->modalDescription('Nur möglich, wenn niemand die Rolle hat.')
            ->action(function (array $arguments) {
                abort_unless(static::canAccess(), 403);
                $rolle = Rolle::query()->findOrFail($arguments['rolle']);
                if (($n = $rolle->zuweisungen()) > 0) {
                    Notification::make()->title('Noch vergeben')->body("{$n} Person(en) haben diese Rolle. Erst unter Benutzer entfernen.")->danger()->send();

                    return;
                }
                $rolle->delete();
                Notification::make()->title('Gelöscht')->success()->send();
            });
    }
}
