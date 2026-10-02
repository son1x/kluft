<?php

namespace App\Filament\Resources\TeamPhotos\Pages;

use App\Filament\Resources\TeamPhotos\TeamPhotoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTeamPhoto extends EditRecord
{
    protected static string $resource = TeamPhotoResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
