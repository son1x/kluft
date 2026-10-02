<?php

namespace App\Filament\Resources\ContactTopics\Pages;

use App\Filament\Resources\ContactTopics\ContactTopicResource;
use Filament\Resources\Pages\CreateRecord;

class CreateContactTopic extends CreateRecord
{
    protected static string $resource = ContactTopicResource::class;
}
