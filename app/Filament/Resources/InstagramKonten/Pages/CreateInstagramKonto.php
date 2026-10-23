<?php

namespace App\Filament\Resources\InstagramKonten\Pages;

use App\Filament\Resources\InstagramKonten\InstagramKontoResource;
use App\Models\InstagramKonto;
use App\Services\Instagram\InstagramFeed;
use App\Support\AuditLogger;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateInstagramKonto extends CreateRecord
{
    use ZugangPruefen;

    protected static string $resource = InstagramKontoResource::class;

    protected function getHeaderActions(): array
    {
        return [ListInstagramKonten::anleitung()];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->zugangPruefen($data);
    }

    protected function afterCreate(): void
    {
        /** @var InstagramKonto $konto */
        $konto = $this->getRecord();
        AuditLogger::log('instagram.feed.konto', Filament::auth()->user(), InstagramKonto::class, $konto->id, request());
        app(InstagramFeed::class)->abrufen($konto);
    }
}
