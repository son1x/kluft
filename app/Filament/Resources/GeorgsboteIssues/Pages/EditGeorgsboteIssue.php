<?php

namespace App\Filament\Resources\GeorgsboteIssues\Pages;

use App\Filament\Resources\GeorgsboteIssues\GeorgsboteIssueResource;
use App\Filament\Resources\GeorgsboteIssues\Pages\Concerns\TracksPrivacyCheck;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditGeorgsboteIssue extends EditRecord
{
    use TracksPrivacyCheck;

    protected static string $resource = GeorgsboteIssueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->after(function ($record) {
                    Storage::disk('local')->delete($record->pdf_path);
                    if ($record->cover_path) {
                        Storage::disk('public')->delete($record->cover_path);
                    }
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->withPrivacyCheck($data, $this->record->privacy_checked);
    }
}
