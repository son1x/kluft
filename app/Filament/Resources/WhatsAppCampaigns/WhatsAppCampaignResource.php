<?php

namespace App\Filament\Resources\WhatsAppCampaigns;

use App\Filament\Resources\WhatsAppCampaigns\Pages\CreateWhatsAppCampaign;
use App\Filament\Resources\WhatsAppCampaigns\Pages\EditWhatsAppCampaign;
use App\Filament\Resources\WhatsAppCampaigns\Pages\ListWhatsAppCampaigns;
use App\Models\Post;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\WhatsAppCampaigns;
use App\Support\WhatsAppAccess;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * WhatsApp-Aussendungen mit Freigabe (F-WA-01 … 06).
 */
class WhatsAppCampaignResource extends Resource
{
    protected static ?string $model = WhatsAppCampaign::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'WhatsApp-Newsletter';

    protected static ?string $slug = 'whatsapp/aussendungen';

    protected static ?string $modelLabel = 'Aussendung';

    protected static ?string $pluralModelLabel = 'Aussendungen';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return WhatsAppAccess::draft(Filament::auth()->user());
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny() && $record instanceof WhatsAppCampaign && $record->status === 'draft';
    }

    public static function getNavigationBadge(): ?string
    {
        $n = static::canViewAny() ? WhatsAppCampaign::query()->where('status', 'review')->count() : 0;

        return $n > 0 ? (string) $n : null;
    }

    public static function form(Schema $schema): Schema
    {
        $locked = fn (?WhatsAppCampaign $record) => $record !== null && ! $record->isEditable();
        $template = fn (Get $get) => WhatsAppTemplate::query()->find($get('template_id'));

        return $schema->components([
            Section::make('Inhalt')
                ->columnSpanFull()
                ->columns(2)
                ->components([
                    TextInput::make('title')->label('Titel (nur intern)')->required()->maxLength(120)->disabled($locked),
                    Select::make('template_id')->label('Vorlage')->required()->live()->disabled($locked)
                        ->options(fn () => WhatsAppTemplate::query()->usable()->pluck('name', 'id'))
                        ->helperText('Nur von Meta genehmigte Newsletter-Vorlagen.'),
                    Select::make('post_id')->label('Beitrag verknüpfen (optional)')->live()->disabled($locked)->searchable()
                        ->options(fn () => Post::query()->published()->latest('published_at')->limit(50)->pluck('title', 'id'))
                        ->afterStateUpdated(function (?string $state, Set $set) {
                            if ($post = Post::query()->find($state)) {
                                $set('variables.body.0', str($post->title)->limit(200)->toString());
                                $set('variables.url', 'aktuelles/'.$post->slug);
                            }
                        })
                        ->helperText('Füllt Text und Link mit dem Beitrag vor.'),
                    DateTimePicker::make('scheduled_at')->label('Geplanter Versand (optional)')->seconds(false)->disabled($locked)
                        ->minDate(now())->helperText('Leer lassen: nach der Freigabe von Hand senden.'),
                    ...array_map(fn (int $i) => TextInput::make("variables.body.{$i}")
                        ->label('Text für {{'.($i + 1).'}}')
                        ->maxLength(300)
                        ->regex('/^[^\r\n\t]*$/')
                        ->validationMessages(['regex' => 'Ohne Zeilenumbrüche (Vorgabe von Meta).'])
                        ->live(onBlur: true)
                        ->disabled($locked)
                        ->visible(fn (Get $get) => ($template($get)?->bodyPlaceholders() ?? 0) > $i)
                        ->required(fn (Get $get) => ($template($get)?->bodyPlaceholders() ?? 0) > $i), range(0, 3)),
                    TextInput::make('variables.url')->label('Link des Buttons')
                        ->prefix(rtrim((string) config('app.url'), '/').'/')
                        ->regex('/^[A-Za-z0-9\-._~\/?=&%#]+$/')
                        ->disabled($locked)
                        ->visible(fn (Get $get) => $template($get)?->urlButton() !== null)
                        ->required(fn (Get $get) => $template($get)?->urlButton() !== null),
                ]),
            Section::make('Vorschau')
                ->columnSpanFull()
                ->components([
                    Placeholder::make('preview')->hiddenLabel()->content(function (Get $get) use ($template) {
                        $text = $template($get)?->render(array_values((array) ($get('variables.body') ?? []))) ?? 'Bitte eine Vorlage wählen.';
                        $footer = $template($get)?->component('FOOTER')['text'] ?? null;

                        return new HtmlString('<div style="max-width:28rem;white-space:pre-wrap;background:#dcf8c6;color:#111;border-radius:.6rem;padding:.75rem 1rem">'
                            .e($text).($footer ? '<div style="margin-top:.4rem;font-size:.8rem;color:#555">'.e($footer).'</div>' : '').'</div>');
                    }),
                    Placeholder::make('reach')->label('Reichweite')->content(function (WhatsAppCampaigns $c) {
                        $n = $c->recipients();

                        return "{$n} aktive Abonnent:innen · geschätzt ".number_format($c->estimate($n), 2, ',', '.').' € bei Meta';
                    }),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Titel')->searchable()->weight('bold')
                    ->description(fn (WhatsAppCampaign $record) => str($record->preview())->limit(80)),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => WhatsAppCampaign::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'draft' => 'gray', 'review' => 'warning', 'approved', 'scheduled' => 'info',
                        'sending' => 'primary', 'sent' => 'success', default => 'danger',
                    })
                    ->description(fn (WhatsAppCampaign $record) => $record->last_error),
                TextColumn::make('recipients')->label('Empfänger')->numeric(),
                TextColumn::make('delivered')->label('Zugestellt')->numeric(),
                TextColumn::make('failed')->label('Fehler')->numeric(),
                TextColumn::make('scheduled_at')->label('Geplant')->dateTime('d.m.Y H:i')->placeholder('–'),
                TextColumn::make('creator.name')->label('Erstellt von')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ...WhatsAppCampaignActions::all(),
                EditAction::make()->label(fn (WhatsAppCampaign $record) => $record->isEditable() ? 'Bearbeiten' : 'Ansehen'),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsAppCampaigns::route('/'),
            'create' => CreateWhatsAppCampaign::route('/create'),
            'edit' => EditWhatsAppCampaign::route('/{record}/edit'),
        ];
    }
}
