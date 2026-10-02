<?php

namespace App\Filament\Resources\ContactTopics\Pages;

use App\Filament\Resources\ContactTopics\ContactTopicResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListContactTopics extends ListRecords
{
    protected static string $resource = ContactTopicResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
