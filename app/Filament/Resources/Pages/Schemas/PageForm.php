<?php

namespace App\Filament\Resources\Pages\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->components([
                    TextInput::make('title')
                        ->label('Titel')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, $set, $get) => $get('slug') ?: $set(
                            'slug',
                            str($state)->slug()
                        ))
                        ->columnSpan(2),

                    TextInput::make('slug')
                        ->label('URL-Pfad (Slug)')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->alphaDash()
                        ->helperText('Erreichbar unter /{slug}'),

                    Select::make('parent_id')
                        ->label('Übergeordnete Seite')
                        ->relationship('parent', 'title')
                        ->searchable()
                        ->preload(),

                    Select::make('stage')
                        ->label('Stufe')
                        ->options([
                            'biber' => 'Biber',
                            'woelflinge' => 'Wölflinge',
                            'jupfi' => 'Jungpfadfinder',
                            'pfadi' => 'Pfadfinder',
                            'rover' => 'Rover',
                        ])
                        ->native(false)
                        ->helperText('Nur setzen, wenn diese Seite zu einer bestimmten Stufe gehört.'),

                    Select::make('status')
                        ->label('Status')
                        ->options(self::statusOptions())
                        ->default('draft')
                        ->native(false)
                        ->required(),

                    Toggle::make('show_in_nav')
                        ->label('In Hauptnavigation anzeigen'),

                    TextInput::make('nav_sort')
                        ->label('Reihenfolge in der Navigation')
                        ->numeric()
                        ->default(0),
                ]),

            Section::make('Inhalt')
                ->components([
                    RichEditor::make('body')
                        ->label('Inhalt')
                        ->required()
                        ->columnSpanFull(),
                ]),

            Section::make('SEO')
                ->collapsed()
                ->components([
                    TextInput::make('seo_title')
                        ->label('SEO-Titel')
                        ->maxLength(255),
                    Textarea::make('seo_description')
                        ->label('SEO-Beschreibung')
                        ->maxLength(500),
                ]),
        ]);
    }

    /**
     * Autoren/Stufenadmins ohne Verwaltungsrechte dürfen nicht direkt
     * veröffentlichen (Standard: Freigabe erforderlich, F-CMS-10).
     *
     * @return array<string, string>
     */
    private static function statusOptions(): array
    {
        $options = [
            'draft' => 'Entwurf',
            'review' => 'Zur Prüfung',
        ];

        $user = Filament::auth()->user();

        if ($user && ($user->isVerwaltung())) {
            $options['published'] = 'Veröffentlicht';
        }

        return $options;
    }
}
