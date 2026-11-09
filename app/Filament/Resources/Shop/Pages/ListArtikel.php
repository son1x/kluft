<?php

namespace App\Filament\Resources\Shop\Pages;

use App\Filament\Resources\Shop\ArtikelResource;
use App\Jobs\ArtikelHolen;
use App\Models\Shop\Artikel;
use App\Support\AuditLogger;
use App\Support\Sammelbestellung;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use InvalidArgumentException;

class ListArtikel extends ListRecords
{
    protected static string $resource = ArtikelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Eigenen Artikel anlegen')->icon('heroicon-o-plus')->color('gray')
                ->modalHeading('Eigener Artikel des Stamms')
                ->modalDescription('Z. B. Stammesaufnäher oder T-Shirts. Wird mitbestellt und bezahlt wie Rüsthaus-Artikel, steht aber nicht in der Sammelliste fürs Rüsthaus.')
                ->mutateDataUsing(fn (array $data) => $data + ['eigen' => true, 'nummer' => Artikel::eigeneNummer()])
                ->after(function (Artikel $record) {
                    $record->update(['preis_cent' => (int) $record->varianten()->min('preis_cent')]);
                    AuditLogger::log('sammelbestellung.artikel.eigen_angelegt', Filament::auth()->user(), 'shop_artikel', $record->id, request());
                }),
            Action::make('alleAktualisieren')->label('Alle aktualisieren')->icon('heroicon-o-arrow-path')->color('gray')
                ->visible(fn () => Artikel::query()->where('eigen', false)->exists())
                ->requiresConfirmation()
                ->modalDescription('Name, Beschreibung, Preise und Größen aller Artikel neu vom Rüsthaus holen. Geänderte Preise gelten für neue Bestellungen. Dauert etwa eine Sekunde je Größe.')
                ->action(function () {
                    abort_unless(ArtikelResource::canAccess(), 403);
                    $fehler = [];
                    foreach (Artikel::query()->where('eigen', false)->orderBy('id')->get() as $artikel) {
                        try {
                            Sammelbestellung::freigeben($artikel->url ?: $artikel->nummer, $artikel->freigegeben);
                        } catch (InvalidArgumentException $e) {
                            $fehler[] = $artikel->name.': '.$e->getMessage();
                        }
                    }
                    AuditLogger::log('sammelbestellung.artikel.aktualisiert', Filament::auth()->user(), 'shop_artikel', null, request());
                    $fehler === []
                        ? Notification::make()->title('Alle Artikel aktualisiert')->success()->send()
                        : Notification::make()->title('Nicht alle aktualisiert')->body(implode("\n", $fehler))->warning()->persistent()->send();
                }),
            Action::make('mehrereHolen')->label('Mehrere Artikel holen')->icon('heroicon-o-queue-list')->color('gray')
                ->modalHeading('Mehrere Artikel vom Rüsthaus holen')
                ->modalDescription('Artikelnummern oder Links, einer pro Zeile (Komma oder Leerzeichen gehen auch), höchstens '.ArtikelHolen::MAX.'. Kluft holt sie nacheinander im Hintergrund, etwa eine Sekunde je Größe. Den Stand siehst du oben auf dieser Seite.')
                ->disabled(fn () => (bool) (ArtikelHolen::stand()['laeuft'] ?? false))
                ->modalSubmitActionLabel('Holen')
                ->schema([
                    Textarea::make('nummern')->label('Rüsthaus-Artikelnummern oder Links')->rows(8)->required()
                        ->placeholder("0100003.30\n0100004\nhttps://www.ruesthaus.de/…")
                        ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail) {
                            $anzahl = count(ArtikelHolen::zerlegen((string) $value));
                            if ($anzahl === 0) {
                                $fail('Bitte mindestens eine Nummer oder einen Link eintragen.');
                            } elseif ($anzahl > ArtikelHolen::MAX) {
                                $fail('Höchstens '.ArtikelHolen::MAX.' auf einmal, es sind '.$anzahl.'.');
                            }
                        }),
                    Toggle::make('freigeben')->label('Gleich im Shop zeigen')->default(false)
                        ->helperText('Aus: Die Artikel kommen als Entwurf und erscheinen erst, wenn du in der Liste „Im Shop“ einschaltest.'),
                ])
                ->action(function (array $data) {
                    abort_unless(ArtikelResource::canAccess(), 403);
                    $eingaben = ArtikelHolen::zerlegen((string) $data['nummern']);
                    ArtikelHolen::starten($eingaben, (bool) ($data['freigeben'] ?? false));
                    AuditLogger::log('sammelbestellung.artikel.mehrere_holen', Filament::auth()->user(), 'shop_artikel', null, request());
                    Notification::make()->success()->title(count($eingaben).' Artikel werden geholt')
                        ->body('Das läuft im Hintergrund. Lade die Seite in ein paar Minuten neu, oben steht dann das Ergebnis.')->send();
                }),
            Action::make('holen')->label('Artikel holen')->icon('heroicon-o-arrow-down-tray')
                ->modalHeading('Artikel vom Rüsthaus holen und freigeben')
                ->modalDescription('Artikelnummer (auch einer bestimmten Größe) oder Link zur Produktseite. Name, Beschreibung, Bild, Preise und Größen werden gespeichert.')
                ->schema([TextInput::make('eingabe')->label('Rüsthaus-Artikelnummer oder Link')->required()->maxLength(300)->placeholder('z. B. 0100003.30')])
                ->action(function (array $data, Action $action) {
                    abort_unless(ArtikelResource::canAccess(), 403);
                    try {
                        $artikel = Sammelbestellung::freigeben($data['eingabe']);
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                        $action->halt();

                        return;
                    }
                    AuditLogger::log('sammelbestellung.artikel.freigegeben', Filament::auth()->user(), 'shop_artikel', $artikel->id, request());
                    Notification::make()->title('„'.$artikel->name.'“ mit '.$artikel->varianten->count().' Größen freigegeben')->success()->send();
                }),
        ];
    }

    /** Stand von „Mehrere Artikel holen“ unter der Überschrift. */
    public function getSubheading(): ?string
    {
        $stand = ArtikelHolen::stand();
        if ($stand === null) {
            return null;
        }
        if ($stand['laeuft']) {
            return "Mehrere Artikel holen: {$stand['erledigt']} von {$stand['gesamt']} erledigt … (Seite neu laden für den Stand)";
        }
        $text = 'Zuletzt geholt: '.count($stand['neu']).' von '.$stand['gesamt'];
        if ($stand['fehler'] !== []) {
            $text .= '. Nicht geklappt: '.implode('; ', array_slice($stand['fehler'], 0, 10)).(count($stand['fehler']) > 10 ? ' …' : '');
        }

        return $text.'.';
    }
}
