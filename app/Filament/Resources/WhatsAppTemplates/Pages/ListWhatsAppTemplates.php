<?php

namespace App\Filament\Resources\WhatsAppTemplates\Pages;

use App\Filament\Resources\WhatsAppTemplates\WhatsAppTemplateResource;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\WhatsAppException;
use App\Services\WhatsApp\WhatsAppSettings;
use App\Services\WhatsApp\WhatsAppTemplates;
use App\Support\AuditLogger;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;

class ListWhatsAppTemplates extends ListRecords
{
    protected static string $resource = WhatsAppTemplateResource::class;

    public function getSubheading(): ?string
    {
        return 'Neue Vorlagen prüft Meta, das dauert Minuten bis einen Tag. Der Status aktualisiert sich automatisch.';
    }

    protected function getHeaderActions(): array
    {
        $connected = fn () => app(WhatsAppSettings::class)->configured();

        return [
            Action::make('sync')
                ->label('Mit Meta abgleichen')
                ->icon('heroicon-o-arrow-path')
                ->visible($connected)
                ->action(function (WhatsAppTemplates $templates) {
                    try {
                        $n = $templates->sync();
                        Notification::make()->success()->title("{$n} Vorlage(n) abgeglichen")->send();
                    } catch (WhatsAppException $e) {
                        Notification::make()->danger()->title('Abgleich fehlgeschlagen')->body($e->getMessage())->send();
                    }
                }),
            Action::make('starter')
                ->label('Startvorlagen anlegen')
                ->icon('heroicon-o-sparkles')
                ->visible(fn () => $connected() && WhatsAppTemplate::query()->doesntExist())
                ->requiresConfirmation()
                ->modalDescription('Reicht drei Vorlagen bei Meta ein: Anmeldebestätigung (Service), Neuigkeit mit Link und Veranstaltungshinweis (beide Marketing).')
                ->action(function (WhatsAppTemplates $templates) {
                    $this->createMany($templates, WhatsAppTemplates::starter());
                }),
            Action::make('create')
                ->label('Vorlage einreichen')
                ->icon('heroicon-o-plus')
                ->visible($connected)
                ->schema([
                    TextInput::make('name')->label('Name')->required()->maxLength(60)
                        ->regex('/^[a-z0-9_]+$/')->helperText('Nur Kleinbuchstaben, Ziffern und _, z. B. dpsg_georgsbote_v1'),
                    Select::make('category')->label('Kategorie')->required()->default('MARKETING')
                        ->options(['MARKETING' => 'Marketing (Newsletter, kostenpflichtig)', 'UTILITY' => 'Service (z. B. Anmeldebestätigung)']),
                    Select::make('purpose')->label('Verwendung')->required()->default('newsletter')
                        ->options(['newsletter' => 'Newsletter-Aussendungen', 'doi' => 'Anmeldebestätigung']),
                    Textarea::make('body')->label('Text')->required()->maxLength(900)->rows(4)
                        ->helperText('Platzhalter als {{1}}, {{2}} … Feste Textteile sind Pflicht, sonst lehnt Meta ab.'),
                    Textarea::make('examples')->label('Beispielwerte für die Platzhalter')->rows(2)
                        ->helperText('Ein Wert pro Zeile, in der Reihenfolge {{1}}, {{2}} …'),
                    TextInput::make('footer')->label('Fußzeile')->default('Abmelden: STOP antworten')->maxLength(60),
                    Toggle::make('with_url')->label('Button mit Link zur Website')->default(true)->live(),
                    TextInput::make('url_text')->label('Button-Text')->default('Mehr lesen')->maxLength(25)
                        ->visible(fn (Get $get) => (bool) $get('with_url')),
                ])
                ->action(function (array $data, WhatsAppTemplates $templates) {
                    $base = rtrim((string) config('app.url'), '/').'/';
                    $this->createMany($templates, [[
                        'name' => $data['name'],
                        'category' => $data['category'],
                        'purpose' => $data['purpose'],
                        'body' => $data['body'],
                        'examples' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($data['examples'] ?? '')) ?: []))),
                        'footer' => $data['footer'] ?? null,
                        'url' => ! empty($data['with_url']) ? ['text' => $data['url_text'] ?: 'Mehr lesen', 'base' => $base, 'example' => $base.'aktuelles/beispiel'] : null,
                        'quick_replies' => [],
                    ]]);
                }),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $definitions
     */
    private function createMany(WhatsAppTemplates $templates, array $definitions): void
    {
        foreach ($definitions as $definition) {
            try {
                $templates->create($definition);
                AuditLogger::log('whatsapp.vorlage.eingereicht:'.$definition['name'], Filament::auth()->user(), request: request());
                Notification::make()->success()->title('Vorlage „'.$definition['name'].'“ eingereicht')->send();
            } catch (WhatsAppException $e) {
                Notification::make()->danger()->title('Vorlage „'.$definition['name'].'“ abgelehnt')->body($e->getMessage())->send();
            }
        }
    }
}
