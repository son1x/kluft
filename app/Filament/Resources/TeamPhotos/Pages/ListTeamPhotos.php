<?php

namespace App\Filament\Resources\TeamPhotos\Pages;

use App\Filament\Resources\TeamPhotos\TeamPhotoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTeamPhotos extends ListRecords
{
    protected static string $resource = TeamPhotoResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
