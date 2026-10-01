<?php

namespace App\Filament\Resources\GeorgsboteIssues\Pages;

use App\Filament\Resources\GeorgsboteIssues\GeorgsboteIssueResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGeorgsboteIssues extends ListRecords
{
    protected static string $resource = GeorgsboteIssueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('oeffentlich')
                ->label('Öffentliches Archiv')
                ->url(route('georgsbote.index'))
                ->openUrlInNewTab()
                ->color('gray'),
            CreateAction::make(),
        ];
    }
}
