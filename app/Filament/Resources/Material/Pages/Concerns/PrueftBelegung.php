<?php

namespace App\Filament\Resources\Material\Pages\Concerns;

use App\Models\Material;
use App\Models\MaterialReservierung;
use Illuminate\Validation\ValidationException;

/** Zugesagt oder ausgegeben nur, wenn genug frei ist. */
trait PrueftBelegung
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function pruefen(array $data, ?int $ohne = null): void
    {
        if (! in_array($data['status'] ?? null, MaterialReservierung::FEST, true)) {
            return;
        }
        $material = Material::query()->findOrFail($data['material_id']);
        $frei = $material->frei($data['von'], $data['bis'], $ohne);
        if ((int) ($data['menge'] ?? 1) > $frei) {
            throw ValidationException::withMessages(['data.von' => "Im Zeitraum sind nur noch {$frei} von {$material->menge} frei. Als „angefragt“ speichern oder einen anderen Zeitraum wählen."]);
        }
    }
}
