<?php

namespace App\Services\Zahlungen;

use App\Models\Zahlung;
use App\Support\Zahlungen;
use RuntimeException;

/** Zahlungsweg Überweisung: Kontodaten und GiroCode, Eingang hakt die Kasse ab. */
final class Ueberweisung implements Zahlungsweg
{
    public function schluessel(): string
    {
        return 'ueberweisung';
    }

    public function anfordern(Zahlung $zahlung): Zahlungsaufforderung
    {
        $konto = Zahlungen::konto();
        if ($konto === null) {
            throw new RuntimeException('Keine Bankverbindung hinterlegt (Kasse → Zahlungen → Bankverbindung).');
        }
        $betrag = $zahlung->offenCent() ?: $zahlung->betrag_cent;

        return new Zahlungsaufforderung(
            weg: $this->schluessel(),
            betragCent: $betrag,
            verwendungszweck: $zahlung->referenz,
            empfaenger: $konto['inhaber'],
            iban: $konto['iban'],
            bic: $konto['bic'] ?: null,
            qrPngBase64: GiroCode::png(GiroCode::nutzdaten($konto['inhaber'], $konto['iban'], $konto['bic'] ?: null, $betrag, $zahlung->referenz)),
        );
    }
}
