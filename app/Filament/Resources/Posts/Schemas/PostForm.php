<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Models\Event;
use App\Models\Post;
use App\Policies\PostPolicy;
use App\Support\RoleCatalog;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            // Aus WordPress übernommene Beiträge: Nacharbeitsliste und
            // Durchsicht vor der Veröffentlichung (F-MIG-04/06).
            Section::make('Aus WordPress importiert')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (?Post $record) => $record?->wp_id !== null)
                ->columnSpanFull()
                ->components([
                    Placeholder::make('import_issues_list')
                        ->label('Bitte vor der Veröffentlichung prüfen')
                        ->content(fn (?Post $record) => new HtmlString(
                            '<ul class="list-disc space-y-1 pl-5 text-sm">'
                            .collect($record?->import_issues ?? [])->map(fn (string $i) => '<li>'.e($i).'</li>')->implode('')
                            .'</ul>'
                            .($record?->wp_url ? '<p class="mt-2 text-sm">Original: <a class="underline" target="_blank" rel="noopener" href="'.e($record->wp_url).'">'.e($record->wp_url).'</a></p>' : '')
                        )),
                    Toggle::make('import_reviewed')
                        ->label('Durchgesehen: Personenbezug, Bildrechte und Links sind geprüft')
                        ->helperText('Erst danach lässt sich der Beitrag zur Prüfung geben oder veröffentlichen.'),
                ]),

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
                        ->live()
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

                    Select::make('section')
                        ->label('Sektion')
                        ->options(fn () => self::sectionOptions())
                        ->native(false)
                        ->required(fn ($get) => $get('scope') === 'section')
                        ->visible(fn ($get) => $get('scope') === 'section'),

                    Select::make('status')
                        ->label('Status')
                        ->options(self::statusOptions())
                        ->default('draft')
                        ->native(false)
                        ->required()
                        ->rule(fn (Get $get, ?Post $record) => function (string $attribute, mixed $value, \Closure $fail) use ($get, $record) {
                            if ($record?->wp_id && $value !== 'draft' && ! $get('import_reviewed')) {
                                $fail('Importierte Beiträge erst durchsehen und oben „Durchgesehen“ anhaken.');
                            }
                        }),

                    Select::make('cover_media_id')
                        ->label('Titelbild')
                        ->relationship('coverMedia', 'alt_text')
                        ->searchable()
                        ->preload(),

                    Select::make('event_id')
                        ->label('Anmeldung zu Veranstaltung')
                        ->helperText('Zeigt im Beitrag einen auffälligen Kasten mit Termin, Anmeldeschluss und Button zur Anmeldung.')
                        ->relationship('event', 'title', fn (Builder $query) => $query->where('status', '!=', 'draft')->orderByDesc('starts_at'))
                        ->getOptionLabelFromRecordUsing(fn (Event $record) => $record->title.' ('.$record->dateLabel().')')
                        ->searchable(['title'])
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
                        ->helperText('Video oder Karte einbetten: Link auf YouTube, Vimeo, OpenStreetMap oder Google Maps allein in eine eigene Zeile setzen – auf der Website erscheint ein Platzhalter, der erst nach Klick lädt.')
                        ->columnSpanFull(),
                ]),

            // KI-Kennzeichnung nach CD-Leitfaden Abschnitt 13 (F-CMS-14).
            Section::make('KI-Kennzeichnung')
                ->description('Pflichtangabe laut CD-Leitfaden: Wurde beim Erstellen KI genutzt? Der Hinweis im Frontend wird automatisch erzeugt.')
                ->columns(2)
                ->components([
                    Radio::make('ai_level')
                        ->label('KI-Nutzung')
                        ->options(Post::AI_LEVELS)
                        ->default('none')
                        ->required()
                        ->live()
                        ->columnSpanFull(),
                    TextInput::make('ai_tool')
                        ->label('KI-Tool')
                        ->placeholder('z. B. ChatGPT, Claude, Midjourney')
                        ->maxLength(100)
                        ->live(onBlur: true)
                        ->visible(fn (Get $get) => in_array($get('ai_level'), ['partial', 'full'], true))
                        ->required(fn (Get $get) => in_array($get('ai_level'), ['partial', 'full'], true)),
                    Select::make('ai_content')
                        ->label('Was wurde generiert?')
                        ->options(array_combine(Post::AI_CONTENT_TYPES, Post::AI_CONTENT_TYPES))
                        ->default('Text')
                        ->native(false)
                        ->live()
                        ->visible(fn (Get $get) => $get('ai_level') === 'full')
                        ->required(fn (Get $get) => $get('ai_level') === 'full'),
                    TextInput::make('ai_person')
                        ->label('Erstellende Person')
                        ->placeholder(fn () => Filament::auth()->user()?->name)
                        ->helperText('Leer = Name der Autorin/des Autors.')
                        ->maxLength(150)
                        ->live(onBlur: true)
                        ->visible(fn (Get $get) => $get('ai_level') === 'partial'),
                    Placeholder::make('ai_preview')
                        ->label('Hinweis im Frontend')
                        ->content(fn (Get $get, ?Post $record) => (new Post([
                            'ai_level' => $get('ai_level'),
                            'ai_tool' => $get('ai_tool'),
                            'ai_content' => $get('ai_content'),
                            'ai_person' => $get('ai_person') ?: ($record?->author?->name ?? Filament::auth()->user()?->name),
                        ]))->aiNotice() ?? 'Kein Hinweis nötig.')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /**
     * Verwaltung/Vorstand: alle Sektionen; sonst nur Sektionen, in denen
     * die Person Sektionsadmin oder Autor:in ist.
     *
     * @return array<string, string>
     */
    private static function sectionOptions(): array
    {
        $user = Filament::auth()->user();
        $all = collect(RoleCatalog::sections());

        if (! $user || $user->isVerwaltung() || $user->hasRole('vorstand')) {
            return $all->all();
        }

        return $all->only([...$user->scopeKeysFor('sektionsadmin'), ...$user->scopeKeysFor('autor')])->all();
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

        if ($user && app(PostPolicy::class)->publish($user)) {
            $options['published'] = 'Veröffentlicht';
        }

        return $options;
    }
}
