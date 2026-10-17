<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\Mitglieder\MitgliederverzeichnisException;
use App\Services\Mitglieder\NamiDiagnose;
use App\Services\Mitglieder\NamiVerzeichnis;
use App\Support\AuditLogger;
use App\Support\Beitraege;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Testabfrage gegen NaMi 2 (NamiDiagnose): zeigt, was NaMi wirklich
 * liefert, ohne Personendaten – zum Kopieren für die Entwicklung, etwa vor
 * der Umstellung auf NaMi 3. Angemeldet wird mit dem im Profil
 * gespeicherten Zugang oder einmalig mit Mitgliedsnummer und Passwort
 * (nicht gespeichert). Das Ergebnis wird nicht gespeichert.
 *
 * @property Schema $form
 */
class NamiTestabfrage extends Page
{
    protected string $view = 'filament.pages.nami-testabfrage';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = 'Verwaltung';

    protected static ?string $navigationLabel = 'NaMi-Testabfrage';

    protected static ?string $title = 'NaMi-Testabfrage';

    protected static ?string $slug = 'nami-testabfrage';

    protected static ?int $navigationSort = 92;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public ?string $bericht = null;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isActive() && config('mitglieder.driver') === 'nami' && ($user->isAdmin() || $user->isKasse());
    }

    public function mount(): void
    {
        $this->form->fill(['nummer' => Beitraege::stammNummer()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('nummer')->label('DPSG-Nummer der Gruppierung')->placeholder('z. B. 210601')->maxLength(12)
                    ->helperText('Eine Gruppierung, die du in NaMi siehst – meist dein Stamm. Leer: nur der Gruppierungsbaum.'),
                ...$this->mitEingabe() ? [
                    TextInput::make('nami_user')->label('Deine NaMi-Mitgliedsnummer')->required()->autocomplete('username'),
                    TextInput::make('nami_password')->label('Dein NaMi-Passwort')->password()->required()->autocomplete('current-password')
                        ->helperText('Nur für diese Abfrage, wird nicht gespeichert.'),
                ] : [],
            ]);
    }

    /** Persönliche Anmeldung ohne gespeicherten Zugang: Eingabe nötig. */
    public function mitEingabe(): bool
    {
        return NamiVerzeichnis::persoenlich() && ! $this->user()->hasNamiZugang();
    }

    public function abfragen(): void
    {
        abort_unless(static::canAccess(), 403);
        $state = $this->form->getState();
        $nami = new NamiVerzeichnis;
        try {
            if (NamiVerzeichnis::persoenlich() && $this->mitEingabe()) {
                $nami->anmelden((string) $state['nami_user'], (string) $state['nami_password']);
            } elseif (NamiVerzeichnis::persoenlich()) {
                $nami->anmeldenMitProfil($this->user());
            }
            $this->bericht = (new NamiDiagnose($nami))->bericht((string) ($state['nummer'] ?? ''));
        } catch (MitgliederverzeichnisException $e) {
            $this->bericht = null;
            Notification::make()->danger()->title('NaMi-Anmeldung fehlgeschlagen')->body($e->getMessage())->send();

            return;
        }
        AuditLogger::log('nami.testabfrage', $this->user(), request: request());
        Notification::make()->success()->title('Testabfrage fertig')->send();
    }

    private function user(): User
    {
        $user = Filament::auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
