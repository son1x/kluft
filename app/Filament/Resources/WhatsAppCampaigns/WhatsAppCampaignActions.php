<?php

namespace App\Filament\Resources\WhatsAppCampaigns;

use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Services\WhatsApp\WhatsAppCampaigns;
use App\Services\WhatsApp\WhatsAppClient;
use App\Services\WhatsApp\WhatsAppException;
use App\Support\AuditLogger;
use App\Support\PhoneNumber;
use App\Support\WhatsAppAccess;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Schritte einer Aussendung – in der Liste und auf der Bearbeiten-Seite.
 */
class WhatsAppCampaignActions
{
    /**
     * @return list<Action>
     */
    public static function all(): array
    {
        $user = fn (): ?User => Filament::auth()->user();
        $me = fn (): User => Filament::auth()->user() ?? abort(403);
        $run = function (callable $step, string $done) {
            try {
                $step();
                Notification::make()->success()->title($done)->send();
            } catch (ValidationException $e) {
                Notification::make()->danger()->title('Nicht möglich')->body(collect($e->errors())->flatten()->first())->send();
            }
        };

        return [
            Action::make('submit')
                ->label('Zur Freigabe')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn (WhatsAppCampaign $record) => $record->status === 'draft' && WhatsAppAccess::draft($user()))
                ->requiresConfirmation()
                ->modalDescription('Alle, die freigeben dürfen, bekommen eine Nachricht.')
                ->action(fn (WhatsAppCampaign $record, WhatsAppCampaigns $c) => $run(fn () => $c->submit($record, $me()), 'Zur Freigabe vorgelegt')),

            Action::make('approve')
                ->label('Freigeben')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (WhatsAppCampaign $record) => $record->status === 'review' && WhatsAppAccess::manage($user())
                    && ! (config('whatsapp.four_eyes') && $record->created_by === $user()?->id))
                ->requiresConfirmation()
                ->modalDescription(function (WhatsAppCampaign $record, WhatsAppCampaigns $c) {
                    $n = $c->recipients();

                    return "Geht an {$n} Abonnent:innen. Geschätzte Kosten bei Meta: ".number_format($c->estimate($n), 2, ',', '.').' €.'
                        .($record->scheduled_at?->isFuture() ? ' Versand geplant am '.$record->scheduled_at->format('d.m.Y H:i').'.' : ' Danach „Jetzt senden“.');
                })
                ->action(fn (WhatsAppCampaign $record, WhatsAppCampaigns $c) => $run(fn () => $c->approve($record, $me()), 'Freigegeben')),

            Action::make('back')
                ->label('Zurück an Entwurf')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->visible(fn (WhatsAppCampaign $record) => in_array($record->status, ['review', 'approved', 'scheduled'], true) && WhatsAppAccess::draft($user()))
                ->action(function (WhatsAppCampaign $record) use ($user) {
                    $record->update(['status' => 'draft', 'approved_by' => null, 'approved_at' => null]);
                    AuditLogger::log('whatsapp.aussendung.zurueck', $user(), 'whatsapp_campaign', $record->id);
                }),

            Action::make('start')
                ->label('Jetzt senden')
                ->icon('heroicon-o-rocket-launch')
                ->color('success')
                ->visible(fn (WhatsAppCampaign $record) => in_array($record->status, ['approved', 'scheduled'], true) && WhatsAppAccess::manage($user()))
                ->requiresConfirmation()
                ->modalDescription(fn (WhatsAppCampaigns $c) => 'Der Versand an '.$c->recipients().' Abonnent:innen beginnt mit dem nächsten Cronjob-Lauf und dauert je nach Anzahl einige Minuten.')
                ->action(fn (WhatsAppCampaign $record, WhatsAppCampaigns $c) => $run(fn () => $c->start($record, $user()), 'Versand gestartet')),

            Action::make('pause')
                ->label('Anhalten')
                ->icon('heroicon-o-pause')
                ->color('warning')
                ->visible(fn (WhatsAppCampaign $record) => $record->status === 'sending' && WhatsAppAccess::manage($user()))
                ->action(fn (WhatsAppCampaign $record, WhatsAppCampaigns $c) => $run(fn () => $c->pause($record, $user()), 'Angehalten')),

            Action::make('resume')
                ->label('Fortsetzen')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn (WhatsAppCampaign $record) => $record->status === 'paused' && WhatsAppAccess::manage($user()))
                ->action(fn (WhatsAppCampaign $record, WhatsAppCampaigns $c) => $run(fn () => $c->resume($record, $me()), 'Wird fortgesetzt')),

            Action::make('cancel')
                ->label('Abbrechen')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (WhatsAppCampaign $record) => in_array($record->status, ['approved', 'scheduled', 'sending', 'paused'], true) && WhatsAppAccess::manage($user()))
                ->requiresConfirmation()
                ->modalDescription('Noch nicht versendete Nachrichten gehen nicht mehr raus. Bereits zugestellte bleiben.')
                ->action(fn (WhatsAppCampaign $record, WhatsAppCampaigns $c) => $run(fn () => $c->cancel($record, $me()), 'Abgebrochen')),

            Action::make('test')
                ->label('Test an eine Nummer')
                ->icon('heroicon-o-device-phone-mobile')
                ->color('gray')
                ->visible(fn (WhatsAppCampaign $record) => $record->isEditable() && $record->template?->status === 'APPROVED' && WhatsAppAccess::manage($user()))
                ->schema([
                    TextInput::make('phone')->label('Deine Mobilnummer')->required()->tel()
                        ->helperText('Nur für diesen Test, wird nicht gespeichert. Kostet wie eine normale Nachricht.'),
                ])
                ->action(function (WhatsAppCampaign $record, array $data, WhatsAppClient $client) {
                    $phone = PhoneNumber::normalize($data['phone']);
                    if ($phone === null) {
                        Notification::make()->danger()->title('Ungültige Nummer')->send();

                        return;
                    }
                    try {
                        $id = $client->sendTemplate($phone, $record->template, $record->variables['body'] ?? [], $record->variables['url'] ?? null);
                        $id === null
                            ? Notification::make()->warning()->title('Testmodus: Nummer steht nicht auf der Test-Allowlist')->send()
                            : Notification::make()->success()->title('Testnachricht verschickt')->send();
                    } catch (WhatsAppException $e) {
                        Notification::make()->danger()->title('Test fehlgeschlagen')->body($e->getMessage())->send();
                    }
                }),
        ];
    }
}
