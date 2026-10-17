<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Ausbau;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Ausbaustufe der Website (Admins): von der Visitenkarte bis zum
 * Vollausbau. Zurückschalten blendet nur aus; Inhalte bleiben erhalten.
 *
 * @property Schema $form
 */
class Ausbaustufe extends Page
{
    protected string $view = 'filament.pages.ausbaustufe';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquare3Stack3d;

    protected static string|UnitEnum|null $navigationGroup = 'Verwaltung';

    protected static ?string $navigationLabel = 'Ausbaustufe';

    protected static ?string $title = 'Ausbaustufe der Website';

    protected static ?string $slug = 'ausbaustufe';

    protected static ?int $navigationSort = 44;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $this->form->fill(['stufe' => Ausbau::stufe()]);
    }

    public function form(Schema $schema): Schema
    {
        $eintrag = SiteSetting::query()->with('updatedBy')->find('ausbau');

        return $schema
            ->statePath('data')
            ->components([
                Radio::make('stufe')->label('Was zeigt die Website?')->required()
                    ->options(collect(Ausbau::STUFEN)->mapWithKeys(fn (array $s, int $n) => [$n => $n.'. '.$s['name']])->all())
                    ->descriptions(collect(Ausbau::STUFEN)->mapWithKeys(fn (array $s, int $n) => [$n => $s['text']])->all())
                    ->helperText('Standard dieser Website: '.Ausbau::standard().'. '.Ausbau::name(Ausbau::standard()).'.'
                        .($eintrag ? ' Geändert am '.$eintrag->updated_at?->format('d.m.Y').($eintrag->updatedBy ? ' von '.$eintrag->updatedBy->name : '').'.' : '')),
            ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $user = Filament::auth()->user();
        $stufe = (int) ($this->form->getState()['stufe'] ?? Ausbau::stufe());

        if ($stufe === Ausbau::stufe()) {
            Notification::make()->title('Nichts geändert.')->send();

            return;
        }
        Ausbau::setzen($stufe, $user?->getKey());
        // subject_id ist numerisch: Stufe im Aktionsnamen, z. B. „ausbau:3“.
        AuditLogger::log('ausbau:'.$stufe, $user instanceof User ? $user : null, request: request());
        Notification::make()->success()->title('Gespeichert')
            ->body('Die Website zeigt jetzt Stufe '.$stufe.' („'.Ausbau::name($stufe).'“). Gespeicherte Inhalte bleiben erhalten.')->send();
        $this->mount();
    }
}
