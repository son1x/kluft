<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Events\Schemas\EventForm;
use App\Models\Event;
use App\Services\Zuschuss\ZuschussExcel;
use App\Services\Zuschuss\ZuschussPdf;
use App\Support\AuditLogger;
use App\Support\Unterschriften;
use App\Support\Zuschuesse;
use App\Support\Zuschuesse\Antrag;
use App\Support\Zuschuesse\Foerderrechner;
use App\Support\Zuschuesse\Geber;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Gemeinsame Maske „Zuschussantrag“ (docs/pflichtenheft-zuschuesse.md,
 * Phase 4): alle Angaben für alle Anträge an einer Stelle, dazu Fristen,
 * Einreichung und Bewilligung je Zuschussgeber; die Unterlagen übernehmen
 * die Angaben und lassen sich einzeln oder gesammelt herunterladen.
 */
class ZuschussAntrag extends EditRecord
{
    protected static string $resource = EventResource::class;

    protected static ?string $title = 'Zuschussantrag';

    public static function canAccess(array $parameters = []): bool
    {
        return Zuschuesse::aktiv() && parent::canAccess($parameters);
    }

    public function getTitle(): string
    {
        return 'Zuschussantrag: '.$this->getRecord()->title;
    }

    public function getBreadcrumb(): string
    {
        return 'Zuschussantrag';
    }

    private function event(): Event
    {
        /** @var Event $event */
        $event = $this->getRecord();

        return $event;
    }

    private function darfUnterlagen(): bool
    {
        return (bool) Filament::auth()->user()?->can('manageParticipants', $this->event());
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Leere Felder mit dem füllen, was sonst auf den Anträgen stünde.
        $data['zuschuss_antrag'] = Antrag::werte($this->event());
        $data['als_standard'] = false;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($data['als_standard'] ?? false) {
            Antrag::standardSpeichern((array) ($data['zuschuss_antrag'] ?? []), Filament::auth()->id());
        }
        unset($data['als_standard']);
        // Vermerke wie „erinnert“ behalten, die nicht in der Maske stehen.
        $data['zuschuss_stand'] = array_replace_recursive((array) $this->event()->zuschuss_stand, (array) ($data['zuschuss_stand'] ?? []));

        return $data;
    }

    protected function afterSave(): void
    {
        AuditLogger::log('event.zuschuss_antrag', Filament::auth()->user(), Event::class, $this->event()->getKey(), request());
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }

    public function form(Schema $schema): Schema
    {
        $schaetzung = fn (string $key) => collect(Foerderrechner::rechnen($this->event())['geber'])->firstWhere('key', $key)['betrag'] ?? 0;
        $euroText = fn (int $cent) => number_format($cent / 100, 2, ',', '.').' €';

        return $schema->components([
            View::make('filament.zuschuss-antrag-stand')->columnSpanFull()
                ->viewData(fn () => [
                    'event' => $this->event(),
                    'fehlend' => $this->event()->zuschussAktiv() ? Antrag::fehlend($this->event()) : [],
                    'uebersicht' => $this->event()->zuschussAktiv() ? Antrag::uebersicht($this->event()) : [],
                ]),

            Section::make('Maßnahme')->columns(2)->columnSpanFull()->collapsible()
                ->components([
                    Toggle::make('zuschuss')->label('Zuschuss beantragen')->live()->columnSpanFull(),
                    Select::make('zuschuss_art')->label('Art der Maßnahme')->options(Zuschuesse::ARTEN)->native(false)
                        ->required(fn (Get $get) => (bool) $get('zuschuss')),
                    CheckboxList::make('zuschuss_geber')->label('Zuschussgeber')->options(fn () => Geber::auswahl($this->event()))->live()
                        ->helperText('Ohne Auswahl: alle eingeschalteten (Verwaltung → Zuschussgeber).'),
                    Toggle::make('zuschuss_fern')->label('Ziel weiter als 250 km entfernt')
                        ->helperText('Die Stadt Saarlouis fördert Lager dann mit 2,50 € statt 1,50 € je Tag.'),
                ]),

            Section::make('Antragsteller')->columns(2)->columnSpanFull()->collapsible()
                ->description('Steht auf allen Anträgen. Vorbelegt mit dem Standard des Stamms bzw. Kontakt und Konto der Website.')
                ->components([
                    ...collect(Antrag::FELDER)->map(fn (string $label, string $feld) => in_array($feld, ['anschrift', 'leitung_anschrift'], true)
                        ? Textarea::make("zuschuss_antrag.{$feld}")->label($label)->rows(2)->maxLength(300)
                        : TextInput::make("zuschuss_antrag.{$feld}")->label($label)->maxLength(200)
                            ->helperText(match ($feld) {
                                'vertretung' => 'Antrag der Stadt Saarlouis.',
                                'leitung_name' => 'Teilnehmerliste der Stadt Saarlouis.',
                                default => null,
                            }))->values()->all(),
                    Toggle::make('als_standard')->label('Antragsteller und Bank als Standard für neue Anträge merken')
                        ->helperText('Ohne Leitung der Maßnahme; die gilt nur für diese Veranstaltung.')->columnSpanFull(),
                ]),

            Section::make('Kosten, Programm und Bericht')->columnSpanFull()->collapsible()
                ->components(EventForm::zuschussFelder()),

            Section::make('Fristen und Bewilligungen')->columnSpanFull()->collapsible()
                ->description('Je Zuschussgeber. Ohne eigene Frist gilt die der Richtlinie; Kluft erinnert 14 und 3 Tage vorher, solange nichts eingereicht ist.')
                ->components(collect(Geber::auswahl($this->event()))->map(fn (string $name, string $key) => Fieldset::make($name)->columns(4)
                    ->visible(fn (Get $get) => in_array($key, (array) ($get('zuschuss_geber') ?: array_keys(Geber::aktiv())), true))
                    ->components([
                        DatePicker::make("zuschuss_stand.{$key}.frist")->label('Frist')->native(false)->displayFormat('d.m.Y')
                            ->placeholder(fn () => Antrag::regelfrist($this->event(), $key)->format('d.m.Y').' (Richtlinie)'),
                        DatePicker::make("zuschuss_stand.{$key}.eingereicht_am")->label('Eingereicht am')->native(false)->displayFormat('d.m.Y'),
                        EventForm::euro("zuschuss_stand.{$key}.beantragt_cent", 'Beantragt')
                            ->placeholder(fn () => 'Schätzung '.$euroText((int) $schaetzung($key))),
                        TextInput::make("zuschuss_stand.{$key}.aktenzeichen")->label('Aktenzeichen')->maxLength(100),
                        DatePicker::make("zuschuss_stand.{$key}.bewilligt_am")->label('Bescheid vom')->native(false)->displayFormat('d.m.Y'),
                        EventForm::euro("zuschuss_stand.{$key}.bewilligt_cent", 'Bewilligt'),
                        DatePicker::make("zuschuss_stand.{$key}.ausgezahlt_am")->label('Ausgezahlt am')->native(false)->displayFormat('d.m.Y'),
                        Toggle::make("zuschuss_stand.{$key}.abgelehnt")->label('Abgelehnt')->inline(false),
                    ]))->values()->all()),
        ]);
    }

    protected function getHeaderActions(): array
    {
        $event = $this->event();

        return [
            Action::make('veranstaltung')->label('Veranstaltung und Teilnehmende')->icon('heroicon-o-users')->color('gray')
                ->url(EventResource::getUrl('edit', ['record' => $event])),
            ActionGroup::make([
                ...collect(ZuschussPdf::unterlagen($event))->map(fn (string $label, string $art) => Action::make('pdf_'.$art)->label($label.' (PDF)')
                    ->icon('heroicon-o-document-text')
                    ->action(fn (): StreamedResponse => $this->pdf($art)))->values()->all(),
                Action::make('zip')->label('Alle Unterlagen (ZIP)')->icon('heroicon-o-archive-box-arrow-down')
                    ->action(fn (): StreamedResponse => $this->zip()),
                Action::make('xlsx')->label('Teilnahmeliste (.xlsx)')->icon('heroicon-o-table-cells')
                    ->action(function (): StreamedResponse {
                        abort_unless($this->darfUnterlagen(), 403);
                        AuditLogger::log('event.zuschuss_xlsx', Filament::auth()->user(), Event::class, $this->event()->getKey(), request());

                        return response()->streamDownload(fn () => app(ZuschussExcel::class)->write($this->event()), ZuschussExcel::dateiname($this->event()),
                            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
                    }),
            ])->label('Unterlagen')->icon('heroicon-o-printer')->button()
                ->visible(fn () => $this->event()->zuschussAktiv() && $this->darfUnterlagen()),
            // Vor Ort, Stufe 2 (docs/vor-ort.md): Liste mit digitalen Unterschriften ablegen.
            Action::make('festschreiben')
                ->label(fn () => $this->event()->teilnahmeliste_am ? 'Teilnahmeliste neu festschreiben' : 'Teilnahmeliste festschreiben')
                ->icon('heroicon-o-lock-closed')->color('gray')
                ->visible(fn () => $this->event()->zuschussAktiv() && $this->darfUnterlagen())
                ->requiresConfirmation()
                ->modalHeading('Teilnahmeliste mit Unterschriften festschreiben?')
                ->modalDescription(function () {
                    $event = $this->event();
                    $anzahl = count(Unterschriften::fuerListe($event));
                    $bestaetigt = $event->registrations()->where('status', 'confirmed')->count();

                    return "{$anzahl} von {$bestaetigt} Teilnehmenden haben digital unterschrieben (unter „Vor Ort“). "
                        .'Die Teilnahmeliste wird mit diesen Unterschriften als PDF abgelegt; „Teilnahmeliste (PDF)“ und das ZIP liefern ab dann diese Fassung. '
                        .'Die Einzelunterschriften werden '.Unterschriften::loeschTage().' Tage danach gelöscht – bis dahin lässt sich nachunterschreiben und neu festschreiben.'
                        .($event->teilnahmeliste_am ? ' Zuletzt festgeschrieben am '.$event->teilnahmeliste_am->format('d.m.Y H:i').'.' : '');
                })
                ->modalSubmitActionLabel('Festschreiben')
                ->action(function () {
                    abort_unless($this->darfUnterlagen() && $this->event()->zuschussAktiv(), 403);
                    $anzahl = Unterschriften::festschreiben($this->event(), Filament::auth()->user());
                    Notification::make()->success()->title("Teilnahmeliste festgeschrieben ({$anzahl} Unterschriften).")
                        ->body('Die Einzelunterschriften werden in '.Unterschriften::loeschTage().' Tagen gelöscht.')->send();
                }),
        ];
    }

    private function pdf(string $art): StreamedResponse
    {
        abort_unless($this->darfUnterlagen() && $this->event()->zuschussAktiv(), 403);
        AuditLogger::log('event.zuschuss_'.$art, Filament::auth()->user(), Event::class, $this->event()->getKey(), request());
        $pdf = app(ZuschussPdf::class)->unterlage($this->event(), $art);

        return response()->streamDownload(fn () => print ($pdf), ZuschussPdf::dateiname($this->event(), $art), ['Content-Type' => 'application/pdf']);
    }

    private function zip(): StreamedResponse
    {
        abort_unless($this->darfUnterlagen() && $this->event()->zuschussAktiv(), 403);
        AuditLogger::log('event.zuschuss_zip', Filament::auth()->user(), Event::class, $this->event()->getKey(), request());
        $pfad = tempnam(sys_get_temp_dir(), 'zuschuss');
        $zip = new ZipArchive;
        $zip->open($pfad, ZipArchive::OVERWRITE);
        $pdf = app(ZuschussPdf::class);
        foreach (array_keys(ZuschussPdf::unterlagen($this->event())) as $art) {
            $zip->addFromString(ZuschussPdf::dateiname($this->event(), $art), $pdf->unterlage($this->event(), $art));
        }
        $zip->close();

        return response()->streamDownload(function () use ($pfad) {
            readfile($pfad);
            @unlink($pfad);
        }, 'zuschuss-'.str($this->event()->title)->slug().'.zip', ['Content-Type' => 'application/zip']);
    }
}
