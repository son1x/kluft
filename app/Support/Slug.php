<?php

namespace App\Support;

use Filament\Forms\Components\TextInput;

/**
 * URL-Pfade (Slugs) aus dem Backend bereinigen: Was beim Abtippen oder
 * Einfügen (z. B. aus Word oder Outlook) unsichtbar mitkommt, ließ die
 * Prüfung „nur Buchstaben, Ziffern, Binde- und Unterstriche“ scheitern,
 * obwohl der Slug richtig aussah. Bewusst sanft: bestehende Slugs (auch mit
 * Umlauten) bleiben, wie sie sind.
 */
class Slug
{
    public static function bereinigen(?string $slug): string
    {
        $slug = (string) $slug;
        // Weiche Trennzeichen und Zeichen ohne Breite entfernen.
        $slug = (string) preg_replace('/[\x{00AD}\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u', '', $slug);
        // Geschützte Bindestriche, Gedanken- und Minusstriche → Bindestrich.
        $slug = (string) preg_replace('/[\x{2010}-\x{2015}\x{2212}]/u', '-', $slug);
        // Leerzeichen (auch geschützte) außen weg, innen → Bindestrich.
        $slug = trim((string) preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $slug));
        $slug = str_replace(' ', '-', $slug);
        // Schrägstriche und Satzzeichen am Rand, doppelte Bindestriche.
        $slug = (string) preg_replace('/-{2,}/', '-', trim($slug, '/-.,;:'));

        return $slug;
    }

    /** Slug-Feld: beim Verlassen bereinigen, vor Prüfung und Speichern ebenso. */
    public static function feld(TextInput $feld): TextInput
    {
        return $feld
            ->live(onBlur: true)
            ->afterStateUpdated(function ($state, $set) {
                if (is_string($state) && ($sauber = self::bereinigen($state)) !== $state) {
                    $set('slug', $sauber);
                }
            })
            ->mutateStateForValidationUsing(fn ($state) => is_string($state) ? self::bereinigen($state) : $state)
            ->dehydrateStateUsing(fn ($state) => is_string($state) ? self::bereinigen($state) : $state);
    }
}
