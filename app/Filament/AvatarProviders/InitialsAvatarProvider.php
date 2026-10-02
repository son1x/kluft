<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Avatar mit Initialen als eingebettetes SVG. Ersetzt Filaments Standard
 * (ui-avatars.com), der bei jedem Seitenaufruf Namen der Redaktion an einen
 * externen Dienst schickt (Pflichtenheft F-CON-02/06).
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model $record): string
    {
        $name = Filament::getNameForDefaultAvatar($record);
        $initials = Str::of($name)->squish()->explode(' ')
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->take(2)
            ->implode('');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="#003056"/>'
            .'<text x="50%" y="50%" dy=".35em" text-anchor="middle" fill="#ffffff" font-family="sans-serif" font-size="26" font-weight="bold">'
            .e($initials ?: '?')
            .'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
