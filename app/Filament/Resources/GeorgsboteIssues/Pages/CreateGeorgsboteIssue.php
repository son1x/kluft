<?php

namespace App\Filament\Resources\GeorgsboteIssues\Pages;

use App\Filament\Resources\GeorgsboteIssues\GeorgsboteIssueResource;
use App\Filament\Resources\GeorgsboteIssues\Pages\Concerns\TracksPrivacyCheck;
use Filament\Resources\Pages\CreateRecord;

class CreateGeorgsboteIssue extends CreateRecord
{
    use TracksPrivacyCheck;

    protected static string $resource = GeorgsboteIssueResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->withPrivacyCheck($data);
    }
}
