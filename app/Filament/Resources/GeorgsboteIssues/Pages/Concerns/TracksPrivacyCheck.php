<?php

namespace App\Filament\Resources\GeorgsboteIssues\Pages\Concerns;

use Filament\Facades\Filament;

/**
 * Dokumentiert, wer den Personenbezug wann geprüft hat (F-GB-03).
 */
trait TracksPrivacyCheck
{
    protected function withPrivacyCheck(array $data, bool $wasChecked = false): array
    {
        if (! empty($data['privacy_checked']) && ! $wasChecked) {
            $data['privacy_checked_by'] = Filament::auth()->id();
            $data['privacy_checked_at'] = now();
        }

        if (empty($data['privacy_checked'])) {
            $data['privacy_checked_by'] = null;
            $data['privacy_checked_at'] = null;
            $data['published'] = false;
        }

        return $data;
    }
}
