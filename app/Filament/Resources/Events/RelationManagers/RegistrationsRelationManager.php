<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\EventRegistrations;
use App\Services\ParticipantExport;
use App\Support\AuditLogger;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Teilnehmerübersicht (F-EV-05/06) und Export (F-EV-20..22). Nur für
 * Berechtigte im Scope der Veranstaltung – der Vorstand sieht hier nichts.
 */
class RegistrationsRelationManager extends RelationManager
{
    protected static string $relationship = 'registrations';

    protected static ?string $title = 'Teilnehmende';

    protected static ?string $modelLabel = 'Anmeldung';

    protected static ?string $pluralModelLabel = 'Anmeldungen';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) Filament::auth()->user()?->can('manageParticipants', $ownerRecord);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        /** @var Event $event */
        $event = $this->getOwnerRecord();
        $fields = $event->formFields();

        return $table
            ->description(fn () => $this->attendancePerDay($event, $fields))
            ->recordTitle(fn (EventRegistration $record) => $record->fullName())
            ->columns([
                TextColumn::make('last_name')->label('Name')
                    ->formatStateUsing(fn (EventRegistration $record) => $record->last_name.', '.$record->first_name)
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                TextColumn::make('email')->label('E-Mail')->searchable()->toggleable(),
                TextColumn::make('birth_year')->label('Jg.')->visible(isset($fields['birth_year'])),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => EventRegistration::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'confirmed' => 'success',
                        'waitlist' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                ...collect($fields)->filter(fn (array $f) => $f['type'] === 'days')
                    ->map(fn (array $f, string $key) => TextColumn::make("data.{$key}")->label('Tage')
                        ->state(fn (EventRegistration $record) => $event->fieldValueLabel($f, $record->data[$key] ?? null))
                        ->placeholder('–'))->values()->all(),
                TextColumn::make('guardian_name')->label('Angemeldet durch')->placeholder('–')->toggleable(),
                TextColumn::make('confirmed_at')->label('Bestätigt am')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->defaultSort('confirmed_at')
            ->filters([
                SelectFilter::make('status')->label('Status')->options(EventRegistration::STATUSES),
            ])
            ->headerActions([
                Action::make('export')
                    ->label('Teilnehmerliste (.xlsx)')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (ParticipantExport $export): StreamedResponse {
                        $event = $this->getOwnerRecord();
                        abort_unless(Filament::auth()->user()?->can('manageParticipants', $event), 403);

                        // Jeder Export wird protokolliert (F-EV-21).
                        AuditLogger::log('event.export', Filament::auth()->user(), Event::class, $event->getKey(), request());

                        return response()->streamDownload(fn () => $export->write($event), $export->filename($event), [
                            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ]);
                    }),
            ])
            ->recordActions([
                Action::make('details')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Schließen')
                    ->modalContent(fn (EventRegistration $record) => new HtmlString(view('filament.registration-details', [
                        'registration' => $record,
                        'fields' => $fields,
                    ])->render())),
                Action::make('promote')
                    ->label('Platz geben')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (EventRegistration $record) => $record->status === 'waitlist')
                    ->requiresConfirmation()
                    ->modalDescription('Die Person rückt von der Warteliste nach und bekommt eine E-Mail – auch wenn das Limit dadurch überschritten wird.')
                    ->action(fn (EventRegistration $record) => $this->changeStatus($record, 'promote')),
                Action::make('waitlist')
                    ->label('Auf Warteliste')
                    ->icon('heroicon-o-queue-list')
                    ->color('warning')
                    ->visible(fn (EventRegistration $record) => $record->status === 'confirmed')
                    ->requiresConfirmation()
                    ->action(fn (EventRegistration $record) => $this->changeStatus($record, 'moveToWaitlist')),
                Action::make('cancel')
                    ->label('Stornieren')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (EventRegistration $record) => in_array($record->status, ['confirmed', 'waitlist'], true))
                    ->requiresConfirmation()
                    ->modalDescription('Die Person bekommt eine Stornierungsmail; ein frei werdender Platz geht an die Warteliste.')
                    ->action(fn (EventRegistration $record) => $this->changeStatus($record, 'cancel')),
            ]);
    }

    /**
     * „Anwesend (bestätigt): Fr, 20.11.: 34 · Sa, 21.11.: 41 · …“ – nur bei
     * aktivierter Tage-Abfrage. Die Angaben sind verschlüsselt gespeichert,
     * daher wird in PHP gezählt.
     *
     * @param  array<string, array<string, mixed>>  $fields
     */
    private function attendancePerDay(Event $event, array $fields): ?string
    {
        $key = collect($fields)->search(fn (array $f) => $f['type'] === 'days');
        if ($key === false) {
            return null;
        }

        $counts = array_fill_keys(array_keys($event->days()), 0);
        $event->registrations()->where('status', 'confirmed')->get()
            ->each(function (EventRegistration $r) use ($key, &$counts) {
                foreach ((array) ($r->data[$key] ?? []) as $day) {
                    if (array_key_exists($day, $counts)) {
                        $counts[$day]++;
                    }
                }
            });

        return 'Anwesend (bestätigt): '.collect($counts)->map(fn (int $n, string $day) => $event->days()[$day].': '.$n)->implode(' · ');
    }

    private function changeStatus(EventRegistration $record, string $method): void
    {
        abort_unless(Filament::auth()->user()?->can('manageParticipants', $this->getOwnerRecord()), 403);

        app(EventRegistrations::class)->{$method}($record);
        AuditLogger::log("registration.{$method}", Filament::auth()->user(), EventRegistration::class, $record->getKey(), request());

        Notification::make()->title('Gespeichert – die Person wurde per E-Mail informiert.')->success()->send();
    }
}
