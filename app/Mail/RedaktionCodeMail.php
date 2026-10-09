<?php

namespace App\Mail;

use App\Http\Controllers\Auth\CodeLoginController;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Einmalcode für die Anmeldung der Redaktion ohne Microsoft 365.
 */
class RedaktionCodeMail extends Mailable
{
    public function __construct(public string $code, public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->code.' ist dein Anmeldecode für '.config('dpsg.site.short_name', config('dpsg.site.name')));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.redaktion-code', with: [
            'minutes' => CodeLoginController::CODE_MINUTES,
            'url' => route('auth.code'),
            'rolle' => $this->user->isRolle() ? $this->user->name : null,
        ]);
    }
}
