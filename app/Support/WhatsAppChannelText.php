<?php

namespace App\Support;

use App\Models\Event;
use App\Models\Post;
use Illuminate\Support\Str;

/**
 * Fertiger Text für den WhatsApp-Kanal (kostenlose Variante): Titel fett,
 * zwei, drei Sätze, Link. WhatsApp-Formatierung: *fett*, _kursiv_.
 */
class WhatsAppChannelText
{
    public const MAX_SUMMARY = 280;

    public static function forPost(Post $post): string
    {
        $summary = self::summary($post->teaser ?: $post->body);

        return trim('*'.self::plain($post->title).'*'."\n\n"
            .($summary !== '' ? $summary."\n\n" : '')
            .'👉 '.route('posts.show', $post->slug));
    }

    public static function forEvent(Event $event): string
    {
        $summary = self::summary($event->teaser ?: $event->description);

        return trim('*'.self::plain($event->title).'*'."\n"
            .'📅 '.$event->dateLabel()."\n"
            .(filled($event->location) ? '📍 '.self::plain((string) $event->location)."\n" : '')
            ."\n"
            .($summary !== '' ? $summary."\n\n" : '')
            .'👉 Infos und Anmeldung: '.route('events.show', $event->slug));
    }

    private static function summary(?string $html): string
    {
        return Str::limit(self::plain((string) $html), self::MAX_SUMMARY);
    }

    /** HTML entfernen, Leerraum zusammenziehen, WhatsApp-Formatzeichen entschärfen. */
    private static function plain(string $text): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />', '</li>'], ' ', $text)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', str_replace(['*', '_', '~'], '', $text)));
    }
}
