<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\Mitglieder\Mitglied;
use App\Services\Mitglieder\MitgliederverzeichnisException;
use App\Services\Mitglieder\NamiVerzeichnis;
use App\Support\AuditLogger;
use App\Support\NamiAmpel;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Persönlicher NaMi-Zugang (freiwillig): Mitgliedsnummer und Passwort
 * verschlüsselt im Profil, damit „Mitgliedschaft prüfen“ ohne Eingabe
 * geht. Gespeichert wird nur, wenn die Anmeldung klappt. Mit Ampel und
 * Selbsttest (Suche nach dem eigenen Namen, Ergebnis nur für einen selbst).
 *
 * @property Schema $form
 */
class NamiZugang extends Page
{
    protected string $view = 'filament.pages.nami-zugang';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Mein Konto';

    protected static ?string $navigationLabel = 'Mein NaMi-Zugang';

    protected static ?string $title = 'Mein NaMi-Zugang';

    protected static ?string $slug = 'nami';

    protected static ?int $navigationSort = 20;

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** Ergebnis des Selbsttests (nur in dieser Ansicht, nicht gespeichert). */
    public ?string $selbsttest = null;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->status === 'active' && NamiVerzeichnis::persoenlich();
    }

    public function mount(): void
    {
        $this->form->fill(['nami_user' => $this->user()->nami_user]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('nami_user')->label('Deine NaMi-Mitgliedsnummer')->required()->autocomplete('username')->maxLength(20),
                TextInput::make('nami_password')->label('Dein NaMi-Passwort')->password()->revealable()
                    ->required(fn () => ! $this->user()->hasNamiZugang())
                    ->autocomplete('current-password')
                    ->helperText(fn () => $this->user()->hasNamiZugang() ? 'Leer lassen, um das gespeicherte Passwort zu behalten.' : null),
            ]);
    }

    /** Anmeldung testen und nur bei Erfolg speichern. */
    public function save(): void
    {
        $user = $this->user();
        $state = $this->form->getState();
        $nummer = trim((string) $state['nami_user']);
        $passwort = filled($state['nami_password'] ?? null) ? (string) $state['nami_password'] : (string) $user->nami_password;

        try {
            (new NamiVerzeichnis)->anmelden($nummer, $passwort);
        } catch (MitgliederverzeichnisException $e) {
            Notification::make()->danger()->title('Nicht gespeichert: NaMi-Anmeldung fehlgeschlagen')->body($e->getMessage())->send();

            return;
        }

        $user->forceFill(['nami_user' => $nummer, 'nami_password' => $passwort, 'nami_checked_at' => now(), 'nami_error' => null])->save();
        $this->form->fill(['nami_user' => $nummer]);
        AuditLogger::log('nami.zugang.gespeichert', $user, request: request());

        Notification::make()->success()->title('NaMi-Zugang gespeichert')->body('Die Anmeldung hat geklappt. „Mitgliedschaft prüfen“ fragt nicht mehr nach dem Passwort.')->send();
    }

    /**
     * Anmeldung mit dem gespeicherten Zugang und Suche nach dem eigenen
     * Namen – zeigt, ob die Verbindung samt Suche wirklich funktioniert.
     */
    public function test(): void
    {
        $user = $this->user();
        $nami = new NamiVerzeichnis;
        try {
            $nami->anmeldenMitProfil($user);
            [$vorname, $nachname] = self::namensteile($user->name);
            $treffer = $nachname === '' ? [] : $nami->suche($vorname, $nachname);
        } catch (MitgliederverzeichnisException $e) {
            $this->selbsttest = null;
            Notification::make()->danger()->title('NaMi-Test fehlgeschlagen')->body($e->getMessage())->send();

            return;
        }

        $ich = collect($treffer)->first(fn (Mitglied $m) => $m->nummer === (string) $user->nami_user);
        $this->selbsttest = match (true) {
            $ich !== null => 'Anmeldung und Suche klappen: Du wirst in NaMi als „'.$ich->vorname.' '.$ich->nachname.'“ geführt'
                .($ich->gruppierung ? ' ('.$ich->gruppierung.')' : '').', '.($ich->aktiv ? 'aktives Mitglied' : 'nicht als aktives Mitglied').'.',
            $treffer !== [] => 'Anmeldung und Suche klappen: '.count($treffer).' Treffer für „'.$vorname.' '.$nachname.'“, deine Mitgliedsnummer war nicht dabei.',
            default => 'Anmeldung klappt; die Suche nach „'.trim($vorname.' '.$nachname).'“ (Name im Backend) fand niemanden.',
        };
        Notification::make()->success()->title('NaMi-Verbindung steht')->send();
    }

    public function delete(): void
    {
        $user = $this->user();
        $user->forceFill(['nami_user' => null, 'nami_password' => null, 'nami_checked_at' => null, 'nami_error' => null])->save();
        $this->form->fill(['nami_user' => null]);
        $this->selbsttest = null;
        AuditLogger::log('nami.zugang.geloescht', $user, request: request());

        Notification::make()->success()->title('NaMi-Zugang gelöscht')->send();
    }

    public function ampel(): HtmlString
    {
        return NamiAmpel::html($this->user());
    }

    public function gespeichert(): bool
    {
        return $this->user()->hasNamiZugang();
    }

    /** @return array{0: string, 1: string} erster Vorname, Nachname (letztes Wort) */
    private static function namensteile(?string $name): array
    {
        $teile = preg_split('/\s+/', trim((string) $name)) ?: [];

        return count($teile) < 2 ? [(string) ($teile[0] ?? ''), ''] : [(string) $teile[0], (string) end($teile)];
    }

    private function user(): User
    {
        $user = Filament::auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
