<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;

/**
 * Was nicht bestellt werden darf, z. B. Gruppenzelte: eine Artikelnummer,
 * ein Nummernanfang oder eine Rüsthaus-Kategorie (erster Teil der Adresse).
 */
class Sperre extends Model
{
    protected $table = 'shop_sperren';

    public const TYPEN = ['nummer' => 'Artikelnummer', 'praefix' => 'Nummer beginnt mit', 'kategorie' => 'Rüsthaus-Kategorie'];

    protected $fillable = ['typ', 'wert', 'notiz'];

    /** Gesperrt? Nummer der Variante bzw. des Artikels und Kategorie aus der Rüsthaus-Adresse. */
    public static function greift(?string $nummer, ?string $kategorie = null): bool
    {
        $nummer = mb_strtolower(trim((string) $nummer));
        $kategorie = mb_strtolower(trim((string) $kategorie));

        foreach (self::query()->get() as $sperre) {
            $wert = mb_strtolower(trim($sperre->wert));
            if ($wert === '') {
                continue;
            }
            $treffer = match ($sperre->typ) {
                'nummer' => $nummer !== '' && $nummer === $wert,
                'praefix' => $nummer !== '' && str_starts_with($nummer, $wert),
                'kategorie' => $kategorie !== '' && $kategorie === $wert,
                default => false,
            };
            if ($treffer) {
                return true;
            }
        }

        return false;
    }
}
