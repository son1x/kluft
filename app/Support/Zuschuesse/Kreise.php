<?php

namespace App\Support\Zuschuesse;

/**
 * Saarländische Postleitzahlen → Kreis, wie im Vordruck „Antrag und Nachweis“
 * (Formular A: HOM, MZG, NK, SB, SLS, WND, sonst „Sonstige“). Quelle:
 * OpenPLZ API (amtliche Verzeichnisse), Stand 31.10.2026. 66822 (Lebach)
 * berührt auch den Kreis Neunkirchen; im Backend je Person korrigierbar.
 */
final class Kreise
{
    public const NAMEN = [
        'HOM' => 'Saarpfalz-Kreis',
        'MZG' => 'Merzig-Wadern',
        'NK' => 'Neunkirchen',
        'SB' => 'Regionalverband Saarbrücken',
        'SLS' => 'Saarlouis',
        'WND' => 'St. Wendel',
        'SONST' => 'Sonstige',
    ];

    /** Ganz oder überwiegend im Bistum Speyer (Saarpfalz-Kreis); die übrigen Kreise gehören zum Bistum Trier. */
    public const BISTUM_SPEYER = ['HOM'];

    private const PLZ = [
        '66111' => 'SB',
        '66113' => 'SB',
        '66115' => 'SB',
        '66117' => 'SB',
        '66119' => 'SB',
        '66121' => 'SB',
        '66123' => 'SB',
        '66125' => 'SB',
        '66126' => 'SB',
        '66127' => 'SB',
        '66128' => 'SB',
        '66129' => 'SB',
        '66130' => 'SB',
        '66131' => 'SB',
        '66132' => 'SB',
        '66133' => 'SB',
        '66265' => 'SB',
        '66271' => 'SB',
        '66280' => 'SB',
        '66287' => 'SB',
        '66292' => 'SB',
        '66299' => 'SB',
        '66333' => 'SB',
        '66346' => 'SB',
        '66352' => 'SB',
        '66359' => 'SLS',
        '66386' => 'HOM',
        '66399' => 'HOM',
        '66424' => 'HOM',
        '66440' => 'HOM',
        '66450' => 'HOM',
        '66453' => 'HOM',
        '66459' => 'HOM',
        '66538' => 'NK',
        '66539' => 'NK',
        '66540' => 'NK',
        '66557' => 'NK',
        '66564' => 'NK',
        '66571' => 'NK',
        '66578' => 'NK',
        '66583' => 'NK',
        '66589' => 'NK',
        '66606' => 'WND',
        '66620' => 'WND',
        '66625' => 'WND',
        '66629' => 'WND',
        '66636' => 'WND',
        '66640' => 'WND',
        '66646' => 'WND',
        '66649' => 'WND',
        '66663' => 'MZG',
        '66679' => 'MZG',
        '66687' => 'MZG',
        '66693' => 'MZG',
        '66701' => 'MZG',
        '66706' => 'MZG',
        '66709' => 'MZG',
        '66740' => 'SLS',
        '66763' => 'SLS',
        '66773' => 'SLS',
        '66780' => 'SLS',
        '66787' => 'SLS',
        '66793' => 'SLS',
        '66798' => 'SLS',
        '66802' => 'SLS',
        '66806' => 'SLS',
        '66809' => 'SLS',
        '66822' => 'SLS',
        '66839' => 'SLS',
    ];

    public static function ausPlz(?string $plz): string
    {
        return self::PLZ[trim((string) $plz)] ?? 'SONST';
    }
}
