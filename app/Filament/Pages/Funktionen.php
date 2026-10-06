<?php

namespace App\Filament\Pages;

use App\Models\FeatureSwitch;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Features;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Funktionen an- und abschalten (Admins). Ohne Schalter gilt der Standard
 * der Instanz; wer den Standard wieder wählt, entfernt den Schalter.
 * Abgeschaltete Funktionen verschwinden aus Website und Backend, ihre
 * Daten bleiben erhalten.
 *
 * @property Schema $form
 */
class Funktionen extends Page
{
    protected string $view = 'filament.pages.funktionen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Verwaltung';

    protected static ?string $navigationLabel = 'Funktionen';

    protected static ?string $title = 'Funktionen an- und abschalten';

    protected static ?string $slug = 'funktionen';

    protected static ?int $navigationSort = 45;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $this->form->fill(['schalter' => collect(Features::KATALOG)->mapWithKeys(fn ($f, string $key) => [$key => Features::enabled($key)])->all()]);
    }

    public function form(Schema $schema): Schema
    {
        $geaendert = FeatureSwitch::query()->with('updatedBy')->get()->keyBy('key');

        return $schema
            ->statePath('data')
            ->components(collect(Features::KATALOG)->groupBy('bereich', preserveKeys: true)
                ->map(fn ($funktionen, string $bereich) => Section::make($bereich)->columns(1)->components(
                    $funktionen->map(fn (array $f, string $key) => Toggle::make("schalter.{$key}")
                        ->label($f['label'])
                        ->helperText(self::hinweis($key, $f, $geaendert->get($key))))->values()->all()
                ))->values()->all());
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $user = Filament::auth()->user();
        $werte = (array) ($this->form->getState()['schalter'] ?? []);

        $geaendert = [];
        foreach (array_keys(Features::KATALOG) as $key) {
            $an = (bool) ($werte[$key] ?? false);
            if ($an === Features::enabled($key)) {
                continue;
            }
            // Gleich dem Standard: Schalter entfernen, sonst setzen.
            Features::setzen($key, $an === Features::standard($key) ? null : $an, $user?->getKey());
            AuditLogger::log($an ? 'feature.an' : 'feature.aus', $user instanceof User ? $user : null, 'feature', $key, request());
            $geaendert[] = Features::KATALOG[$key]['label'].($an ? ' an' : ' aus');
        }

        if ($geaendert === []) {
            Notification::make()->title('Nichts geändert.')->send();

            return;
        }
        Notification::make()->success()->title('Gespeichert')->body(implode(' · ', $geaendert).'. Wirkt ab dem nächsten Seitenaufruf.')->send();
        $this->mount();
    }

    /**
     * @param  array{text: string, braucht?: string}  $f
     */
    private static function hinweis(string $key, array $f, ?FeatureSwitch $schalter): string
    {
        $teile = [$f['text'], 'Standard dieser Website: '.(Features::standard($key) ? 'an' : 'aus').'.'];
        if ($schalter) {
            $teile[] = 'Geändert am '.$schalter->updated_at?->format('d.m.Y').($schalter->updatedBy ? ' von '.$schalter->updatedBy->name : '').'.';
        }
        if (($f['braucht'] ?? null) && $key === Features::MITGLIEDERABGLEICH && ! in_array(config('mitglieder.driver'), ['nami', 'hitobito'], true)) {
            $teile[] = 'Wirkt erst mit '.$f['braucht'].'.';
        }

        return implode(' ', $teile);
    }
}
