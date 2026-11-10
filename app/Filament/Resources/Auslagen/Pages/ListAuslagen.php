<?php

namespace App\Filament\Resources\Auslagen\Pages;

use App\Filament\Resources\Auslagen\AuslageResource;
use App\Models\User;
use App\Support\Auslagen;
use App\Support\Zahlungen;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAuslagen extends ListRecords
{
    protected static string $resource = AuslageResource::class;

    private function kasse(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && Auslagen::kasse($user);
    }

    public function getSubheading(): ?string
    {
        return $this->kasse()
            ? 'Eingereichte Auslagen prüfen, freigeben und gesammelt überweisen (SEPA-Datei für das Online-Banking).'
            : 'Geld für den Verband ausgelegt oder gefahren? Hier einreichen; die Kasse prüft und überweist.';
    }

    public function getTabs(): array
    {
        if (! $this->kasse()) {
            return [];
        }

        return [
            'pruefen' => Tab::make('Zu prüfen')->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'eingereicht')),
            'auszahlen' => Tab::make('Zum Auszahlen')->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'freigegeben')),
            'erledigt' => Tab::make('Erledigt')->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['ausgezahlt', 'abgelehnt'])),
            'alle' => Tab::make('Alle'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('kmSatz')->label('Kilometersatz')->color('gray')->icon('heroicon-o-cog-6-tooth')
                ->visible(fn () => $this->kasse())
                ->fillForm(fn () => ['euro' => number_format(Auslagen::kmSatzCent() / 100, 2, '.', '')])
                ->schema([TextInput::make('euro')->label('Erstattung je Kilometer')->prefix('€')->numeric()->minValue(0.01)->maxValue(5)->step(0.01)->required()
                    ->helperText('Steuerfrei sind derzeit bis 0,30 € je km (Pkw).')])
                ->action(function (array $data) {
                    Auslagen::kmSatzSetzen((int) round((float) $data['euro'] * 100), Filament::auth()->id());
                    Notification::make()->title('Gespeichert: '.Zahlungen::euro(Auslagen::kmSatzCent()).' je km')->success()->send();
                }),
            CreateAction::make()->label('Auslage einreichen'),
        ];
    }
}
