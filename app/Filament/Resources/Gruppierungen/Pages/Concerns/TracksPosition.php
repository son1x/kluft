<?php

namespace App\Filament\Resources\Gruppierungen\Pages\Concerns;

use Filament\Facades\Filament;

/**
 * Merkt sich, wer den Kartenpunkt gesetzt hat (8.2 position_gesetzt_von).
 */
trait TracksPosition
{
    protected function withPositionAuthor(array $data, ?float $oldLat = null, ?float $oldLng = null): array
    {
        $lat = filled($data['lat'] ?? null) ? (float) $data['lat'] : null;
        $lng = filled($data['lng'] ?? null) ? (float) $data['lng'] : null;

        if ($lat !== $oldLat || $lng !== $oldLng) {
            $data['position_gesetzt_von'] = $lat !== null && $lng !== null ? Filament::auth()->id() : null;
        }

        if ($lat === null || $lng === null) {
            $data['lat'] = $data['lng'] = null;
        }

        return $data;
    }
}
