<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Jobs\CheckEventMembers;
use App\Mail\RegistrationDocumentMail;
use App\Mail\TeilnahmebescheinigungMail;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Gruppierung;
use App\Models\RegistrationDocument;
use App\Models\User;
use App\Models\Zahlung;
use App\Services\EventRegistrations;
use App\Services\Mitglieder\MitgliederAbgleich;
use App\Services\Mitglieder\Mitgliederverzeichnis;
use App\Services\Mitglieder\MitgliederverzeichnisException;
use App\Services\Mitglieder\NamiVerzeichnis;
use App\Services\ParticipantExport;
use App\Services\Teilnahmebescheinigung;
use App\Services\TeilnehmendenListe;
use App\Support\AuditLogger;
use App\Support\Features;
use App\Support\NamiAmpel;
use App\Support\Teilnahmebeitraege;
use App\Support\Zahlungen;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
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
            ->modifyQueryUsing(fn ($query) => $query->with(['documents', 'zahlungen']))
            ->description(fn () => $this->attendancePerDay($event, $fields))
            ->recordTitle(fn (EventRegistration $record) => $record->fullName())
            ->columns([
                TextColumn::make('last_name')->label('Name')
                    ->formatStateUsing(fn (EventRegistration $record) => $record->last_name.', '.$record->first_name)
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                TextColumn::make('email')->label('E-Mail')->searchable()->toggleable()->placeholder('–'),
                TextColumn::make('birth_year')->label('Jg.')->visible(isset($fields['birth_year'])),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => EventRegistration::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'confirmed' => 'success',
                        'waitlist' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('preis_cent')->label('Beitrag')->alignEnd()
                    ->formatStateUsing(fn (?int $state) => Zahlungen::euro($state))->placeholder('–')
                    ->description(fn (EventRegistration $record) => $record->preis_bezeichnung)
                    ->visible($event->hatTeilnahmebeitrag()),
                TextColumn::make('zahlung')->label('Zahlung')->badge()
                    ->state(fn (EventRegistration $record) => $record->zahlungsstand() ?? 'keine')
                    ->formatStateUsing(fn (string $state) => Zahlung::STATUS[$state] ?? 'keine Forderung')
                    ->color(fn (string $state) => match ($state) {
                        'bezahlt' => 'success', 'teilweise' => 'warning', 'offen' => 'danger', default => 'gray',
                    })
                    ->description(fn (EventRegistration $record) => $record->zahlung()?->referenz)
                    ->visible($event->hatTeilnahmebeitrag()),
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
                        'gast' => 'info',
                        default => 'gray',
                    })
                    ->placeholder('noch nicht geprüft')
                    ->description(fn (EventRegistration $record) => collect([
                        $record->member_number ? 'Nr. '.$record->member_number : null,
                        $record->member_group,
                        match ($record->member_own_group) {
                            true => '✓ eigene Gruppierung',
                            false => '⚠ andere Gruppierung',
                            default => null,
                        },
                        $record->member_manual ? 'von Hand' : null,
                    ])->filter()->implode(' · ') ?: null),
                TextColumn::make('certificate_approved_at')->label('Bescheinigung')->badge()
                    ->visible($event->bescheinigungAn())
                    ->state(fn (EventRegistration $record) => $record->uploadedCertificate() ? 'hochgeladen'
                        : ($record->certificateApproved() ? 'freigegeben' : ($record->status === 'confirmed' ? 'offen' : null)))
                    ->color(fn (?string $state) => $state === 'offen' ? 'gray' : 'success')
                    ->description(fn (EventRegistration $record) => $record->certificateApproved() ? $record->certificate_approved_at?->format('d.m.Y') : null)
                    ->placeholder('–'),
                TextColumn::make('guardian_name')->label('Angemeldet durch')->placeholder('–')->toggleable(),
                TextColumn::make('confirmed_at')->label('Bestätigt am')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->defaultSort('confirmed_at')
            ->filters([
                SelectFilter::make('status')->label('Status')->options(EventRegistration::STATUSES),
                SelectFilter::make('member_status')->label('Mitglied')->options(MitgliederAbgleich::STATUSES)
                    ->visible($event->checksMembers()),
                TernaryFilter::make('member_own_group')->label('Gruppierung')->nullable()
                    ->trueLabel('eigene')->falseLabel('andere')
                    ->visible($event->checksMembers() && $event->nurFuerMitglieder()),
                TernaryFilter::make('certificate_approved_at')->label('Bescheinigung')->nullable()
                    ->trueLabel('freigegeben')->falseLabel('noch nicht freigegeben')
                    ->visible($event->bescheinigungAn()),
            ])
            ->headerActions([
                ActionGroup::make([
                    Action::make('addParticipant')
                        ->label('Einzeln eintragen')
                        ->icon('heroicon-o-user-plus')
                        ->modalHeading('Teilnehmende*n eintragen')
                        ->modalDescription('Für Anmeldungen per Telefon, E-Mail oder Papier. Ohne Bestätigungslink; die Eintragung wird mit deinem Namen protokolliert.')
                        ->schema(fn () => [
                            TextInput::make('first_name')->label('Vorname')->required()->maxLength(255),
                            TextInput::make('last_name')->label('Nachname')->required()->maxLength(255),
                            TextInput::make('email')->label('E-Mail-Adresse')->email()->maxLength(255)
                                ->helperText('Optional. Ohne Adresse gibt es keine E-Mails und keinen Zugang zu „Meine Anmeldungen“.'),
                            ...$this->eventFieldInputs($event),
                            ...$this->preisInputs($event),
                            ...$this->manualStatusInputs($event),
                        ])
                        ->action(function (array $data) {
                            $event = $this->veranstaltung();
                            abort_unless(Filament::auth()->user()?->can('manageParticipants', $event), 403);

                            $person = [
                                'first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'email' => $data['email'] ?? '',
                                'birth_year' => filled($data['birth_year'] ?? null) ? (int) $data['birth_year'] : null,
                                'data' => (array) ($data['data'] ?? []),
                                'preis' => filled($data['preis'] ?? null) ? ($event->preise()[(int) $data['preis']] ?? null) : null,
                            ];
                            [$result] = app(EventRegistrations::class)->addManually($event, $person, (string) $data['status'], (bool) ($data['notify'] ?? false), self::namiNutzer());
                            $this->afterManualAdd($event);

                            match ($result) {
                                'duplicate' => Notification::make()->warning()->title('Nicht eingetragen: '.$data['first_name'].' '.$data['last_name'].' ist schon angemeldet.')->send(),
                                default => Notification::make()->success()->title($data['first_name'].' '.$data['last_name'].' eingetragen ('.EventRegistration::STATUSES[$result].').')->send(),
                            };
                        }),
                    Action::make('importParticipants')
                        ->label('Liste eintragen')
                        ->icon('heroicon-o-queue-list')
                        ->modalHeading('Mehrere Teilnehmende eintragen')
                        ->modalDescription(fn () => new HtmlString(e('Liste aus Excel kopieren und einfügen oder als .xlsx/.csv hochladen. Mit Kopfzeile werden die Spalten nach Namen zugeordnet (Vorname, Nachname oder Name, E-Mail, Geburtsjahr')
                            .e(collect($event->formFields())->except('birth_year')->filter(fn (array $f) => $f['type'] !== 'days')->pluck('label')->map(fn ($l) => ', '.$l)->implode(''))
                            .e('); ohne Kopfzeile gilt: Vorname, Nachname, E-Mail, Geburtsjahr. Wer schon angemeldet ist, wird übersprungen.')))
                        ->schema(fn () => [
                            Textarea::make('liste')->label('Liste einfügen')->rows(8)
                                ->placeholder("Vorname\tNachname\tE-Mail\tGeburtsjahr\nMia\tMuster\tmia@example.org\t2012")
                                ->requiredWithout('datei'),
                            FileUpload::make('datei')->label('… oder Datei hochladen')
                                ->disk('local')->directory('tmp/teilnehmende')->visibility('private')
                                ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'])
                                ->maxSize(2048)
                                ->storeFileNamesIn('datei_name'),
                            ...$this->manualStatusInputs($event),
                        ])
                        ->action(function (array $data, TeilnehmendenListe $liste) {
                            $event = $this->veranstaltung();
                            abort_unless(Filament::auth()->user()?->can('manageParticipants', $event), 403);

                            if (filled($data['datei'] ?? null)) {
                                $pfad = (string) $data['datei'];
                                $gelesen = $liste->ausDatei($event, Storage::disk('local')->path($pfad), (string) ($data['datei_name'] ?? $pfad));
                                Storage::disk('local')->delete($pfad);
                            } else {
                                $gelesen = $liste->ausText($event, (string) ($data['liste'] ?? ''));
                            }

                            $zaehler = ['confirmed' => 0, 'waitlist' => 0, 'duplicate' => 0];
                            foreach ($gelesen['personen'] as $person) {
                                [$result] = app(EventRegistrations::class)->addManually($event, $person, (string) $data['status'], (bool) ($data['notify'] ?? false), self::namiNutzer());
                                $zaehler[$result]++;
                            }
                            $this->afterManualAdd($event);

                            $neu = $zaehler['confirmed'] + $zaehler['waitlist'];
                            $teile = array_filter([
                                $zaehler['confirmed'] ? $zaehler['confirmed'].' bestätigt' : null,
                                $zaehler['waitlist'] ? $zaehler['waitlist'].' auf der Warteliste' : null,
                            ]);
                            $hinweise = array_filter([
                                $zaehler['duplicate'] ? $zaehler['duplicate'].' schon angemeldet, übersprungen.' : null,
                                ...$gelesen['fehler'] !== [] ? [count($gelesen['fehler']).' Zeile(n) nicht eingetragen: '.implode('; ', array_slice($gelesen['fehler'], 0, 10)).(count($gelesen['fehler']) > 10 ? ' …' : '')] : [],
                            ]);
                            $meldung = Notification::make()
                                ->title($neu.' '.($neu === 1 ? 'Person' : 'Personen').' eingetragen'.($teile ? ' ('.implode(', ', $teile).')' : '').'.')
                                ->body($hinweise ? implode(' ', $hinweise) : null)
                                ->status($gelesen['fehler'] !== [] || $neu === 0 ? 'warning' : 'success');
                            if ($gelesen['fehler'] !== []) {
                                $meldung->persistent(); // Fehlerliste bleibt stehen, bis sie gelesen ist
                            }
                            $meldung->send();
                        }),
                ])
                    ->label('Teilnehmende eintragen')
                    ->icon('heroicon-o-user-plus')
                    ->button()
                    ->visible(Features::enabled(Features::TEILNEHMENDE_EINTRAGEN)),
                Action::make('checkMembers')
                    ->label('Mitgliedschaft prüfen')
                    ->icon('heroicon-o-identification')
                    ->visible($event->checksMembers())
                    ->modalHeading('Mit der Mitgliederverwaltung abgleichen')
                    ->modalDescription(fn () => new HtmlString(e('Bestätigte Anmeldungen und Warteliste werden mit '.app(Mitgliederverzeichnis::class)->name().' abgeglichen – im Hintergrund, etwa 30 je Minute. Von Hand entschiedene Anmeldungen bleiben, wie sie sind.')
                        .(NamiVerzeichnis::persoenlich() ? ' '.e(self::gespeicherterZugang(self::namiNutzer())
                            ? 'Die Anmeldung bei NaMi läuft über deinen im Profil gespeicherten Zugang.'
                            : 'Du meldest dich mit deinem eigenen NaMi-Konto an; das Passwort wird nur gespeichert, wenn du es unten ankreuzst.')
                            .' '.e('Das Ergebnis folgt deinen NaMi-Rechten – „nicht gefunden“ kann auch heißen, dass die Person für dich in NaMi nicht sichtbar ist.') : '')
                        .(config('mitglieder.driver') === 'nami' ? '<div class="mt-3 text-start">'.NamiAmpel::html(self::namiNutzer())->toHtml().'</div>' : '')))
                    ->schema(fn () => [
                        ...NamiVerzeichnis::persoenlich() && ! self::gespeicherterZugang(self::namiNutzer()) ? [
                            TextInput::make('nami_user')->label('Deine NaMi-Mitgliedsnummer')->required()->autocomplete('username'),
                            TextInput::make('nami_password')->label('Dein NaMi-Passwort')->password()->required()->autocomplete('current-password'),
                            Toggle::make('nami_speichern')->label('Zugang verschlüsselt in meinem Profil speichern (dann keine Eingabe mehr nötig)')
                                ->visible(Features::enabled(Features::NAMI_PROFIL)),
                        ] : [],
                        Toggle::make('alle')->label('Auch bereits geprüfte Anmeldungen neu prüfen'),
                    ])
                    ->action(function (array $data, Action $action) {
                        $event = $this->getOwnerRecord();
                        abort_unless(Filament::auth()->user()?->can('manageParticipants', $event), 403);

                        // Persönliche Anmeldung: hier anmelden; in die Warteschlange gehen nur das
                        // Session-Cookie und ggf. die Person (für eine Neuanmeldung mit ihrem gespeicherten Zugang).
                        $session = null;
                        $nutzer = self::namiNutzer();
                        if (NamiVerzeichnis::persoenlich()) {
                            try {
                                $session = self::gespeicherterZugang($nutzer)
                                    ? (new NamiVerzeichnis)->anmeldenMitProfil($nutzer)
                                    : (new NamiVerzeichnis)->anmelden((string) ($data['nami_user'] ?? ''), (string) ($data['nami_password'] ?? ''));
                            } catch (MitgliederverzeichnisException $e) {
                                Notification::make()->title('NaMi-Anmeldung fehlgeschlagen')
                                    ->body($e->getMessage().(self::gespeicherterZugang($nutzer) ? ' Gespeicherten Zugang unter „Mein Konto → Mein NaMi-Zugang“ prüfen.' : ''))
                                    ->danger()->send();
                                $action->halt();
                            }
                            if ($nutzer && ! self::gespeicherterZugang($nutzer) && Features::enabled(Features::NAMI_PROFIL) && ($data['nami_speichern'] ?? false)) {
                                $nutzer->forceFill(['nami_user' => trim((string) $data['nami_user']), 'nami_password' => (string) $data['nami_password'], 'nami_checked_at' => now(), 'nami_error' => null])->save();
                                AuditLogger::log('nami.zugang.gespeichert', $nutzer, request: request());
                            }
                        }

                        CheckEventMembers::dispatch($event->getKey(), (bool) ($data['alle'] ?? false), null, null, $session, self::gespeicherterZugang($nutzer) ? $nutzer->getKey() : null);
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
                    Action::make('approveCertificate')
                        ->label('Bescheinigung freigeben')
                        ->icon('heroicon-o-check-badge')
                        ->visible(fn (EventRegistration $record) => $event->certificate_enabled && $record->status === 'confirmed' && ! $record->certificate_approved_at)
                        ->modalHeading(fn (EventRegistration $record) => 'Teilnahme von '.$record->fullName().' bestätigen')
                        ->modalDescription('Die Person findet ihre Teilnahmebescheinigung danach unter „Meine Anmeldungen“.')
                        ->schema([Toggle::make('notify')->label('Person per E-Mail informieren')->default(true)])
                        ->action(fn (EventRegistration $record, array $data) => $this->approveCertificates(new Collection([$record]), (bool) ($data['notify'] ?? false))),
                    Action::make('revokeCertificate')
                        ->label('Freigabe zurücknehmen')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->visible(fn (EventRegistration $record) => $event->certificate_enabled && $record->certificate_approved_at !== null)
                        ->requiresConfirmation()
                        ->modalDescription('Die Bescheinigung verschwindet aus „Meine Anmeldungen“. Bereits heruntergeladene PDFs lassen sich weiter prüfen, solange Name und Prüfcode stimmen.')
                        ->action(fn (EventRegistration $record) => $this->revokeCertificates(new Collection([$record]))),
                    Action::make('previewCertificate')
                        ->label('Bescheinigung ansehen')
                        ->icon('heroicon-o-document-check')
                        ->visible(fn (EventRegistration $record) => $event->certificate_enabled && $record->status === 'confirmed')
                        ->action(function (EventRegistration $record, Teilnahmebescheinigung $bescheinigung): StreamedResponse {
                            abort_unless(Filament::auth()->user()?->can('manageParticipants', $this->getOwnerRecord()), 403);
                            $pdf = $bescheinigung->pdf($record);

                            return response()->streamDownload(fn () => print ($pdf), $bescheinigung->filename($record), ['Content-Type' => 'application/pdf']);
                        }),
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
                Action::make('beitrag')
                    ->label('Beitrag')
                    ->icon('heroicon-o-banknotes')
                    ->color('gray')
                    ->visible(fn (EventRegistration $record) => $event->hatTeilnahmebeitrag() && in_array($record->status, ['confirmed', 'waitlist'], true)
                        && ! in_array($record->zahlungsstand(), ['bezahlt', 'teilweise', 'erstattet'], true))
                    ->modalHeading(fn (EventRegistration $record) => 'Teilnahmebeitrag – '.$record->fullName())
                    ->modalDescription(fn (EventRegistration $record) => Teilnahmebeitraege::aktiv()
                        ? ($record->zahlungsstand() === 'offen'
                            ? 'Eine Forderung ist offen ('.$record->zahlung()?->referenz.'). Ändern storniert sie und legt eine neue an; „Aufforderung schicken“ verschickt die Mail mit GiroCode erneut.'
                            : 'Bei bestätigter Anmeldung entsteht eine Forderung; auf Wunsch geht die Zahlungsaufforderung mit GiroCode raus.')
                        : 'Zahlungsaufforderungen gibt es, sobald die Funktion „Zahlungen“ an und eine Bankverbindung hinterlegt ist.')
                    ->fillForm(fn (EventRegistration $record) => [
                        'preis' => ($i = collect($event->preise())->search(fn (array $p) => $p['bezeichnung'] === $record->preis_bezeichnung && $p['cent'] === $record->preis_cent)) === false ? null : $i,
                        'senden' => filled($record->email),
                    ])
                    ->schema(fn () => [
                        ...$this->preisInputs($event, true),
                        Toggle::make('senden')->label('Zahlungsaufforderung per E-Mail schicken')->visible(fn () => Teilnahmebeitraege::aktiv()),
                    ])
                    ->action(function (EventRegistration $record, array $data) {
                        $event = $this->veranstaltung();
                        abort_unless(Filament::auth()->user()?->can('manageParticipants', $event), 403);
                        $preis = $event->preise()[(int) $data['preis']] ?? null;
                        if ($preis === null) {
                            return;
                        }
                        $offen = $record->zahlung();
                        $geaendert = $record->preis_bezeichnung !== $preis['bezeichnung'] || $record->preis_cent !== $preis['cent'];
                        $record->update(['preis_bezeichnung' => $preis['bezeichnung'], 'preis_cent' => $preis['cent']]);
                        if ($offen && $geaendert) {
                            Teilnahmebeitraege::stornieren($record, 'Beitrag geändert');
                            $offen = null;
                        }
                        if ($offen === null) {
                            Teilnahmebeitraege::fordern($record->fresh(), (bool) ($data['senden'] ?? false));
                        } elseif (($data['senden'] ?? false) && filled($record->email)) {
                            Zahlungen::auffordern($offen, $record->email, $record->guardian_name ?: $record->fullName(), 'Teilnehmende Person: '.$record->fullName().'.');
                        }
                        AuditLogger::log('registration.beitrag', Filament::auth()->user(), EventRegistration::class, $record->getKey(), request());
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approveCertificates')
                        ->label('Bescheinigungen freigeben')
                        ->icon('heroicon-o-check-badge')
                        ->color('success')
                        ->modalHeading('Teilnahme bestätigen und Bescheinigungen freigeben')
                        ->modalDescription('Gilt für die markierten bestätigten Anmeldungen. Warteliste, Stornierte und bereits Freigegebene werden übersprungen.')
                        ->schema([Toggle::make('notify')->label('Personen per E-Mail informieren')->default(true)])
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records, array $data) => $this->approveCertificates($records, (bool) ($data['notify'] ?? false))),
                    BulkAction::make('revokeCertificates')
                        ->label('Freigabe zurücknehmen')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => $this->revokeCertificates($records)),
                ])->label('Bescheinigungen')->visible($event->bescheinigungAn()),
            ]);
    }

    /**
     * Teilnahme bestätigen: nur bestätigte, noch nicht freigegebene Anmeldungen.
     *
     * @param  Collection<int, EventRegistration>  $records
     */
    private function approveCertificates(Collection $records, bool $notify): void
    {
        abort_unless(Filament::auth()->user()?->can('manageParticipants', $this->getOwnerRecord()), 403);

        $offen = $records->filter(fn (EventRegistration $r) => $r->event_id === $this->getOwnerRecord()->getKey()
            && $r->status === 'confirmed' && $r->certificate_approved_at === null);

        foreach ($offen as $registration) {
            $registration->forceFill(['certificate_approved_at' => now(), 'certificate_approved_by' => Filament::auth()->id()])->save();
            AuditLogger::log('registration.certificate.approve', Filament::auth()->user(), EventRegistration::class, $registration->getKey(), request());
            if ($notify && filled($registration->email)) {
                try {
                    Mail::to($registration->email)->send(new TeilnahmebescheinigungMail($registration));
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }

        $uebersprungen = $records->count() - $offen->count();
        Notification::make()
            ->title($offen->count().' '.($offen->count() === 1 ? 'Bescheinigung' : 'Bescheinigungen').' freigegeben'.($notify && $offen->isNotEmpty() ? ', Personen werden per E-Mail informiert' : '').'.')
            ->body($uebersprungen > 0 ? $uebersprungen.' übersprungen (nicht bestätigt oder schon freigegeben).' : null)
            ->success()->send();
    }

    /**
     * @param  Collection<int, EventRegistration>  $records
     */
    private function revokeCertificates(Collection $records): void
    {
        abort_unless(Filament::auth()->user()?->can('manageParticipants', $this->getOwnerRecord()), 403);

        $freigegeben = $records->filter(fn (EventRegistration $r) => $r->event_id === $this->getOwnerRecord()->getKey() && $r->certificate_approved_at !== null);
        foreach ($freigegeben as $registration) {
            $registration->forceFill(['certificate_approved_at' => null, 'certificate_approved_by' => null])->save();
            AuditLogger::log('registration.certificate.revoke', Filament::auth()->user(), EventRegistration::class, $registration->getKey(), request());
        }

        Notification::make()->title('Freigabe bei '.$freigegeben->count().' '.($freigegeben->count() === 1 ? 'Anmeldung' : 'Anmeldungen').' zurückgenommen.')->success()->send();
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

        if (($data['notify'] ?? false) && filled($record->email)) {
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

    /**
     * Auswahl der Preisstufe (nur bei Veranstaltungen mit Teilnahmebeitrag).
     *
     * @return list<Component>
     */
    private function preisInputs(Event $event, bool $pflicht = false): array
    {
        $optionen = collect($event->preise())->map(fn (array $p) => $p['bezeichnung'].' – '.Zahlungen::euro($p['cent']))->all();

        return $optionen === [] ? [] : [
            Select::make('preis')->label('Teilnahmebeitrag')->options($optionen)->required($pflicht)
                ->placeholder('ohne Beitrag')->helperText($pflicht ? null : 'Bei bestätigter Anmeldung entsteht daraus eine Forderung (Funktion „Zahlungen“).'),
        ];
    }

    private static function namiNutzer(): ?User
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Eingabefelder für die bei der Veranstaltung abgefragten Angaben.
     *
     * @return list<Component>
     */
    private function eventFieldInputs(Event $event): array
    {
        return collect($event->formFields())->map(function (array $field, string $key) use ($event) {
            $name = $key === 'birth_year' ? 'birth_year' : 'data.'.$key;

            return match ($field['type']) {
                'year' => TextInput::make($name)->label($field['label'])->numeric()->minValue(1900)->maxValue((int) date('Y')),
                'plz' => TextInput::make($name)->label($field['label'])->regex('/^\d{4,5}$/')->maxLength(5),
                'select' => Select::make($name)->label($field['label'])->options($field['options'] ?? []),
                'stamm' => TextInput::make($name)->label($field['label'])->maxLength(150)
                    ->datalist(Gruppierung::query()->active()->orderBy('typ')->orderBy('name')->get()
                        ->map(fn (Gruppierung $g) => $g->typ === 'bezirk' ? 'Bezirk '.$g->name : $g->name)->all()),
                'herkunft' => Select::make($name)->label($field['label'])->options($field['options'] ?? [])->default($field['default'] ?? null)->selectablePlaceholder(false),
                'days' => CheckboxList::make($name)->label($field['label'])->options($event->days())->columns(2),
                'textarea' => Textarea::make($name)->label($field['label'])->rows(2)->maxLength(1000),
                default => TextInput::make($name)->label($field['label'])->maxLength(255),
            };
        })->values()->all();
    }

    /**
     * @return list<Component>
     */
    private function manualStatusInputs(Event $event): array
    {
        return [
            Select::make('status')->label('Status')->required()->default('auto')->options([
                'auto' => $event->capacity ? 'Bestätigt, solange Plätze frei sind (noch '.$event->spotsLeft().'), danach Warteliste' : 'Bestätigt',
                'confirmed' => 'Bestätigt – auch über die Platzzahl hinaus',
                'waitlist' => 'Warteliste',
            ]),
            Toggle::make('notify')->label('Bestätigung per E-Mail schicken (nur wer eine Adresse hat)'),
        ];
    }

    /** Wie nach der Online-Bestätigung: neue Anmeldungen abgleichen (nur mit technischem Konto). */
    private function afterManualAdd(Event $event): void
    {
        if ($event->checksMembers() && ! NamiVerzeichnis::persoenlich()) {
            CheckEventMembers::dispatch($event->getKey());
        }
    }

    private function veranstaltung(): Event
    {
        $event = $this->getOwnerRecord();
        abort_unless($event instanceof Event, 404);

        return $event;
    }

    /** Gespeicherten NaMi-Zugang nur nutzen, solange die Funktion eingeschaltet ist. */
    private static function gespeicherterZugang(?User $user): bool
    {
        return $user !== null && $user->hasNamiZugang() && Features::enabled(Features::NAMI_PROFIL);
    }
}
