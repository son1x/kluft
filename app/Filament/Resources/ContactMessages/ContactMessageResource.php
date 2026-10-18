<?php

namespace App\Filament\Resources\ContactMessages;

use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Models\ContactMessage;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Features;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Nachrichten-Eingang (docs/nachrichten.md): alle Nachrichten aus dem
 * Kontaktformular und Fragen zu Veranstaltungen an einer Stelle, für
 * Verwaltung und Admins. Antworten gehen per Mail an die Absender*in.
 */
class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    protected static string|UnitEnum|null $navigationGroup = 'Verband & Kontakt';

    protected static ?string $slug = 'nachrichten';

    protected static ?string $navigationLabel = 'Nachrichten';

    protected static ?string $modelLabel = 'Nachricht';

    protected static ?string $pluralModelLabel = 'Nachrichten';

    protected static ?int $navigationSort = 25;

    public static function canViewAny(): bool
    {
        $user = Filament::auth()->user();

        return Features::enabled(Features::NACHRICHTEN) && $user instanceof User && $user->isVerwaltung();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDeleteAny(): bool
    {
        return static::canViewAny();
    }

    public static function getNavigationBadge(): ?string
    {
        $n = static::canViewAny() ? ContactMessage::query()->where('status', 'neu')->count() : 0;

        return $n > 0 ? (string) $n : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Keine Nachrichten')
            ->emptyStateDescription('Hier erscheinen Nachrichten aus dem Kontaktformular und Fragen zu Veranstaltungen.')
            ->recordAction('ansehen')
            ->columns([
                TextColumn::make('created_at')->label('Eingang')->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('thema')->label('Thema')->wrap()
                    ->description(fn (ContactMessage $record) => ContactMessage::ARTEN[$record->art] ?? null),
                TextColumn::make('name')->label('Von')->searchable(['name', 'email'])
                    ->description(fn (ContactMessage $record) => $record->email),
                TextColumn::make('betreff')->label('Betreff / Nachricht')->wrap()
                    ->state(fn (ContactMessage $record) => $record->betreff ?: str($record->nachricht)->limit(60)->toString())
                    ->description(fn (ContactMessage $record) => $record->betreff ? str($record->nachricht)->squish()->limit(90)->toString() : null),
                TextColumn::make('empfaenger')->label('Zugestellt an')->wrap()->toggleable()
                    ->state(fn (ContactMessage $record) => implode(', ', $record->empfaenger)),
                IconColumn::make('zugestellt')->label('Mail')->boolean()
                    ->tooltip(fn (ContactMessage $record) => $record->zugestellt ? 'Per Mail zugestellt' : 'Mail fehlgeschlagen – bitte selbst weitergeben'),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => ContactMessage::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'neu' => 'danger', 'in_bearbeitung' => 'warning', default => 'success',
                    })
                    ->description(fn (ContactMessage $record) => $record->bearbeitet_am
                        ? $record->bearbeitet_am->format('d.m.Y').($record->bearbeiter ? ', '.$record->bearbeiter->name : '') : null),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(ContactMessage::STATUSES)->multiple()
                    ->default(['neu', 'in_bearbeitung']),
                SelectFilter::make('art')->label('Herkunft')->options(ContactMessage::ARTEN),
                SelectFilter::make('zugestellt')->label('Mail')->options(['0' => 'fehlgeschlagen', '1' => 'zugestellt']),
            ])
            ->recordActions([
                Action::make('ansehen')
                    ->label('Ansehen')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (ContactMessage $record) => $record->betreff ?: $record->thema)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Schließen')
                    ->modalContent(fn (ContactMessage $record) => new HtmlString(view('filament.contact-message', ['nachricht' => $record])->render()))
                    ->extraModalFooterActions(fn (ContactMessage $record) => [
                        Action::make('antworten')->label('Per Mail antworten')->icon('heroicon-o-envelope')
                            ->url(self::mailto($record)),
                    ]),
                ActionGroup::make([
                    Action::make('inBearbeitung')->label('In Bearbeitung')->icon('heroicon-o-clock')
                        ->visible(fn (ContactMessage $record) => $record->status !== 'in_bearbeitung')
                        ->action(fn (ContactMessage $record) => self::status($record, 'in_bearbeitung')),
                    Action::make('erledigt')->label('Erledigt')->icon('heroicon-o-check-circle')->color('success')
                        ->visible(fn (ContactMessage $record) => $record->status !== 'erledigt')
                        ->schema([Textarea::make('notiz')->label('Notiz (optional, z. B. „beantwortet von …“)')->maxLength(500)->rows(2)])
                        ->fillForm(fn (ContactMessage $record) => ['notiz' => $record->notiz])
                        ->action(fn (ContactMessage $record, array $data) => self::status($record, 'erledigt', $data['notiz'] ?? null)),
                    Action::make('wiederOeffnen')->label('Wieder öffnen')->icon('heroicon-o-arrow-uturn-left')
                        ->visible(fn (ContactMessage $record) => $record->status === 'erledigt')
                        ->action(fn (ContactMessage $record) => self::status($record, 'neu')),
                    Action::make('antworten')->label('Per Mail antworten')->icon('heroicon-o-envelope')
                        ->url(fn (ContactMessage $record) => self::mailto($record)),
                    DeleteAction::make()->after(fn (ContactMessage $record) => AuditLogger::log('nachricht.geloescht', Filament::auth()->user(), ContactMessage::class, $record->id, request())),
                ]),
            ])
            ->toolbarActions([
                BulkAction::make('erledigtAlle')->label('Als erledigt markieren')->icon('heroicon-o-check-circle')
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records) {
                        foreach ($records as $record) {
                            if ($record instanceof ContactMessage) {
                                self::status($record, 'erledigt');
                            }
                        }
                    }),
                DeleteBulkAction::make(),
            ]);
    }

    public static function status(ContactMessage $record, string $status, ?string $notiz = null): void
    {
        abort_unless(static::canViewAny() && isset(ContactMessage::STATUSES[$status]), 403);
        $record->fill([
            'status' => $status,
            'bearbeitet_von' => Filament::auth()->id(),
            'bearbeitet_am' => now(),
        ] + ($notiz !== null ? ['notiz' => trim($notiz) ?: null] : []))->save();
        AuditLogger::log('nachricht.'.$status, Filament::auth()->user(), ContactMessage::class, $record->id, request());
    }

    private static function mailto(ContactMessage $record): string
    {
        return 'mailto:'.rawurlencode($record->email).'?subject='.rawurlencode('Re: '.($record->betreff ?: $record->thema));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactMessages::route('/'),
        ];
    }
}
