<?php

namespace App\Support;

use Filament\Forms\Components\TextInput;

/**
 * URL-Pfade (Slugs) aus dem Backend bereinigen: Was beim Abtippen oder
 * Einfügen (z. B. aus Word oder Outlook) unsichtbar mitkommt, ließ die
 * Prüfung „nur Buchstaben, Ziffern, Binde- und Unterstriche“ scheitern,
 * obwohl der Slug richtig aussah. Nach dem Bereinigen erfüllt jeder Slug
 * diese Regel; bestehende gültige Slugs (auch mit Umlauten) bleiben gleich.
 */
class Slug
{
    public static function bereinigen(?string $slug): string
    {
        $slug = (string) $slug;
        // Unsichtbare Zeichen entfernen: weiche Trennzeichen, Zeichen ohne Breite,
        // Richtungsmarken (Teams/Outlook kopieren z. B. U+2066…U+2069 mit),
        // Varianten-Wähler (Steuerzeichen wie Tab werden unten zum Bindestrich).
        $slug = (string) preg_replace('/[\p{Cf}\p{Co}\p{Cn}\x{FE00}-\x{FE0F}]/u', '', $slug);
        // Geschützte Bindestriche, Gedanken- und Minusstriche → Bindestrich.
        $slug = (string) preg_replace('/[\x{2010}-\x{2015}\x{2212}]/u', '-', $slug);
        // Alles, was kein Buchstabe, keine Ziffer, kein Binde- oder Unterstrich
        // ist (Leerzeichen, Schrägstrich, Apostroph, Punkt, Emoji …) → Bindestrich.
        $slug = (string) preg_replace('/[^\pL\pM\pN_-]+/u', '-', $slug);
        // Doppelte Bindestriche zusammenfassen, Bindestriche am Rand weg.
        $slug = trim((string) preg_replace('/-{2,}/', '-', $slug), '-');

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
