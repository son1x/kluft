<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['author_id'] = Filament::auth()->id();

        if ($data['status'] === 'published') {
            $data['published_at'] = now();
            $data['reviewed_by'] = Filament::auth()->id();
        }

        return $data;
    }
}
