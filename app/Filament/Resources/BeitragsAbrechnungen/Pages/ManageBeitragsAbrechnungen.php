<?php

namespace App\Filament\Resources\BeitragsAbrechnungen\Pages;

use App\Filament\Resources\BeitragsAbrechnungen\BeitragsAbrechnungResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBeitragsAbrechnungen extends ManageRecords
{
    protected static string $resource = BeitragsAbrechnungResource::class;

    public function getSubheading(): ?string
    {
        return 'Die Rechnung des Bundesverbands je Halbjahr neben dem, was in Kluft als Soll steht und schon eingegangen ist.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Rechnung eintragen')];
    }
}
