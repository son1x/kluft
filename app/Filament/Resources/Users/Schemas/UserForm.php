<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\RoleAssignment;
use App\Models\User;
use App\Services\DirectoryUnavailable;
use App\Services\EntraDirectory;
use App\Services\Mitglieder\MitgliederAbgleich;
use App\Services\Mitglieder\MitgliederverzeichnisException;
use App\Services\Mitglieder\MitgliedPruefung;
use App\Support\AuditLogger;
use App\Support\Features;
use App\Support\RoleCatalog;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class UserForm
{
    public const STATUS_OPTIONS = [
        'active' => 'Aktiv',
        'pending' => 'Wartet auf Freischaltung',
        'disabled' => 'Deaktiviert',
    ];

    public static function configure(Schema $schema): Schema
    {
        $actor = Filament::auth()->user();
        $global = RoleCatalog::canManageUsersGlobally($actor);
        $grantable = RoleCatalog::grantableBy($actor);
        $codeLogin = Features::enabled(Features::CODE_LOGIN);
        $rolle = fn (Get $get) => $codeLogin && $get('kind') === 'rolle';
        $perCode = fn (Get $get) => $codeLogin && $get('login') === 'email';

        return $schema->components([
            self::directorySection($codeLogin),

            Section::make($codeLogin ? 'Zugang' : 'M365-Konto')
                ->description($codeLogin
                    ? 'Für eine Person oder für eine Rolle wie „Stammesvorstand“. Anmeldung mit Code per E-Mail oder mit Microsoft 365. Ein Passwort gibt es nicht.'
                    : 'Die Anmeldung erfolgt ausschließlich über Microsoft 365. Wird ein Konto hier vorab angelegt, wird es bei der ersten Anmeldung über die E-Mail-Adresse verknüpft.')
                ->columns(2)
                ->components([
                    Radio::make('kind')
                        ->label('Art des Zugangs')
                        ->options(User::KIND_OPTIONS)
                        ->descriptions([
                            'person' => 'Eine bestimmte Person mit eigener Adresse; auf Wunsch in NaMi geprüft.',
                            'rolle' => 'Hängt an einem Funktionspostfach. Wer das Postfach hat, kommt hinein; beim Amtswechsel nur das Postfach übergeben.',
                        ])
                        ->default('person')
                        ->required()
                        ->live()
                        ->visible($codeLogin)
                        ->disabled(fn (?User $record) => $record !== null && ! $global)
                        ->columnSpanFull(),
                    TextInput::make('name')
                        ->label(fn (Get $get) => $rolle($get) ? 'Bezeichnung der Rolle' : 'Name')
                        ->placeholder(fn (Get $get) => $rolle($get) ? 'z. B. Stammesvorstand, Wölflingsleitung' : null)
                        ->required()
                        ->maxLength(255)
                        ->disabled(fn (?User $record) => $record !== null && ! $global),
                    Select::make('login')
                        ->label('Anmeldung')
                        ->options(User::LOGIN_OPTIONS)
                        ->default('email')
                        ->required()
                        ->native(false)
                        ->live()
                        ->visible($codeLogin)
                        ->disabled(fn (?User $record) => $record !== null && ! $global)
                        ->helperText(fn (Get $get) => $perCode($get)
                            ? 'Anmeldung unter '.route('auth.code').' mit einem Code, der an die Adresse geht.'
                            : 'Anmeldung mit dem Microsoft-365-Konto dieser Adresse.'),
                    TextInput::make('email')
                        ->label(fn (Get $get) => match (true) {
                            $rolle($get) => 'Funktionspostfach',
                            $perCode($get) => 'E-Mail-Adresse',
                            default => 'M365-E-Mail-Adresse',
                        })
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->disabled(fn (?User $record) => $record !== null && (! $global || filled($record->entra_oid)))
                        ->dehydrateStateUsing(fn (?string $state) => $state === null ? null : mb_strtolower(trim($state)))
                        ->helperText(fn (?User $record, Get $get) => match (true) {
                            filled($record?->entra_oid) => 'Bereits mit dem M365-Konto verknüpft – wird bei jeder Anmeldung aus Entra übernommen.',
                            $rolle($get) => 'z. B. vorstand@'.config('dpsg.site.domain').' – der Code geht an dieses Postfach.',
                            default => 'z. B. vorname.nachname@'.config('dpsg.site.domain'),
                        }),
                    TextInput::make('member_number')
                        ->label('DPSG-Mitgliedsnummer (optional)')
                        ->maxLength(20)
                        ->regex('/^[0-9 ]*$/')
                        ->dehydrateStateUsing(fn (?string $state) => filled($state) ? preg_replace('/\D/', '', $state) : null)
                        ->visible(fn (Get $get) => $codeLogin && ! $rolle($get))
                        ->disabled(fn (?User $record) => $record !== null && ! $global)
                        ->helperText(fn (?User $record) => $record?->member_checked_at
                            ? 'NaMi-Prüfung vom '.$record->member_checked_at->format('d.m.Y').': '.$record->member_check_result
                            : (MitgliederAbgleich::eingerichtet() ? 'Mit „In NaMi prüfen“ wird geschaut, ob die Person aktives Mitglied ist.' : null))
                        ->suffixAction(self::namiPruefen($actor)),
                    Select::make('status')
                        ->label('Status')
                        ->options(self::STATUS_OPTIONS)
                        ->default('active')
                        ->required()
                        ->native(false)
                        ->disabled(! $global)
                        ->helperText('Deaktivierte Konten verlieren sofort den Zugriff aufs Backend.'),
                    Placeholder::make('last_login_at')
                        ->label('Letzte Anmeldung')
                        ->content(fn (?User $record) => $record?->last_login_at?->format('d.m.Y H:i') ?? 'noch nie'),
                ]),

            Section::make('Rollen')
                ->description($global
                    ? 'Mehrere Zuweisungen je Person möglich. Jede Änderung wird im Audit-Log protokolliert.'
                    : 'Du kannst Autor:innen in deinem Bereich freischalten oder entziehen.')
                ->components([
                    Repeater::make('roleAssignments')
                        ->hiddenLabel()
                        ->relationship(
                            'roleAssignments',
                            modifyQueryUsing: fn (Builder $query) => $global ? $query : self::scopeToGrantable($query, $grantable),
                        )
                        ->schema([
                            Select::make('assignment')
                                ->hiddenLabel()
                                ->options($grantable)
                                ->in(array_keys($grantable))
                                ->required()
                                ->distinct()
                                ->native(false),
                        ])
                        ->mutateRelationshipDataBeforeFillUsing(fn (array $data) => ['assignment' => RoleCatalog::encode($data)])
                        ->mutateRelationshipDataBeforeCreateUsing(fn (array $data) => self::toColumns($data, $grantable))
                        ->mutateRelationshipDataBeforeSaveUsing(fn (array $data) => self::toColumns($data, $grantable))
                        ->addActionLabel('Rolle hinzufügen')
                        ->defaultItems(0),
                ]),
        ]);
    }

    /**
     * Suche im Entra-Verzeichnis (F-USR-02), nur beim Anlegen. Ohne
     * Verbindung bleibt die Eingabe per E-Mail-Adresse (F-USR-03).
     */
    private static function directorySection(bool $codeLogin): Section
    {
        $directory = app(EntraDirectory::class);

        return Section::make('Im M365-Verzeichnis suchen')
            ->description('Person per Name oder Adresse finden; Name und E-Mail werden übernommen und das Konto direkt verknüpft. Es werden nur Name, E-Mail und Konto-ID abgefragt.')
            ->visible(fn (?User $record, Get $get) => $record === null && (! $codeLogin || $get('login') === 'entra'))
            ->components([
                Select::make('directory_oid')
                    ->hiddenLabel()
                    ->placeholder('Name oder E-Mail-Adresse eingeben …')
                    ->searchable()
                    ->searchPrompt('Mindestens zwei Buchstaben eingeben')
                    ->noSearchResultsMessage('Niemand gefunden. Tippfehler? Sonst unten die Adresse von Hand eintragen.')
                    ->getSearchResultsUsing(fn (string $search) => self::directorySearch($directory, $search))
                    ->getOptionLabelUsing(fn (?string $value) => self::directoryLabel(session('entra.treffer.'.$value)) ?? $value)
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set) {
                        $person = $state ? session('entra.treffer.'.$state) : null;
                        if (is_array($person)) {
                            $set('name', $person['name']);
                            $set('email', $person['email']);
                        }
                    })
                    ->visible(fn () => $directory->connected()),
                Placeholder::make('directory_connect')
                    ->hiddenLabel()
                    ->content(new HtmlString('<a href="'.e(route('auth.entra.verzeichnis')).'" class="fi-link font-semibold text-primary-600 underline">Mit Microsoft verbinden und suchen</a> <span class="text-sm text-gray-500">– einmal pro Sitzung; Microsoft leitet sofort zurück.</span>'))
                    ->visible(fn () => ! $directory->connected()),
            ]);
    }

    /**
     * „In NaMi prüfen“: Ist die Person aktives Mitglied? Sucht über Vor- und
     * Nachname, bei angegebener Mitgliedsnummer muss sie passen. Gespeichert
     * wird nur Zeitpunkt, Ergebnis und ggf. die Nummer (Datensparsamkeit).
     */
    private static function namiPruefen(?User $actor): Action
    {
        return Action::make('namiPruefen')
            ->label('In NaMi prüfen')
            ->icon('heroicon-o-identification')
            ->visible(fn (?User $record) => $record !== null && MitgliederAbgleich::eingerichtet() && $actor !== null && RoleCatalog::canManageUsersGlobally($actor))
            ->action(function (?User $record, Get $get, Set $set) use ($actor) {
                if (! $record || ! $actor) {
                    return;
                }
                try {
                    $ergebnis = app(MitgliedPruefung::class)->pruefe($actor, (string) $get('name'), $get('member_number'));
                } catch (MitgliederverzeichnisException $e) {
                    Notification::make()->danger()->title('NaMi-Prüfung nicht möglich')->body($e->getMessage())->send();

                    return;
                }
                $record->forceFill([
                    'member_number' => $ergebnis['nummer'] ?? $record->member_number,
                    'member_checked_at' => now(),
                    'member_check_result' => $ergebnis['text'],
                ])->save();
                if (! empty($ergebnis['nummer'])) {
                    $set('member_number', $ergebnis['nummer']);
                }
                AuditLogger::log('user.nami_check', $actor, 'user', $record->id);
                Notification::make()
                    ->title($ergebnis['text'])
                    ->color($ergebnis['aktiv'] ? 'success' : 'warning')
                    ->icon($ergebnis['aktiv'] ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
                    ->send();
            });
    }

    /**
     * @return array<string, string>
     */
    private static function directorySearch(EntraDirectory $directory, string $search): array
    {
        try {
            $people = $directory->search($search);
        } catch (DirectoryUnavailable $e) {
            Notification::make()->warning()
                ->title('Verzeichnissuche nicht möglich ('.$e->getMessage().')')
                ->body(new HtmlString('Bitte <a href="'.e(route('auth.entra.verzeichnis')).'" class="underline">neu mit Microsoft verbinden</a> oder die Adresse von Hand eintragen.'))
                ->send();

            return [];
        }

        // Treffer für die Übernahme merken (nur in der Sitzung).
        $treffer = [];
        foreach ($people as $person) {
            $treffer[$person['id']] = $person;
        }
        session()->put('entra.treffer', $treffer);

        return array_map(fn (array $person) => self::directoryLabel($person), $treffer);
    }

    /**
     * @param  array{name: string, email: string}|mixed  $person
     */
    private static function directoryLabel(mixed $person): ?string
    {
        return is_array($person) ? $person['name'].' – '.$person['email'] : null;
    }

    /**
     * @param  array<string, string>  $grantable
     * @return array{role: string, scope_type: string, scope_key: ?string, granted_by: int|null}
     */
    private static function toColumns(array $data, array $grantable): array
    {
        // Zweite Prüfung zusätzlich zur Formular-Validierung: niemand darf
        // per manipuliertem Request eine Rolle außerhalb des eigenen
        // Vergaberechts setzen.
        if (! array_key_exists($data['assignment'] ?? '', $grantable)) {
            throw ValidationException::withMessages(['roleAssignments' => 'Diese Rolle darfst du nicht vergeben.']);
        }

        return [...RoleCatalog::decode($data['assignment']), 'granted_by' => Filament::auth()->id()];
    }

    /**
     * @param  Builder<RoleAssignment>  $query
     * @param  array<string, string>  $grantable
     */
    private static function scopeToGrantable(Builder $query, array $grantable): Builder
    {
        return $query->where(function (Builder $q) use ($grantable) {
            foreach (array_keys($grantable) as $key) {
                $parts = RoleCatalog::decode($key);
                $q->orWhere(fn (Builder $w) => $w
                    ->where('role', $parts['role'])
                    ->where('scope_type', $parts['scope_type'])
                    ->where('scope_key', $parts['scope_key']));
            }
            if ($grantable === []) {
                $q->whereRaw('1 = 0');
            }
        });
    }
}
