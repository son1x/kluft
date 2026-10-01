<?php

namespace App\Filament\Resources\GeorgsboteIssues\Schemas;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class GeorgsboteIssueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ausgabe')
                ->columns(3)
                ->components([
                    TextInput::make('year')
                        ->label('Jahrgang')
                        ->numeric()
                        ->minValue(1950)
                        ->maxValue(2100)
                        ->default(now()->year)
                        ->required(),
                    TextInput::make('issue_no')
                        ->label('Ausgabe Nr.')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(99)
                        ->required()
                        ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('year', $get('year')))
                        ->validationMessages(['unique' => 'Diese Ausgabe gibt es für den Jahrgang schon.']),
                    TextInput::make('title')
                        ->label('Titel / Schwerpunkt')
                        ->required()
                        ->maxLength(255),
                    FileUpload::make('pdf_path')
                        ->label('PDF')
                        ->disk('local')
                        ->directory('georgsbote')
                        ->visibility('private')
                        ->acceptedFileTypes(['application/pdf'])
                        ->maxSize(51200)
                        ->required()
                        ->downloadable()
                        ->columnSpan(2),
                    FileUpload::make('cover_path')
                        ->label('Titelbild (optional)')
                        ->disk('public')
                        ->directory('georgsbote')
                        ->image()
                        ->imageEditor()
                        ->maxSize(5120)
                        ->helperText('z. B. Screenshot der Titelseite'),
                ]),

            Section::make('Veröffentlichung')
                ->description('Vor der Veröffentlichung prüfen, ob Fotos, Namen und Adressen in der Ausgabe online gezeigt werden dürfen (Einwilligungen, Minderjährige). Ausgaben mit ungeklärtem Personenbezug bleiben offline.')
                ->columns(2)
                ->components([
                    Checkbox::make('privacy_checked')
                        ->label('Personenbezug geprüft: Fotos, Namen und Adressen dürfen veröffentlicht werden')
                        ->columnSpanFull(),
                    Toggle::make('published')
                        ->label('Im Archiv veröffentlichen')
                        ->live()
                        ->rule(fn (Get $get) => function (string $attribute, mixed $value, \Closure $fail) use ($get) {
                            if ($value && ! $get('privacy_checked')) {
                                $fail('Erst veröffentlichen, wenn der Personenbezug geprüft ist.');
                            }
                        }),
                    DateTimePicker::make('published_at')
                        ->label('Sichtbar ab (optional)')
                        ->seconds(false)
                        ->native(false),
                ]),
        ]);
    }
}
