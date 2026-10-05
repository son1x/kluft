<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Jobs\CheckEventMembers;
use App\Mail\RegistrationDocumentMail;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\RegistrationDocument;
use App\Services\EventRegistrations;
use App\Services\Mitglieder\MitgliederAbgleich;
use App\Services\Mitglieder\Mitgliederverzeichnis;
use App\Services\ParticipantExport;
use App\Support\AuditLogger;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

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
            ->modifyQueryUsing(fn ($query) => $query->with('documents'))
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
                TextColumn::make('documents_count')->label('Dokumente')->counts('documents')
                    ->formatStateUsing(fn (int $state) => $state ?: '–')
                    ->tooltip(fn (EventRegistration $record) => $record->documents->map->label()->implode(', ') ?: null)
                    ->toggleable(),
                TextColumn::make('member_status')->label('Mitglied')->badge()
                    ->visible($event->checksMembers())
                    ->formatStateUsing(fn (?string $state) => MitgliederAbgleich::STATUSES[$state] ?? $state)
                    ->color(fn (?string $state) => match ($state) {
                        'mitglied' => 'success',
                        'unklar' => 'warning',
                        'nicht_gefunden', 'ehemalig', 'kein_mitglied' => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('noch nicht geprüft')
                    ->description(fn (EventRegistration $record) => collect([$record->member_number ? 'Nr. '.$record->member_number : null, $record->member_group, $record->member_manual ? 'von Hand' : null])->filter()->implode(' · ') ?: null),
                TextColumn::make('guardian_name')->label('Angemeldet durch')->placeholder('–')->toggleable(),
                TextColumn::make('confirmed_at')->label('Bestätigt am')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->defaultSort('confirmed_at')
            ->filters([
                SelectFilter::make('status')->label('Status')->options(EventRegistration::STATUSES),
                SelectFilter::make('member_status')->label('Mitglied')->options(MitgliederAbgleich::STATUSES)
                    ->visible($event->checksMembers()),
            ])
            ->headerActions([
                Action::make('checkMembers')
                    ->label('Mitgliedschaft prüfen')
                    ->icon('heroicon-o-identification')
                    ->visible($event->checksMembers())
                    ->modalHeading('Mit der Mitgliederverwaltung abgleichen')
                    ->modalDescription('Bestätigte Anmeldungen und Warteliste werden mit '.app(Mitgliederverzeichnis::class)->name().' abgeglichen – im Hintergrund, etwa 30 je Minute. Von Hand entschiedene Anmeldungen bleiben, wie sie sind.')
                    ->schema([
                        Toggle::make('alle')->label('Auch bereits geprüfte Anmeldungen neu prüfen'),
                    ])
                    ->action(function (array $data) {
                        $event = $this->getOwnerRecord();
                        abort_unless(Filament::auth()->user()?->can('manageParticipants', $event), 403);
                        CheckEventMembers::dispatch($event->getKey(), (bool) ($data['alle'] ?? false));
                        AuditLogger::log('event.member_check', Filament::auth()->user(), Event::class, $event->getKey(), request());
                        Notification::make()->title('Abgleich gestartet – die Spalte „Mitglied“ füllt sich in den nächsten Minuten.')->success()->send();
                    }),
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
                ActionGroup::make([
                    Action::make('uploadDocument')
                        ->label('Dokument hochladen')
                        ->icon('heroicon-o-arrow-up-tray')
                        ->modalHeading(fn (EventRegistration $record) => 'Dokument für '.$record->fullName().' ('.$record->email.')')
                        ->modalDescription('Die Person findet das PDF unter „Meine Anmeldungen“ (Anmeldung per E-Mail und Einmalcode). Es wird mit der Anmeldung gelöscht.')
                        ->schema([
                            Select::make('type')->label('Art')->options(RegistrationDocument::TYPES)->default('rechnung')->required()->live(),
                            TextInput::make('title')->label('Bezeichnung (optional)')->placeholder('z. B. Rechnung Nr. 2026-117')->maxLength(120),
                            FileUpload::make('path')->label('PDF')
                                ->disk(RegistrationDocument::DISK)
                                ->directory('anmeldungen/'.$event->id)
                                ->visibility('private')
                                ->acceptedFileTypes(['application/pdf'])
                                ->maxSize(10240)
                                ->storeFileNamesIn('original_name')
                                ->required(),
                            Toggle::make('replace')->label('Bisherige Dokumente dieser Art ersetzen')->default(true),
                            Toggle::make('notify')->label('Person per E-Mail informieren')->default(true),
                        ])
                        ->action(fn (EventRegistration $record, array $data) => $this->storeDocument($record, $data)),
                    Action::make('downloadDocument')
                        ->label('Dokument ansehen')
                        ->icon('heroicon-o-document-arrow-down')
                        ->visible(fn (EventRegistration $record) => $record->documents->isNotEmpty())
                        ->schema(fn (EventRegistration $record) => [
                            Select::make('document')->label('Dokument')->required()
                                ->options($record->documents->mapWithKeys(fn (RegistrationDocument $d) => [$d->id => $d->label().' ('.$d->created_at->format('d.m.Y').')']))
                                ->default($record->documents->first()?->id),
                        ])
                        ->modalSubmitActionLabel('Herunterladen')
                        ->action(function (EventRegistration $record, array $data) {
                            abort_unless(Filament::auth()->user()?->can('manageParticipants', $this->getOwnerRecord()), 403);
                            $document = $record->documents()->findOrFail($data['document']);

                            return Storage::disk(RegistrationDocument::DISK)->download($document->path, $document->downloadName());
                        }),
                    Action::make('deleteDocument')
                        ->label('Dokument löschen')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->visible(fn (EventRegistration $record) => $record->documents->isNotEmpty())
                        ->schema(fn (EventRegistration $record) => [
                            Select::make('document')->label('Dokument')->required()
                                ->options($record->documents->mapWithKeys(fn (RegistrationDocument $d) => [$d->id => $d->label().' ('.$d->created_at->format('d.m.Y').')'])),
                        ])
                        ->modalSubmitActionLabel('Löschen')
                        ->action(function (EventRegistration $record, array $data) {
                            abort_unless(Filament::auth()->user()?->can('manageParticipants', $this->getOwnerRecord()), 403);
                            $record->documents()->findOrFail($data['document'])->delete();
                            AuditLogger::log('registration.document.delete', Filament::auth()->user(), EventRegistration::class, $record->getKey(), request());
                            Notification::make()->title('Dokument gelöscht.')->success()->send();
                        }),
                ])->label('Dokumente')->icon('heroicon-o-document-text')->button()->outlined()->size('sm'),
                Action::make('memberDecision')
                    ->label('Mitgliedschaft')
                    ->icon('heroicon-o-identification')
                    ->color('gray')
                    ->visible(fn (EventRegistration $record) => $event->checksMembers() && $record->status !== 'cancelled')
                    ->modalHeading(fn (EventRegistration $record) => 'Mitgliedschaft von '.$record->fullName())
                    ->modalDescription('Z. B. bei „unklar“ nach Rücksprache. Die Entscheidung überschreibt kein späterer automatischer Abgleich.')
                    ->fillForm(fn (EventRegistration $record) => [
                        'status' => in_array($record->member_status, ['mitglied', 'kein_mitglied'], true) ? $record->member_status : null,
                        'number' => $record->member_number,
                        'group' => $record->member_group,
                    ])
                    ->schema([
                        Select::make('status')->label('Ergebnis')->required()->live()
                            ->options(['mitglied' => 'Mitglied', 'kein_mitglied' => 'Kein Mitglied']),
                        TextInput::make('number')->label('Mitgliedsnummer')->maxLength(40)
                            ->visible(fn (Get $get) => $get('status') === 'mitglied'),
                        TextInput::make('group')->label('Stamm / Gruppierung')->maxLength(150)
                            ->visible(fn (Get $get) => $get('status') === 'mitglied'),
                    ])
                    ->action(function (EventRegistration $record, array $data) {
                        abort_unless(Filament::auth()->user()?->can('manageParticipants', $this->getOwnerRecord()), 403);
                        MitgliederAbgleich::manuell($record, $data['status'], $data['number'] ?? null, $data['group'] ?? null);
                        AuditLogger::log('registration.member_decision', Filament::auth()->user(), EventRegistration::class, $record->getKey(), request());
                        Notification::make()->title('Gespeichert.')->success()->send();
                    }),
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

    /**
     * @param  array{type: string, title?: string|null, path: string, original_name?: string|null, replace?: bool, notify?: bool}  $data
     */
    private function storeDocument(EventRegistration $record, array $data): void
    {
        abort_unless(Filament::auth()->user()?->can('manageParticipants', $this->getOwnerRecord()), 403);

        if ($data['replace'] ?? false) {
            $record->documents()->where('type', $data['type'])->get()->each->delete();
        }

        $document = $record->documents()->create([
            'type' => $data['type'],
            'title' => filled($data['title'] ?? null) ? $data['title'] : null,
            'path' => $data['path'],
            'original_name' => $data['original_name'] ?? basename($data['path']),
            'size' => (int) Storage::disk(RegistrationDocument::DISK)->size($data['path']),
            'uploaded_by' => Filament::auth()->id(),
        ]);
        AuditLogger::log('registration.document.upload', Filament::auth()->user(), EventRegistration::class, $record->getKey(), request());

        if ($data['notify'] ?? false) {
            try {
                Mail::to($record->email)->send(new RegistrationDocumentMail($document));
            } catch (Throwable $e) {
                report($e);
            }
        }

        Notification::make()->title('Dokument gespeichert'.(($data['notify'] ?? false) ? ' – die Person wurde per E-Mail informiert.' : '.'))->success()->send();
    }

    private function changeStatus(EventRegistration $record, string $method): void
    {
        abort_unless(Filament::auth()->user()?->can('manageParticipants', $this->getOwnerRecord()), 403);

        app(EventRegistrations::class)->{$method}($record);
        AuditLogger::log("registration.{$method}", Filament::auth()->user(), EventRegistration::class, $record->getKey(), request());

        Notification::make()->title('Gespeichert – die Person wurde per E-Mail informiert.')->success()->send();
    }
}
