<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Concerns\HasWebsiteTabs;
use App\Filament\Resources\Posts\PostResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPosts extends ListRecords
{
    use HasWebsiteTabs;

    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
