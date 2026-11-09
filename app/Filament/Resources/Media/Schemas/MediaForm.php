<?php

namespace App\Filament\Resources\Media\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MediaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('path')
                ->label('Datei')
                ->disk('public')
                ->directory('media')
                ->image()
                ->imageEditor()
                ->required()
                ->visibility('public')
                ->helperText('JPEG, PNG oder WebP. Für Titelbilder Querformat, mindestens 1600 Pixel breit; sie werden im Verhältnis 16 : 9 gezeigt. Handyfotos passen meist direkt. Mit „Bearbeiten“ (Stift im Vorschaubild) lässt sich das Bild zuschneiden.')
                ->columnSpanFull(),

            TextInput::make('alt_text')
                ->label('Alt-Text (Barrierefreiheit)')
                ->required()
                ->maxLength(255)
                ->helperText('Beschreibt in einem Satz, was zu sehen ist, z. B. „Wölflinge bauen am Lagerfeuer ein Zelt auf“. Wird vorgelesen, wenn jemand das Bild nicht sehen kann, und ist der Suchbegriff bei der Titelbild-Auswahl.'),

            TextInput::make('credit')
                ->label('Bildnachweis / Urheber:in')
                ->required()
                ->maxLength(255)
                ->helperText('Z. B. "Foto: Max Mustermann" – Pflicht laut CD-Leitfaden.'),

            Toggle::make('rights_confirmed')
                ->label('Einwilligung liegt vor (bei Personenfotos)')
                ->helperText('Bestätigt, dass abgebildete Personen (bzw. bei Minderjährigen die Personensorgeberechtigten) der Veröffentlichung zugestimmt haben.'),
        ]);
    }
}
