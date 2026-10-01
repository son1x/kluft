<?php

namespace App\Filament\Resources\Media\Pages;

use App\Filament\Resources\Media\MediaResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateMedia extends CreateRecord
{
    protected static string $resource = MediaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $disk = Storage::disk('public');

        $data['mime'] = $disk->mimeType($data['path']);
        $data['size'] = $disk->size($data['path']);
        $data['uploaded_by'] = Filament::auth()->id();

        return $data;
    }
}
