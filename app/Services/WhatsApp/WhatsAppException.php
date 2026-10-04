<?php

namespace App\Services\WhatsApp;

use RuntimeException;

/**
 * Fehler der Cloud API mit Einordnung, wie der Versand reagieren soll
 * (docs/whatsapp_newsletter.md, 6.2). Codes laut Meta-Doku.
 */
class WhatsAppException extends RuntimeException
{
    /** Später erneut versuchen (Durchsatz- oder Paar-Limit, Störung bei Meta). */
    private const RETRY = [4, 80007, 130429, 131000, 131016, 131056, 133004];

    /** Aussendung anhalten (Token, Konto, Spam-Limit, Zahlungsmittel). */
    private const FATAL = [0, 3, 10, 100, 190, 200, 368, 131031, 131042, 131048, 131057];

    /** Person hat Marketing-Nachrichten dieses Absenders abbestellt. */
    public const OPT_OUT = [131050];

    public function __construct(string $message, public readonly int $metaCode = 0, public readonly int $httpStatus = 0)
    {
        parent::__construct($message);
    }

    public function retryable(): bool
    {
        return in_array($this->metaCode, self::RETRY, true) || $this->httpStatus >= 500 || $this->httpStatus === 429;
    }

    public function fatal(): bool
    {
        return ! $this->retryable() && (in_array($this->metaCode, self::FATAL, true) || $this->httpStatus === 401);
    }

    public function optOut(): bool
    {
        return in_array($this->metaCode, self::OPT_OUT, true);
    }
}
