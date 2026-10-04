<?php

namespace App\Filament\Resources\WhatsAppTemplates;

use App\Filament\Resources\WhatsAppTemplates\Pages\ListWhatsAppTemplates;
use App\Models\WhatsAppTemplate;
use App\Support\WhatsAppAccess;
use App\Support\WhatsAppMode;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Bei Meta genehmigte Nachrichtenvorlagen (F-WA-07). Nur mit ihnen darf
 * außerhalb des 24-Stunden-Fensters geschrieben werden.
 */
class WhatsAppTemplateResource extends Resource
{
    protected static ?string $model = WhatsAppTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|UnitEnum|null $navigationGroup = 'WhatsApp-Newsletter';

    protected static ?string $slug = 'whatsapp/vorlagen';

    protected static ?string $modelLabel = 'Vorlage';

    protected static ?string $pluralModelLabel = 'Vorlagen';

    protected static ?int $navigationSort = 2;

    public const STATUS_LABELS = [
        'APPROVED' => 'Genehmigt', 'PENDING' => 'In Prüfung', 'REJECTED' => 'Abgelehnt',
        'PAUSED' => 'Pausiert', 'DISABLED' => 'Deaktiviert', 'PENDING_DELETION' => 'Wird gelöscht',
    ];

    public static function canViewAny(): bool
    {
        return WhatsAppMode::api() && WhatsAppAccess::manage(Filament::auth()->user());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Name')->fontFamily('mono')->searchable()
                    ->description(fn (WhatsAppTemplate $record) => str($record->bodyText())->limit(90)),
                TextColumn::make('purpose')->label('Zweck')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'doi' ? 'Anmeldebestätigung' : 'Newsletter'),
                TextColumn::make('category')->label('Kategorie')->formatStateUsing(fn (string $state) => $state === 'UTILITY' ? 'Service' : 'Marketing (kostenpflichtig)'),
                TextColumn::make('status')->label('Status bei Meta')->badge()
                    ->formatStateUsing(fn (string $state) => self::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'APPROVED' => 'success', 'PENDING' => 'warning', default => 'danger',
                    })
                    ->description(fn (WhatsAppTemplate $record) => $record->rejected_reason),
                TextColumn::make('synced_at')->label('Abgeglichen')->since(),
            ])
            ->recordActions([
                Action::make('purpose')
                    ->label('Zweck')
                    ->icon('heroicon-o-tag')
                    ->schema([
                        Select::make('purpose')->label('Verwendung im CMS')->required()
                            ->options(['newsletter' => 'Newsletter-Aussendungen', 'doi' => 'Anmeldebestätigung (Formular-Weg)']),
                    ])
                    ->fillForm(fn (WhatsAppTemplate $record) => ['purpose' => $record->purpose])
                    ->action(fn (WhatsAppTemplate $record, array $data) => $record->update(['purpose' => $data['purpose']])),
            ])
            ->emptyStateHeading('Noch keine Vorlagen')
            ->emptyStateDescription('„Startvorlagen anlegen“ reicht die drei Standardvorlagen bei Meta zur Prüfung ein.');
    }

    public static function getPages(): array
    {
        return ['index' => ListWhatsAppTemplates::route('/')];
    }
}
