<?php

namespace App\Filament\Resources\Auslagen\Pages;

use App\Filament\Resources\Auslagen\AuslageResource;
use App\Filament\Resources\Auslagen\Pages\Concerns\AuslageFormular;
use App\Mail\AuslageMail;
use App\Models\Auslage;
use App\Models\User;
use App\Support\Auslagen;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Mail;

class CreateAuslage extends CreateRecord
{
    use AuslageFormular;

    protected static string $resource = AuslageResource::class;

    protected static ?string $title = 'Auslage einreichen';

    protected static bool $canCreateAnother = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var User $user */
        $user = Filament::auth()->user();
        $spalten = $this->zuSpalten($data);
        $user->forceFill(['auslagen_konto' => ($data['konto_merken'] ?? false) ? $spalten['konto'] : null])->save();

        return [...$spalten, 'user_id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'status' => 'eingereicht'];
    }

    protected function afterCreate(): void
    {
        /** @var Auslage $auslage */
        $auslage = $this->record;
        foreach (Auslagen::kassenteam() as $kasse) {
            if ($kasse->id !== $auslage->user_id) {
                Mail::to($kasse->email, $kasse->name)->send(new AuslageMail($auslage, 'eingereicht'));
            }
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Eingereicht. Die Kasse meldet sich per Mail.';
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Einreichen');
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
