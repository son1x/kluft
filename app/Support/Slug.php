<?php

namespace App\Support;

use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * URL-Pfade (Slugs) aus dem Backend bereinigen: Was beim Abtippen oder
 * Einfügen (z. B. aus Word oder Outlook) unsichtbar mitkommt, ließ die
 * Prüfung „nur Buchstaben, Ziffern, Binde- und Unterstriche“ scheitern,
 * obwohl der Slug richtig aussah. Nach dem Bereinigen erfüllt jeder Slug
 * diese Regel; bestehende gültige Slugs (auch mit Umlauten) bleiben gleich.
 */
class Slug
{
    public static function bereinigen(\Stringable|string|null $slug): string
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

    /** Vorschlag aus dem Titel – als echter Text (str()->slug() liefert ein Objekt). */
    public static function ausTitel(mixed $titel): string
    {
        return self::bereinigen(Str::slug((string) $titel));
    }

    /**
     * Slug-Feld: beim Verlassen bereinigen, vor Prüfung und Speichern ebenso.
     * Auch ein Stringable-Objekt im Formularzustand (früherer Vorschlag aus dem
     * Titel) wird zu Text – Laravels alpha_dash lehnt alles ab, was kein Text
     * ist, und meldete dann „nur Buchstaben, Ziffern …“ bei gültigem Slug.
     */
    public static function feld(TextInput $feld): TextInput
    {
        return $feld
            ->live(onBlur: true)
            ->afterStateUpdated(function ($state, $set) {
                if (! self::textartig($state) || ($sauber = self::bereinigen($state)) === (string) $state) {
                    return;
                }
                $set('slug', $sauber);
                // Hinweis, wenn Unsichtbares dabei war (z. B. aus Teams/Outlook kopiert).
                if (preg_match('/[\p{Cf}\p{Co}\p{Cn}\x{FE00}-\x{FE0F}]/u', (string) $state)) {
                    Notification::make()->info()
                        ->title('Unsichtbare Zeichen entfernt')
                        ->body('Im URL-Pfad steckten unsichtbare Zeichen (z. B. aus Teams, Outlook oder Word kopiert). Sie wurden entfernt: „'.$sauber.'“.')
                        ->send();
                }
            })
            ->mutateStateForValidationUsing(fn ($state) => self::textartig($state) ? self::bereinigen($state) : $state)
            ->dehydrateStateUsing(fn ($state) => self::textartig($state) ? self::bereinigen($state) : $state);
    }

    private static function textartig(mixed $state): bool
    {
        return is_string($state) || $state instanceof \Stringable;
    }
}
