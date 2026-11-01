<?php

namespace App\Filament\Resources\Zuschussgeber\Pages;

use App\Filament\Resources\Zuschussgeber\ZuschussgeberResource;
use App\Models\Zuschussgeber;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Str;

class ManageZuschussgeber extends ManageRecords
{
    protected static string $resource = ZuschussgeberResource::class;

    public function getSubheading(): ?string
    {
        return 'Welche Stellen bei Veranstaltungen zur Wahl stehen. Die Vorlagen haben feste Regeln und eigene Vordrucke; eigene Zuschussgeber rechnen nach den Angaben hier und bekommen einen allgemeinen Antrag.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Eigener Zuschussgeber')
            ->mutateDataUsing(function (array $data): array {
                $basis = 'eigen_'.Str::of((string) $data['name'])->slug('_')->limit(30, '')->rtrim('_');
                $key = $basis;
                for ($i = 2; Zuschussgeber::query()->where('key', $key)->exists(); $i++) {
                    $key = $basis.'_'.$i;
                }

                return $data + ['key' => $key, 'vorlage' => null, 'sort' => (int) Zuschussgeber::query()->max('sort') + 10];
            })];
    }
}
