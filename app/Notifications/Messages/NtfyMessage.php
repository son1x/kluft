<?php

namespace App\Notifications\Messages;

/**
 * Eine Push-Nachricht für ntfy. Bewusst ohne personenbezogene Daten:
 * nur Art des Ereignisses, Titel von Veranstaltung/Beitrag, Zahlen und
 * ein Link ins Backend.
 */
class NtfyMessage
{
    /**
     * @param  list<string>  $tags  Emoji-Kürzel, s. ntfy-Doku
     */
    public function __construct(
        public string $title,
        public string $message,
        public ?string $click = null,
        public array $tags = [],
        public int $priority = 3,
    ) {}
}
