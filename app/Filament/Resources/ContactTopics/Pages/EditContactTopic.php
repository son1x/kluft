<?php

namespace App\Filament\Resources\ContactTopics\Pages;

use App\Filament\Resources\ContactTopics\ContactTopicResource;
use Filament\Resources\Pages\EditRecord;

class EditContactTopic extends EditRecord
{
    protected static string $resource = ContactTopicResource::class;
}
