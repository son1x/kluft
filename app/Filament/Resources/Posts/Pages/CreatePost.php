<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Concerns\ZeigtBarrierefreiheitsHinweise;
use App\Filament\Resources\Posts\PostResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreatePost extends CreateRecord
{
    use ZeigtBarrierefreiheitsHinweise;

    protected static string $resource = PostResource::class;

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
