<?php

namespace App\Filament\Resources\Material\Pages;

use App\Filament\Resources\Material\ReservierungResource;
use App\Models\MaterialReservierung;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListReservierungen extends ListRecords
{
    protected static string $resource = ReservierungResource::class;

    public function getTabs(): array
    {
        return [
            'anfragen' => Tab::make('Anfragen')->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'angefragt')),
            'kommend' => Tab::make('Zugesagt')->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'zugesagt')),
            'unterwegs' => Tab::make('Ausgegeben')->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'ausgegeben')),
            'erledigt' => Tab::make('Erledigt')->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['zurueck', 'abgelehnt'])->reorder('von', 'desc')),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return MaterialReservierung::query()->where('status', 'angefragt')->exists() ? 'anfragen' : 'kommend';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Neue Reservierung')];
    }
}
