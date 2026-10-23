<?php

namespace App\Filament\Resources\Shop\Pages;

use App\Filament\Resources\Shop\BestellungResource;
use App\Mail\ShopBestellungMail;
use App\Models\Shop\Bestellung;
use App\Models\Shop\Position;
use App\Services\Sammelbestellung\Ausgabe;
use App\Support\AuditLogger;
use App\Support\Sammelbestellung;
use App\Support\SammelbestellungAblauf;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EditBestellung extends EditRecord
{
    protected static string $resource = BestellungResource::class;

    public function getTitle(): string
    {
        /** @var Bestellung $b */
        $b = $this->getRecord();

        return 'Bestellung von '.$b->name.' – '.(Bestellung::STAENDE[$b->stand()] ?? '');
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('pruefen')->label('Mitgliedschaft prüfen')->icon('heroicon-o-identification')
                    ->action(function () {
                        $ergebnis = SammelbestellungAblauf::mitgliedschaftPruefen($this->getRecord());
                        AuditLogger::log('sammelbestellung.bestellung.mitgliedschaft', Filament::auth()->user(), 'shop_bestellungen', $this->getRecord()->id, request());
                        $ergebnis === null
                            ? Notification::make()->warning()->title('Keine Mitgliederliste')->body('Für die Prüfung braucht es den NaMi-Abruf unter „Kasse → Mitgliedsbeiträge“ oder den Mitgliederabgleich.')->send()
                            : Notification::make()->success()->title('Ergebnis: '.(Bestellung::MITGLIEDSCHAFT[$ergebnis] ?? $ergebnis))->send();
                        $this->refreshFormData(['mitgliedschaft']);
                    }),
                Action::make('mitgliedBestaetigen')->label('Mitgliedschaft von Hand bestätigen')->icon('heroicon-o-hand-thumb-up')
                    ->visible(fn () => $this->getRecord()->mitgliedschaft !== 'bestaetigt')
                    ->requiresConfirmation()->modalDescription('Z. B. für ein Neumitglied, das noch nicht in NaMi steht.')
                    ->action(function () {
                        $this->getRecord()->update(['mitgliedschaft' => 'bestaetigt', 'mitgliedschaft_am' => now()]);
                        AuditLogger::log('sammelbestellung.bestellung.mitglied_bestaetigt', Filament::auth()->user(), 'shop_bestellungen', $this->getRecord()->id, request());
                    }),
                Action::make('halten')->label(fn () => $this->getRecord()->gehalten ? 'Nicht mehr halten' : 'Halten (Zahlung angekündigt)')->icon('heroicon-o-pause-circle')
                    ->visible(fn () => ! $this->getRecord()->storniert_am)
                    ->action(function () {
                        $this->getRecord()->update(['gehalten' => ! $this->getRecord()->gehalten]);
                        AuditLogger::log('sammelbestellung.bestellung.gehalten', Filament::auth()->user(), 'shop_bestellungen', $this->getRecord()->id, request());
                    }),
                Action::make('ausgegeben')->label('Alles ausgegeben')->icon('heroicon-o-check-circle')
                    ->visible(fn () => $this->getRecord()->positionen->contains(fn (Position $p) => $p->bezahlt() && ! $p->ausgegeben_am))
                    ->schema([TextInput::make('vermerk')->label('Vermerk')->maxLength(160)->placeholder('z. B. Unterschrift auf Liste vom 12.12.')])
                    ->action(function (array $data) {
                        $this->getRecord()->positionen->filter(fn (Position $p) => $p->bezahlt() && ! $p->ausgegeben_am)
                            ->each(fn (Position $p) => SammelbestellungAblauf::ausgeben($p, Filament::auth()->user(), $data['vermerk'] ?? null));
                        AuditLogger::log('sammelbestellung.bestellung.ausgegeben', Filament::auth()->user(), 'shop_bestellungen', $this->getRecord()->id, request());
                        Notification::make()->success()->title('Als ausgegeben abgehakt')->send();
                    }),
                Action::make('ausgabeschein')->label('Ausgabeschein (PDF)')->icon('heroicon-o-document-arrow-down')
                    ->visible(fn () => $this->getRecord()->positionen->contains(fn (Position $p) => $p->bezahlt()))
                    ->action(function (): StreamedResponse {
                        $b = $this->getRecord();
                        abort_unless(SammelbestellungAblauf::darfAusgeben(Filament::auth()->user()), 403);
                        AuditLogger::log('sammelbestellung.ausgabe.schein', Filament::auth()->user(), 'shop_bestellungen', $b->id, request());
                        $pdf = app(Ausgabe::class)->pdf($b->runde, 'scheine', $b);

                        return response()->streamDownload(fn () => print ($pdf), 'ausgabeschein-'.$b->id.'.pdf', ['Content-Type' => 'application/pdf']);
                    }),
            ])->label('Aktionen')->button()->color('gray'),
            Action::make('stornieren')->label('Stornieren')->color('danger')->icon('heroicon-o-x-circle')
                ->visible(fn () => ! $this->getRecord()->storniert_am)
                ->requiresConfirmation()
                ->modalDescription('Die Person bekommt eine Mail. Offene Zahlungen entfallen. Bereits Bezahltes bleibt in „Kasse → Zahlungen“ mit dem Hinweis „Erstattung prüfen“.')
                ->action(function () {
                    Sammelbestellung::stornieren($this->getRecord(), Filament::auth()->id());
                    Mail::to($this->getRecord()->email, $this->getRecord()->name)->send(new ShopBestellungMail($this->getRecord()->fresh(), 'storno'));
                    AuditLogger::log('sammelbestellung.bestellung.storniert', Filament::auth()->user(), 'shop_bestellungen', $this->getRecord()->id, request());
                    Notification::make()->title('Storniert')->success()->send();
                }),
        ];
    }
}
