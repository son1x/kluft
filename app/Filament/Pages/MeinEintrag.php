<?php

namespace App\Filament\Pages;

use App\Filament\Resources\People\PersonResource;
use App\Models\Person;
use App\Models\User;
use App\Support\AuditLogger;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Mein Konto → Mein Eintrag auf der Website: Wer von der Verwaltung mit
 * einem Personen-Eintrag verknüpft ist (people.user_id), pflegt dort selbst
 * den Vorstellungstext, das Foto und die eigene Einwilligung zur
 * Veröffentlichung. Name, Funktion, Gremium, E-Mail und Reihenfolge bleiben
 * bei der Verwaltung. Änderungen gelten sofort und stehen im Audit-Log.
 *
 * @property Schema $form
 */
class MeinEintrag extends Page
{
    protected string $view = 'filament.pages.mein-eintrag';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Mein Konto';

    protected static ?string $navigationLabel = 'Mein Eintrag auf der Website';

    protected static ?string $title = 'Mein Eintrag auf der Website';

    protected static ?string $slug = 'mein-eintrag';

    protected static ?int $navigationSort = 3;

    /** Gewählter Eintrag, wenn eine Person mehrere hat. */
    public ?int $personId = null;

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** Nur mit verknüpftem Eintrag. */
    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isActive() && $user->personen()->exists();
    }

    public function mount(): void
    {
        $this->waehlen((int) $this->eintraege()->first()?->id);
    }

    /** Anderen eigenen Eintrag bearbeiten (bei mehreren). */
    public function waehlen(int $id): void
    {
        $person = $this->person($id);
        $this->personId = $person->id;
        $this->form->fill(['bio' => $person->bio, 'consent' => $person->consent]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Toggle::make('consent')
                    ->label('Mein Eintrag darf auf der Website erscheinen')
                    ->helperText('Name, Funktion, Text und Foto sind nur zu sehen, solange du das erlaubst. Ausschalten nimmt den Eintrag sofort von der Website.'),
                Textarea::make('bio')
                    ->label('Vorstellungstext')
                    ->rows(6)
                    ->maxLength(3000)
                    ->helperText('Absätze mit einer Leerzeile trennen. Lange Texte werden mit „Mehr lesen“ eingeklappt.'),
                FileUpload::make('portrait_upload')
                    ->label(fn () => $this->aktuellePerson()?->photo ? 'Neues Foto hochladen' : 'Foto hochladen')
                    ->image()
                    ->disk('public')->directory('media/portraits')->visibility('public')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(10240)
                    ->imageEditor()
                    ->imageEditorAspectRatios(['1:1'])
                    ->imageCropAspectRatio('1:1')
                    ->imageResizeTargetWidth('800')
                    ->imageResizeTargetHeight('800')
                    ->imageResizeMode('cover')
                    ->helperText('Wird rund angezeigt; beim Hochladen auf einen quadratischen Ausschnitt mit dem Gesicht in der Mitte zuschneiden.')
                    ->live(),
                TextInput::make('portrait_credit')
                    ->label('Bildnachweis (wer hat das Foto gemacht?)')
                    ->maxLength(150)
                    ->required(fn (Get $get) => filled($get('portrait_upload')))
                    ->visible(fn (Get $get) => filled($get('portrait_upload'))),
                Checkbox::make('bildrechte')
                    ->label('Ich darf dieses Foto veröffentlichen (eigenes Foto oder mit Erlaubnis der Person, die es gemacht hat).')
                    ->accepted(fn (Get $get) => filled($get('portrait_upload')))
                    ->validationMessages(['accepted' => 'Bitte bestätige, dass du das Foto veröffentlichen darfst.'])
                    ->visible(fn (Get $get) => filled($get('portrait_upload'))),
            ]);
    }

    public function speichern(): void
    {
        $person = $this->person((int) $this->personId);
        $daten = $this->form->getState();

        // Nur Text, Einwilligung und Foto; alles andere bleibt bei der Verwaltung.
        $werte = PersonResource::storePortrait([
            'name' => $person->name,
            'consent' => (bool) ($daten['bildrechte'] ?? false),
            'portrait_upload' => $daten['portrait_upload'] ?? null,
            'portrait_credit' => $daten['portrait_credit'] ?? null,
        ]);
        $person->forceFill([
            'bio' => filled($daten['bio'] ?? null) ? trim((string) $daten['bio']) : null,
            'consent' => (bool) ($daten['consent'] ?? false),
            ...isset($werte['photo_media_id']) ? ['photo_media_id' => $werte['photo_media_id']] : [],
        ])->save();

        AuditLogger::log('person.selbst_bearbeitet', $this->user(), Person::class, $person->id, request());
        $this->waehlen($person->id);
        Notification::make()->success()->title('Gespeichert')
            ->body($person->consent ? 'Deine Änderungen sind auf der Website zu sehen.' : 'Dein Eintrag ist auf der Website nicht zu sehen.')
            ->send();
    }

    /** Foto vom Eintrag nehmen (es bleibt in der Mediathek, dort kannst du es löschen). */
    public function fotoEntfernen(): void
    {
        $person = $this->person((int) $this->personId);
        $person->forceFill(['photo_media_id' => null])->save();
        AuditLogger::log('person.selbst_foto_entfernt', $this->user(), Person::class, $person->id, request());
        Notification::make()->success()->title('Foto entfernt')->body('Jetzt erscheint ein Kreis mit deinen Initialen.')->send();
    }

    /** @return Collection<int, Person> */
    public function eintraege(): Collection
    {
        return $this->user()->personen()->with('photo')->orderBy('sort')->orderBy('id')->get();
    }

    public function aktuellePerson(): ?Person
    {
        return $this->personId ? $this->eintraege()->firstWhere('id', $this->personId) : null;
    }

    /** Nur eigene Einträge; fremde IDs ergeben 403. */
    private function person(int $id): Person
    {
        $person = $this->user()->personen()->whereKey($id)->first();
        abort_unless($person instanceof Person, 403);

        return $person;
    }

    private function user(): User
    {
        $user = Filament::auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
