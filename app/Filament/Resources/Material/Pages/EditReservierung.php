<?php

namespace App\Filament\Resources\Material\Pages;

use App\Filament\Resources\Material\ReservierungResource;
use App\Mail\MaterialMail;
use App\Models\MaterialReservierung;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Mail;

class EditReservierung extends EditRecord
{
    use Concerns\PrueftBelegung;

    protected static string $resource = ReservierungResource::class;

    private ?string $statusVorher = null;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var MaterialReservierung $r */
        $r = $this->record;
        $this->statusVorher = $r->status;
        $this->pruefen($data, $r->id);

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var MaterialReservierung $r */
        $r = $this->record;
        if ($r->status !== $this->statusVorher && in_array($r->status, ['zugesagt', 'abgelehnt'], true)
            && filled($r->email) && ($this->data['benachrichtigen'] ?? false)) {
            Mail::to($r->email, $r->name)->send(new MaterialMail($r, $r->status));
        }
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
