<?php

namespace App\Filament\Resources\Auslagen\Pages\Concerns;

use App\Models\Auslage;
use App\Support\Auslagen;
use App\Support\Zahlungen;

/**
 * Formularwerte ↔ Spalten: Betrag in Euro ↔ Cent, Bankverbindung als
 * verschlüsseltes Feld.
 */
trait AuslageFormular
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function zuSpalten(array $data, ?Auslage $record = null): array
    {
        $fahrt = ($data['art'] ?? 'betrag') === 'fahrt';
        $spalten = [
            'art' => $fahrt ? 'fahrt' : 'betrag',
            'anlass' => trim((string) $data['anlass']),
            'datum' => $data['datum'],
            'betrag_cent' => $fahrt ? null : (int) round(((float) str_replace(',', '.', (string) ($data['betrag'] ?? 0))) * 100),
            'km' => $fahrt ? round((float) str_replace(',', '.', (string) ($data['km'] ?? 0)), 1) : null,
            'km_satz_cent' => $fahrt ? ($record->km_satz_cent ?? Auslagen::kmSatzCent()) : null,
            'strecke' => $fahrt ? ($data['strecke'] ?? null) : null,
            'belege' => array_values((array) ($data['belege'] ?? [])),
            'konto' => ['inhaber' => trim((string) $data['konto_inhaber']), 'iban' => Zahlungen::ibanNormal((string) $data['konto_iban'])],
        ];

        return $spalten;
    }

    /**
     * @return array<string, mixed>
     */
    protected function zuFormular(Auslage $record): array
    {
        return [
            ...$record->only(['art', 'anlass', 'strecke', 'belege']),
            'datum' => $record->datum->toDateString(),
            'betrag' => $record->betrag_cent !== null ? number_format($record->betrag_cent / 100, 2, ',', '') : null,
            'km' => $record->km !== null ? str_replace('.', ',', (string) $record->km) : null,
            'konto_inhaber' => $record->konto['inhaber'] ?? null,
            'konto_iban' => $record->konto['iban'] ?? null,
        ];
    }
}
