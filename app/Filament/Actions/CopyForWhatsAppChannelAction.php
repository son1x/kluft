<?php

namespace App\Filament\Actions;

use App\Models\Event;
use App\Models\Post;
use App\Support\WhatsAppAccess;
use App\Support\WhatsAppChannelText;
use App\Support\WhatsAppMode;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;

/**
 * „Für WhatsApp-Kanal kopieren“ an Beiträgen und Veranstaltungen (kostenlose
 * Variante): zeigt den fertigen Text zum Kopieren und den Link zum Kanal.
 * Gepostet wird von Hand in WhatsApp (App oder WhatsApp Web).
 */
class CopyForWhatsAppChannelAction
{
    public static function make(): Action
    {
        return Action::make('whatsappChannel')
            ->label('Für WhatsApp-Kanal')
            ->icon('heroicon-o-chat-bubble-bottom-center-text')
            ->color('gray')
            ->visible(fn (Model $record) => WhatsAppMode::channel()
                && WhatsAppAccess::draft(Filament::auth()->user())
                && (($record instanceof Post && $record->isPublished()) || ($record instanceof Event && $record->status !== 'draft')))
            ->modalHeading('Text für den WhatsApp-Kanal')
            ->modalDescription('Kopieren, in WhatsApp den Kanal öffnen, einfügen und senden. Bei Bedarf ein Bild dazu anhängen.')
            ->modalContent(fn (Model $record) => view('filament.actions.whatsapp-channel-copy', [
                'text' => $record instanceof Post ? WhatsAppChannelText::forPost($record) : WhatsAppChannelText::forEvent($record),
                'channelUrl' => config('whatsapp.channel_url'),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Schließen');
    }
}
