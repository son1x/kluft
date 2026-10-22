<?php

namespace App\Filament\Resources\Zahlungen\Pages;

use App\Filament\Resources\Beitraege\BeitragResource;
use App\Filament\Resources\Zahlungen\ZahlungResource;
use App\Support\AuditLogger;
use App\Support\Zahlungen;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Throwable;

class ListZahlungen extends ListRecords
{
    protected static string $resource = ZahlungResource::class;

    public function getSubheading(): ?string
    {
        $konto = Zahlungen::konto();

        return $konto === null
            ? 'Noch keine Bankverbindung hinterlegt – ohne sie gibt es keine Zahlungsaufforderung.'
            : 'Empfänger: '.$konto['inhaber'].', IBAN '.trim(chunk_split($konto['iban'], 4, ' '));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('neu')
                ->label('Neue Forderung')
                ->icon('heroicon-o-plus')
                ->disabled(fn () => Zahlungen::konto() === null)
                ->modalDescription('Legt eine Zahlung an, die zu keinem Modul gehört (z. B. Kostenbeitrag), und schickt auf Wunsch gleich die Zahlungsaufforderung mit GiroCode. Name und E-Mail werden nur für die Mail benutzt, nicht gespeichert.')
                ->schema([
                    TextInput::make('zweck')->label('Wofür')->required()->maxLength(140)->placeholder('z. B. Kostenbeitrag Sommerlager'),
                    TextInput::make('betrag')->label('Betrag')->suffix('€')->required()->rules(BeitragResource::EURO)->inputMode('decimal'),
                    DatePicker::make('faellig_am')->label('Fällig bis'),
                    Toggle::make('senden')->label('Zahlungsaufforderung per Mail schicken')->default(true)->live(),
                    TextInput::make('name')->label('Name')->maxLength(100)->required(fn ($get) => (bool) $get('senden'))->visible(fn ($get) => (bool) $get('senden')),
                    TextInput::make('email')->label('E-Mail')->email()->required(fn ($get) => (bool) $get('senden'))->visible(fn ($get) => (bool) $get('senden')),
                ])
                ->action(function (array $data) {
                    abort_unless(ZahlungResource::canViewAny(), 403);
                    $zahlung = Zahlungen::anlegen(null, (int) BeitragResource::alsCent($data['betrag']), 'ZA', (string) $data['zweck'], $data['faellig_am'] ?: null);
                    AuditLogger::log('zahlung.angelegt', Filament::auth()->user(), $zahlung::class, $zahlung->id, request());
                    if ($data['senden'] ?? false) {
                        try {
                            Zahlungen::auffordern($zahlung, (string) $data['email'], (string) $data['name']);
                        } catch (Throwable $e) {
                            Notification::make()->title('Angelegt, aber die Mail ging nicht raus')->body($e->getMessage())->warning()->send();

                            return;
                        }
                    }
                    Notification::make()->title('Forderung '.$zahlung->referenz.' angelegt'.(($data['senden'] ?? false) ? ' und verschickt.' : '.'))->success()->send();
                }),
            Action::make('konto')
                ->label('Bankverbindung')
                ->icon('heroicon-o-building-library')
                ->color('gray')
                ->modalDescription('Konto der Gliederung für Zahlungsaufforderungen und GiroCode. Steht nur in der Datenbank, nicht im Repository.')
                ->fillForm(fn () => Zahlungen::konto() ?? [])
                ->schema([
                    TextInput::make('inhaber')->label('Kontoinhaber')->required()->maxLength(70),
                    TextInput::make('iban')->label('IBAN')->required()->maxLength(42)
                        ->rule(fn () => function (string $attribute, mixed $value, Closure $fail) {
                            if (! Zahlungen::ibanGueltig((string) $value)) {
                                $fail('Die IBAN ist ungültig (Prüfsumme).');
                            }
                        }),
                    TextInput::make('bic')->label('BIC (optional)')->maxLength(11),
                ])
                ->action(function (array $data) {
                    abort_unless(ZahlungResource::canViewAny(), 403);
                    Zahlungen::kontoSetzen((string) $data['inhaber'], (string) $data['iban'], $data['bic'] ?? null, Filament::auth()->id());
                    AuditLogger::log('zahlung.konto', Filament::auth()->user(), request: request());
                    Notification::make()->title('Bankverbindung gespeichert.')->success()->send();
                }),
        ];
    }
}
