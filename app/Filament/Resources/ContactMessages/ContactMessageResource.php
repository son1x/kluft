<?php

namespace App\Filament\Resources\ContactMessages;

use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Models\ContactMessage;
use App\Models\ContactTopic;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Features;
use App\Support\Mailversand;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Throwable;
use UnitEnum;

/**
 * Nachrichten-Eingang (docs/nachrichten.md): alle Nachrichten aus dem
 * Kontaktformular und Fragen zu Veranstaltungen an einer Stelle, für
 * Verwaltung und Admins; Stufen- und Sektionsadmins sehen gebündelt die
 * Fragen zu ihren Veranstaltungen. Antworten gehen per Mail an die Absender*in.
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

    /**
     * Verwaltung und Admins sehen alles; Stufen- und Sektionsadmins die
     * Fragen zu Veranstaltungen, die sie verwalten (gebündelt über alle).
     */
    public static function canViewAny(): bool
    {
        $user = self::nutzer();

        return Features::enabled(Features::NACHRICHTEN) && $user !== null
            && ($user->isVerwaltung() || $user->hasRole('stufenadmin') || $user->hasRole('sektionsadmin'));
    }

    public static function darf(ContactMessage $record): bool
    {
        $user = self::nutzer();
        if (! static::canViewAny() || $user === null) {
            return false;
        }

        return $user->isVerwaltung() || ($record->event !== null && $user->can('manageParticipants', $record->event));
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = self::nutzer();
        if ($user !== null && ! $user->isVerwaltung()) {
            $stufen = $user->scopeKeysFor('stufenadmin');
            $bereiche = $user->scopeKeysFor('sektionsadmin');
            $query->where('art', 'veranstaltung')
                ->whereHas('event', fn (Builder $e) => $e->where(fn (Builder $w) => $w->whereIn('stage', $stufen ?: [''])->orWhereIn('section', $bereiche ?: [''])));
        }

        return $query;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof ContactMessage && self::darf($record);
    }

    public static function canDelete(Model $record): bool
    {
        return $record instanceof ContactMessage && self::darf($record);
    }

    public static function canDeleteAny(): bool
    {
        return static::canViewAny();
    }

    public static function getNavigationBadge(): ?string
    {
        $n = static::canViewAny() ? static::getEloquentQuery()->where('status', 'neu')->count() : 0;

        return $n > 0 ? (string) $n : null;
    }

    private static function nutzer(): ?User
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Keine Nachrichten')
            ->emptyStateDescription('Hier erscheinen Nachrichten aus dem Kontaktformular und Fragen zu Veranstaltungen.')
            ->recordAction('ansehen')
            ->columns(self::spalten())
            ->filters([
                SelectFilter::make('status')->label('Status')->options(ContactMessage::STATUSES)->multiple()
                    ->default(['neu', 'in_bearbeitung']),
                SelectFilter::make('art')->label('Herkunft')->options(ContactMessage::ARTEN),
                SelectFilter::make('zugestellt')->label('Mail')->options(['0' => 'fehlgeschlagen', '1' => 'zugestellt']),
            ])
            ->recordActions(self::aktionen())
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

    /**
     * Spalten der Nachrichtenliste (auch für „Fragen“ bei der Veranstaltung).
     *
     * @return array<int, mixed>
     */
    public static function spalten(bool $mitThema = true): array
    {
        return [
            TextColumn::make('created_at')->label('Eingang')->dateTime('d.m.Y H:i')->sortable(),
            ...$mitThema ? [TextColumn::make('thema')->label('Thema')->wrap()
                ->description(fn (ContactMessage $record) => ContactMessage::ARTEN[$record->art] ?? null)] : [],
            TextColumn::make('name')->label('Von')->searchable(['name', 'email'])
                ->description(fn (ContactMessage $record) => $record->email),
            TextColumn::make('betreff')->label('Betreff / Nachricht')->wrap()
                ->state(fn (ContactMessage $record) => $record->betreff ?: str($record->nachricht)->limit(60)->toString())
                ->description(fn (ContactMessage $record) => $record->betreff ? str($record->nachricht)->squish()->limit(90)->toString() : null),
            TextColumn::make('empfaenger')->label('Zugestellt an')->wrap()->toggleable()
                ->state(fn (ContactMessage $record) => implode(', ', $record->empfaenger)),
            IconColumn::make('zugestellt')->label('Mail')->boolean()
                // Mailer „log“: nicht verschickt, aber auch kein Fehler beim Versand.
                ->icon(fn (ContactMessage $record) => $record->letztesErgebnis() === 'log' ? 'heroicon-o-exclamation-triangle' : null)
                ->color(fn (ContactMessage $record) => $record->letztesErgebnis() === 'log' ? 'warning' : null)
                ->tooltip(fn (ContactMessage $record) => match (true) {
                    $record->letztesErgebnis() === 'log' => 'Nur ins Server-Protokoll geschrieben, nicht verschickt – bitte weiterleiten, sobald Mails wieder rausgehen',
                    $record->zugestellt => 'Per Mail zugestellt',
                    default => 'Mail fehlgeschlagen – bitte erneut senden oder weiterleiten',
                }),
            TextColumn::make('status')->label('Status')->badge()
                ->formatStateUsing(fn (string $state) => ContactMessage::STATUSES[$state] ?? $state)
                ->color(fn (string $state) => match ($state) {
                    'neu' => 'danger', 'in_bearbeitung' => 'warning', 'spam' => 'gray', default => 'success',
                })
                ->description(fn (ContactMessage $record) => $record->bearbeitet_am
                    ? $record->bearbeitet_am->format('d.m.Y').($record->bearbeiter ? ', '.$record->bearbeiter->name : '') : null),
        ];
    }

    /**
     * Aktionen je Nachricht (auch für „Fragen“ bei der Veranstaltung).
     *
     * @return array<int, mixed>
     */
    public static function aktionen(): array
    {
        return [
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
            // Spamverdacht (docs/nachrichten.md): doch zustellen.
            Action::make('keinSpam')
                ->label('Kein Spam – zustellen')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn (ContactMessage $record) => $record->status === 'spam')
                ->requiresConfirmation()
                ->modalDescription(fn (ContactMessage $record) => 'Die Nachricht geht jetzt per Mail an: '.implode(', ', $record->empfaenger).'.')
                ->action(fn (ContactMessage $record) => self::keinSpam($record)),
            ActionGroup::make([
                // Falls etwas schiefging (Mail nur protokolliert, fehlgeschlagen, falsche Person).
                Action::make('erneutSenden')->label('Erneut senden')->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->modalHeading('Erneut senden')
                    ->modalDescription(fn (ContactMessage $record) => 'Die Nachricht geht noch einmal per Mail an: '.implode(', ', $record->empfaenger).'. Antworten gehen direkt an die Absender*in.'
                        .(Mailversand::nurProtokoll() ? ' Achtung: Mails werden derzeit nur ins Server-Protokoll geschrieben (Verwaltung → E-Mail-Versand).' : ''))
                    ->action(fn (ContactMessage $record) => self::senden($record, 'erneut', $record->empfaenger)),
                Action::make('weiterleiten')->label('Weiterleiten')->icon('heroicon-o-arrow-uturn-right')
                    ->modalHeading('Weiterleiten')
                    ->modalDescription(fn () => 'Die Nachricht geht per Mail an die angegebene Adresse; Antworten gehen direkt an die Absender*in.'
                        .(Mailversand::nurProtokoll() ? ' Achtung: Mails werden derzeit nur ins Server-Protokoll geschrieben (Verwaltung → E-Mail-Versand).' : ''))
                    ->schema([
                        TextInput::make('an')->label('An')->email()->required()->maxLength(254)
                            ->datalist(fn () => self::adressVorschlaege())
                            ->helperText('z. B. die Ansprechperson der Stufe oder Veranstaltung'),
                        Textarea::make('hinweis')->label('Hinweis an die Empfänger*in (optional)')->maxLength(500)->rows(2),
                    ])
                    ->modalSubmitActionLabel('Weiterleiten')
                    ->action(fn (ContactMessage $record, array $data) => self::senden($record, 'weitergeleitet', [mb_strtolower(trim((string) $data['an']))], $data['hinweis'] ?? null)),
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
        ];
    }

    public static function status(ContactMessage $record, string $status, ?string $notiz = null): void
    {
        abort_unless(self::darf($record) && isset(ContactMessage::STATUSES[$status]), 403);
        $record->fill([
            'status' => $status,
            'bearbeitet_von' => Filament::auth()->id(),
            'bearbeitet_am' => now(),
        ] + ($notiz !== null ? ['notiz' => trim($notiz) ?: null] : []))->save();
        AuditLogger::log('nachricht.'.$status, Filament::auth()->user(), ContactMessage::class, $record->id, request());
    }

    /** Zurückgehaltene Nachricht (Spamverdacht) doch an die zuständige Person mailen. */
    public static function keinSpam(ContactMessage $record): void
    {
        abort_unless(self::darf($record) && $record->status === 'spam', 403);

        $record->fill(['status' => 'neu', 'notiz' => null, 'bearbeitet_von' => Filament::auth()->id(), 'bearbeitet_am' => now()]);
        self::senden($record, 'kein_spam', $record->empfaenger);
    }

    /**
     * Erneut senden oder weiterleiten: über die Warteschlange, im Verlauf
     * festgehalten, ehrliche Meldung bei Mailer „log“.
     *
     * @param  list<string>  $an
     */
    public static function senden(ContactMessage $record, string $art, array $an, ?string $hinweis = null): string
    {
        abort_unless(self::darf($record) && $an !== [], 403);
        $user = Filament::auth()->user();
        $hinweis = filled($hinweis) ? 'Weitergeleitet von '.($user->name ?? 'der Redaktion').': '.trim((string) $hinweis) : null;

        try {
            Mail::to($an)->send($record->mail($hinweis));
            $ergebnis = Mailversand::nurProtokoll() ? 'log' : 'mail';
        } catch (Throwable) {
            $ergebnis = 'fehler';
        }

        $record->versandEintragen($art, $an, $ergebnis, $user?->name);
        // Angekommen ist sie, sobald ein Versand geklappt hat; ein späterer Fehler ändert daran nichts.
        if ($ergebnis === 'mail') {
            $record->zugestellt = true;
        }
        $record->save();
        AuditLogger::log('nachricht.'.$art.':'.$ergebnis, $user, ContactMessage::class, $record->id, request());

        $wort = match ($art) {
            'weitergeleitet' => 'Weitergeleitet',
            'kein_spam' => 'Zugestellt',
            default => 'Erneut gesendet',
        };
        $notification = match ($ergebnis) {
            'mail' => Notification::make()->title($wort.' an '.implode(', ', $an))->success(),
            'log' => Notification::make()->title('Nicht verschickt')->body('Mails werden derzeit nur ins Server-Protokoll geschrieben. Unter Verwaltung → E-Mail-Versand steht, was zu tun ist.')->warning()->persistent(),
            default => Notification::make()->title('Mail fehlgeschlagen')->body('Bitte später erneut versuchen.')->danger(),
        };
        $notification->send();

        return $ergebnis;
    }

    /** @return list<string> Adressen der Kontaktthemen als Vorschläge beim Weiterleiten. */
    private static function adressVorschlaege(): array
    {
        return ContactTopic::query()->active()->get()
            ->flatMap(fn (ContactTopic $topic) => $topic->recipients())
            ->push(ContactTopic::fallbackEmail())
            ->filter()->map(fn ($adresse) => mb_strtolower((string) $adresse))->unique()->sort()->values()->all();
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
