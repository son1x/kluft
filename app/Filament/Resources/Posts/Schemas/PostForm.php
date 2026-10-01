<?php

namespace App\Filament\Resources\Posts\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PostForm
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
                        ->helperText('Erreichbar unter /aktuelles/{slug}'),

                    Select::make('scope')
                        ->label('Geltungsbereich')
                        ->options([
                            'general' => 'Allgemein',
                            'stage' => 'Stufe',
                            'section' => 'Sektion',
                            'vorstand' => 'Vorstand',
                        ])
                        ->default('general')
                        ->native(false)
                        ->required(),

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
                        ->visible(fn ($get) => $get('scope') === 'stage'),

                    Select::make('status')
                        ->label('Status')
                        ->options(self::statusOptions())
                        ->default('draft')
                        ->native(false)
                        ->required(),

                    Select::make('cover_media_id')
                        ->label('Titelbild')
                        ->relationship('coverMedia', 'alt_text')
                        ->searchable()
                        ->preload(),
                ]),

            Section::make('Inhalt')
                ->components([
                    Textarea::make('teaser')
                        ->label('Teaser (kurze Anrisstext für Übersichten)')
                        ->required()
                        ->maxLength(300)
                        ->columnSpanFull(),
                    RichEditor::make('body')
                        ->label('Inhalt')
                        ->required()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private static function statusOptions(): array
    {
        $options = [
            'draft' => 'Entwurf',
            'review' => 'Zur Prüfung',
        ];

        $user = Filament::auth()->user();

        if ($user && app(\App\Policies\PostPolicy::class)->publish($user)) {
            $options['published'] = 'Veröffentlicht';
        }

        return $options;
    }
}
