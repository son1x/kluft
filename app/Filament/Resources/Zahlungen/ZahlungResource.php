<?php

namespace App\Filament\Resources\Zahlungen;

use App\Filament\Resources\Beitraege\BeitragResource;
use App\Filament\Resources\Zahlungen\Pages\ListZahlungen;
use App\Models\Zahlung;
use App\Support\AuditLogger;
use App\Support\Zahlungen;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Baustein „Zahlungen“ (docs/zahlungen.md): Forderungen aller Module mit
 * Stand, Zahlungseingang, Erstattung und Storno. Angelegt werden sie von
 * den Modulen oder oben über „Neue Forderung“.
 */
class ZahlungResource extends Resource
{
    protected static ?string $model = Zahlung::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Kasse';

    protected static ?string $slug = 'kasse/zahlungen';

    protected static ?string $navigationLabel = 'Zahlungen';

    protected static ?string $modelLabel = 'Zahlung';

    protected static ?string $pluralModelLabel = 'Zahlungen';

    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return Zahlungen::zugriff(Filament::auth()->user());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $n = Zahlung::query()->offen()->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function table(Table $table): Table
    {
        $euro = fn (?int $state) => Zahlungen::euro($state);
        $user = fn () => Filament::auth()->user();

        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Noch keine Zahlungen')
            ->emptyStateDescription('Zahlungen legen die Module an (z. B. Sammelbestellung). Oben „Neue Forderung“ schickt eine Zahlungsaufforderung von Hand.')
            ->columns([
                TextColumn::make('referenz')->label('Verwendungszweck')->searchable()->copyable()->fontFamily('mono')
                    ->description(fn (Zahlung $record) => $record->zweck),
                TextColumn::make('betrag_cent')->label('Betrag')->formatStateUsing($euro)->alignEnd()
                    ->summarize(Sum::make()->label('Summe')->formatStateUsing(fn ($state) => Zahlungen::euro((int) $state))),
                TextColumn::make('bezahlt_cent')->label('Eingegangen')->formatStateUsing($euro)->placeholder('–')->alignEnd()
                    ->description(fn (Zahlung $record) => $record->bezahlt_am?->format('d.m.Y'))
                    ->summarize(Sum::make()->label('Eingegangen')->formatStateUsing(fn ($state) => Zahlungen::euro((int) $state))),
                TextColumn::make('erstattet_cent')->label('Erstattet')->formatStateUsing(fn (int $state) => $state > 0 ? Zahlungen::euro($state) : '–')->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => Zahlung::STATUS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'bezahlt' => 'success', 'teilweise' => 'warning', 'erstattet', 'storniert' => 'gray', default => 'danger',
                    }),
                TextColumn::make('faellig_am')->label('Fällig')->date('d.m.Y')->placeholder('–')->sortable(),
                TextColumn::make('aufgefordert_am')->label('Aufgefordert')->dateTime('d.m.Y H:i')->placeholder('–')->toggleable(),
                TextColumn::make('zahlbar_type')->label('Aus')->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : 'von Hand')
                    ->placeholder('von Hand')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('notiz')->label('Notiz')->placeholder('–')->wrap()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(Zahlung::STATUS),
                SelectFilter::make('stand')->label('Offen')
                    ->options(['offen' => 'offen oder teilweise'])
                    ->query(fn (Builder $query, array $data) => ($data['value'] ?? null) === 'offen' ? $query->whereIn('status', ['offen', 'teilweise']) : $query),
            ])
            ->recordActions([
                Action::make('eingang')
                    ->label('Eingang')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Zahlung $record) => in_array($record->status, ['offen', 'teilweise'], true))
                    ->modalHeading(fn (Zahlung $record) => 'Zahlungseingang – '.$record->referenz)
                    ->fillForm(fn (Zahlung $record) => ['betrag' => BeitragResource::alsEuro($record->offenCent()), 'am' => now()])
                    ->schema([
                        TextInput::make('betrag')->label('Eingegangen')->suffix('€')->required()->rules(BeitragResource::EURO)->inputMode('decimal'),
                        DatePicker::make('am')->label('Eingang am')->required(),
                        TextInput::make('notiz')->label('Notiz')->maxLength(500),
                    ])
                    ->action(function (Zahlung $record, array $data) use ($user) {
                        abort_unless(static::canViewAny(), 403);
                        $record->eingang((int) BeitragResource::alsCent($data['betrag']), $data['am'], Filament::auth()->id(), $data['notiz'] ?: null);
                        AuditLogger::log('zahlung.eingang', $user(), Zahlung::class, $record->id, request());
                        Notification::make()->title('Gespeichert: '.(Zahlung::STATUS[$record->status] ?? $record->status))->success()->send();
                    }),
                Action::make('erstattung')
                    ->label('Erstattung')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn (Zahlung $record) => (int) $record->bezahlt_cent > $record->erstattet_cent)
                    ->fillForm(fn (Zahlung $record) => ['betrag' => BeitragResource::alsEuro((int) $record->bezahlt_cent - $record->erstattet_cent), 'am' => now()])
                    ->schema([
                        TextInput::make('betrag')->label('Erstattet')->suffix('€')->required()->rules(BeitragResource::EURO)->inputMode('decimal'),
                        DatePicker::make('am')->label('Erstattet am')->required(),
                        TextInput::make('notiz')->label('Notiz')->maxLength(500),
                    ])
                    ->action(function (Zahlung $record, array $data) use ($user) {
                        abort_unless(static::canViewAny(), 403);
                        $record->erstattung((int) BeitragResource::alsCent($data['betrag']), $data['am'], Filament::auth()->id(), $data['notiz'] ?: null);
                        AuditLogger::log('zahlung.erstattung', $user(), Zahlung::class, $record->id, request());
                    }),
                Action::make('stornieren')
                    ->label('Stornieren')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Zahlung $record) => $record->status === 'offen')
                    ->requiresConfirmation()
                    ->modalDescription('Die Forderung entfällt. Sie bleibt als „storniert“ in der Liste.')
                    ->schema([TextInput::make('notiz')->label('Grund')->maxLength(500)])
                    ->action(function (Zahlung $record, array $data) use ($user) {
                        abort_unless(static::canViewAny(), 403);
                        $record->stornieren(Filament::auth()->id(), $data['notiz'] ?: null);
                        AuditLogger::log('zahlung.storniert', $user(), Zahlung::class, $record->id, request());
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('bezahlt')
                    ->label('Als bezahlt markieren')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->schema([DatePicker::make('am')->label('Eingang am')->default(now())->required()])
                    ->modalDescription('Bei allen ausgewählten offenen Zahlungen wird der offene Betrag als eingegangen eingetragen.')
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records, array $data) use ($user) {
                        abort_unless(static::canViewAny(), 403);
                        $n = 0;
                        foreach ($records as $record) {
                            if ($record instanceof Zahlung && $record->offenCent() > 0) {
                                $record->eingang($record->offenCent(), $data['am'], Filament::auth()->id());
                                $n++;
                            }
                        }
                        AuditLogger::log('zahlung.eingang_sammel:'.$n, $user(), request: request());
                        Notification::make()->title("{$n} Zahlungen als bezahlt markiert.")->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListZahlungen::route('/')];
    }
}
