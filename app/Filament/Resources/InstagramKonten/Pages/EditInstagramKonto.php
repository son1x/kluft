<?php

namespace App\Filament\Resources\InstagramKonten\Pages;

use App\Filament\Resources\InstagramKonten\InstagramKontoResource;
use App\Models\InstagramKonto;
use App\Support\AuditLogger;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditInstagramKonto extends EditRecord
{
    use ZugangPruefen;

    protected static string $resource = InstagramKontoResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->zugangPruefen($data);
    }

    protected function afterSave(): void
    {
        AuditLogger::log('instagram.feed.konto', Filament::auth()->user(), InstagramKonto::class, $this->getRecord()->getKey(), request());
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
