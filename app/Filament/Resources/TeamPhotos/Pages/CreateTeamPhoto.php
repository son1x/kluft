<?php

namespace App\Filament\Resources\TeamPhotos\Pages;

use App\Filament\Resources\TeamPhotos\TeamPhotoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTeamPhoto extends CreateRecord
{
    protected static string $resource = TeamPhotoResource::class;
}
