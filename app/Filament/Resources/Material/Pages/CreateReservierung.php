<?php

namespace App\Filament\Resources\Material\Pages;

use App\Filament\Resources\Material\ReservierungResource;
use App\Mail\MaterialMail;
use App\Models\MaterialReservierung;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Mail;

class CreateReservierung extends CreateRecord
{
    use Concerns\PrueftBelegung;

    protected static string $resource = ReservierungResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pruefen($data);

        return [...$data, 'erstellt_von' => Filament::auth()->id()];
    }

    protected function afterCreate(): void
    {
        /** @var MaterialReservierung $r */
        $r = $this->record;
        if ($r->status === 'zugesagt' && filled($r->email) && ($this->data['benachrichtigen'] ?? false)) {
            Mail::to($r->email, $r->name)->send(new MaterialMail($r, 'zugesagt'));
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
