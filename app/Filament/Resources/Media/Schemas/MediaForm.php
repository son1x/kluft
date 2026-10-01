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
                ->columnSpanFull(),

            TextInput::make('alt_text')
                ->label('Alt-Text (Barrierefreiheit)')
                ->required()
                ->maxLength(255)
                ->helperText('Beschreibt das Bild für Screenreader – Pflichtfeld.'),

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
