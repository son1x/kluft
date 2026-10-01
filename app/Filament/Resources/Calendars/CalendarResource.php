<?php

namespace App\Filament\Resources\Calendars;

use App\Filament\Resources\Calendars\Pages\CreateCalendar;
use App\Filament\Resources\Calendars\Pages\EditCalendar;
use App\Filament\Resources\Calendars\Pages\ListCalendars;
use App\Models\Calendar;
use App\Services\CalendarSync;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CalendarResource extends Resource
{
    protected static ?string $model = Calendar::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $modelLabel = 'Kalender';

    protected static ?string $pluralModelLabel = 'Kalender';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->components([
                    TextInput::make('label')->label('Bezeichnung')->required()->maxLength(100),
                    ColorPicker::make('color')->label('Farbe')->default('#003056')->required()->regex('/^#[0-9a-fA-F]{6}$/'),
                    TextInput::make('ics_url')
                        ->label('ICS-Freigabelink (Nextcloud)')
                        ->url()
                        // Der gespeicherte Link wird nie an den Browser geschickt
                        // (wirkt wie ein Zugangsschlüssel); leer lassen = unverändert.
                        ->required(fn (?Calendar $record) => $record === null)
                        ->dehydrated(fn (?string $state) => filled($state))
                        ->password()
                        ->revealable()
                        ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail) {
                            try {
                                CalendarSync::assertAllowedUrl((string) $value);
                            } catch (\RuntimeException $e) {
                                $fail($e->getMessage());
                            }
                        })
                        ->helperText(fn (?Calendar $record) => ($record ? 'Leer lassen, um den gespeicherten Link zu behalten. ' : '')
                            .'In Nextcloud: Kalender → Teilen → Öffentlicher Link → „Abonnement-Link kopieren". Erlaubte Hosts: '
                            .(implode(', ', CalendarSync::allowedHosts()) ?: 'noch keine (NEXTCLOUD_ALLOWED_HOSTS)'))
                        ->columnSpanFull(),
                    Select::make('stage')
                        ->label('Stufe (optional)')
                        ->options(collect(config('dpsg.stages'))->map(fn (array $s) => $s['label'])->all())
                        ->native(false),
                    Toggle::make('active')->label('Aktiv')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ColorColumn::make('color')->label(''),
                TextColumn::make('label')->label('Kalender')->searchable(),
                TextColumn::make('stage')->label('Stufe')->formatStateUsing(fn (?string $state) => $state ? config("dpsg.stages.{$state}.label") : null),
                TextColumn::make('events_count')->label('Termine')->counts('events'),
                TextColumn::make('last_synced_at')->label('Zuletzt abgerufen')->dateTime('d.m.Y H:i')->placeholder('noch nie'),
                TextColumn::make('last_error')->label('Letzter Fehler')->color('danger')->wrap()->placeholder('–'),
                IconColumn::make('active')->label('Aktiv')->boolean(),
            ])
            ->recordActions([
                Action::make('sync')
                    ->label('Jetzt abrufen')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->action(function (Calendar $record, CalendarSync $sync) {
                        $sync->sync($record)
                            ? Notification::make()->title('Kalender abgerufen')->success()->send()
                            : Notification::make()->title('Abruf fehlgeschlagen')->body($record->last_error)->danger()->send();
                    }),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCalendars::route('/'),
            'create' => CreateCalendar::route('/create'),
            'edit' => EditCalendar::route('/{record}/edit'),
        ];
    }
}
