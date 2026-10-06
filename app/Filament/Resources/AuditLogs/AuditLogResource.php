<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Post;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Support\AuditText;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Verwaltung → Protokoll: das Audit-Log zum Lesen (nur Admins). Keine
 * Bearbeitung, kein Löschen; Einträge verschwinden nach 12 Monaten
 * automatisch (audit:prune).
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Verwaltung';

    protected static ?string $navigationLabel = 'Protokoll';

    protected static ?string $modelLabel = 'Protokolleintrag';

    protected static ?string $pluralModelLabel = 'Protokoll';

    protected static ?string $slug = 'protokoll';

    protected static ?int $navigationSort = 90;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->columns([
                TextColumn::make('created_at')->label('Zeitpunkt')->dateTime('d.m.Y H:i:s')->sortable(),
                TextColumn::make('user.name')->label('Wer')->placeholder('System / Website')->searchable(),
                TextColumn::make('action')->label('Was')
                    ->formatStateUsing(fn (string $state) => AuditText::text($state))
                    ->description(fn (AuditLog $record) => ($b = AuditText::bereich($record->action)) ? AuditText::BEREICHE[$b]['label'] : null)
                    ->searchable()
                    ->wrap(),
                TextColumn::make('subject_id')->label('Betrifft')
                    ->state(fn (AuditLog $record) => self::betrifft($record)[0])
                    ->url(fn (AuditLog $record) => self::betrifft($record)[1])
                    ->placeholder('–')
                    ->wrap(),
                TextColumn::make('ip_truncated')->label('IP (gekürzt)')->placeholder('–')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('bereich')->label('Bereich')
                    ->options(collect(AuditText::BEREICHE)->map(fn (array $b) => $b['label'])->all())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null)
                        ? $query->where(fn (Builder $q) => collect(AuditText::BEREICHE[$data['value']]['prefixe'] ?? [])
                            ->each(fn (string $prefix) => $q->orWhere('action', 'like', $prefix.'%')))
                        : $query),
                SelectFilter::make('user_id')->label('Person')
                    ->relationship('user', 'name')->searchable()->preload(),
                Filter::make('zeitraum')
                    ->schema([
                        DatePicker::make('von')->label('Von'),
                        DatePicker::make('bis')->label('Bis'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['von'] ?? null, fn (Builder $q, $von) => $q->where('created_at', '>=', $von.' 00:00:00'))
                        ->when($data['bis'] ?? null, fn (Builder $q, $bis) => $q->where('created_at', '<=', $bis.' 23:59:59')))
                    ->indicateUsing(fn (array $data) => array_filter([
                        ($data['von'] ?? null) ? 'ab '.date('d.m.Y', (int) strtotime((string) $data['von'])) : null,
                        ($data['bis'] ?? null) ? 'bis '.date('d.m.Y', (int) strtotime((string) $data['bis'])) : null,
                    ])),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    /**
     * Betroffener Datensatz als Text und – falls noch vorhanden und erlaubt – Link.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private static function betrifft(AuditLog $record): array
    {
        if ($record->subject_id === null || blank($record->subject_type)) {
            return [null, null];
        }
        $id = $record->subject_id;

        [$model, $text, $url] = match ($record->subject_type) {
            'user', User::class => [$m = User::query()->find($id), $m ? 'Konto: '.$m->name : null, $m ? UserResource::getUrl('edit', ['record' => $m]) : null],
            'event', Event::class => [$m = Event::query()->find($id), $m ? 'Veranstaltung: '.$m->title : null, $m ? EventResource::getUrl('edit', ['record' => $m]) : null],
            EventRegistration::class => [$m = EventRegistration::query()->with('event')->find($id), $m ? 'Anmeldung von '.$m->fullName().' ('.$m->event?->title.')' : null, $m?->event ? EventResource::getUrl('edit', ['record' => $m->event]) : null],
            Post::class => [$m = Post::query()->find($id), $m ? 'Beitrag: '.$m->title : null, $m ? PostResource::getUrl('edit', ['record' => $m]) : null],
            'whatsapp_campaign' => [$m = WhatsAppCampaign::query()->find($id), $m ? 'WhatsApp-Aussendung #'.$m->id : null, $m ? WhatsAppCampaignResource::getUrl('edit', ['record' => $m]) : null],
            default => [null, null, null],
        };

        if (! $model instanceof Model) {
            $art = [
                'user' => 'Konto', User::class => 'Konto', 'event' => 'Veranstaltung', Event::class => 'Veranstaltung',
                EventRegistration::class => 'Anmeldung', Post::class => 'Beitrag', 'whatsapp_campaign' => 'WhatsApp-Aussendung',
            ][$record->subject_type] ?? class_basename((string) $record->subject_type);

            return [$art.' #'.$id.' (gelöscht)', null];
        }

        // Link nur, wenn die angemeldete Person den Datensatz auch öffnen darf.
        $ziel = $model instanceof EventRegistration ? $model->event : $model;

        return [$text, $ziel && Filament::auth()->user()?->can('update', $ziel) ? $url : null];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
        ];
    }
}
