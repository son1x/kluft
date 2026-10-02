<?php

namespace App\Support;

use App\Models\Calendar;
use App\Models\CalendarEvent;
use App\Models\Event;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Ein Eintrag im öffentlichen Kalender – entweder ein Termin aus einem
 * Nextcloud-Kalender oder eine Veranstaltung aus dem Backend (mit
 * Anmeldung). Die Ansicht muss die Herkunft nicht kennen.
 */
final class CalendarItem
{
    /** Farbe der Veranstaltungen im Kalender (DPSG-Rot). */
    public const EVENT_COLOR = '#810a1a';

    public function __construct(
        public readonly string $title,
        public readonly CarbonInterface $starts_at,
        public readonly ?CarbonInterface $ends_at,
        public readonly bool $all_day,
        public readonly ?string $location,
        public readonly ?string $description,
        public readonly string $sourceLabel,
        public readonly string $color,
        public readonly ?string $url = null,
        public readonly ?string $badge = null,
        public readonly bool $cancelled = false,
    ) {}

    public static function fromCalendarEvent(CalendarEvent $e): self
    {
        return new self(
            title: $e->title,
            starts_at: $e->starts_at,
            ends_at: $e->ends_at,
            all_day: $e->all_day,
            location: $e->location,
            description: $e->description,
            sourceLabel: $e->calendar->label,
            color: $e->calendar->color,
        );
    }

    public static function fromEvent(Event $e): self
    {
        $state = $e->registrationState();

        return new self(
            title: $e->title,
            starts_at: $e->starts_at,
            ends_at: $e->ends_at,
            all_day: false,
            location: $e->location,
            description: $e->teaser,
            sourceLabel: 'Veranstaltung',
            color: self::EVENT_COLOR,
            url: route('events.show', $e->slug),
            badge: match ($state) {
                'offen' => 'Anmeldung möglich',
                'warteliste' => 'Warteliste',
                'bald' => 'Anmeldung bald möglich',
                'ausgebucht' => 'Ausgebucht',
                'abgesagt' => 'Abgesagt',
                default => null,
            },
            cancelled: $state === 'abgesagt',
        );
    }

    /** Letzter tatsächlicher Tag (ganztägige ICS-Termine enden exklusiv am Folgetag). */
    public function lastDay(): ?CarbonInterface
    {
        if (! $this->ends_at) {
            return null;
        }

        return $this->all_day ? $this->ends_at->copy()->subDay() : $this->ends_at;
    }

    public function textColor(): string
    {
        return Calendar::textColorFor($this->color);
    }

    /**
     * Gleicher Termin? Gleicher Starttag und (nahezu) gleicher Titel –
     * damit eine Veranstaltung, die zusätzlich in der Nextcloud steht,
     * nur einmal (mit Anmeldung) erscheint.
     */
    public function sameAs(self $other): bool
    {
        if (! $this->starts_at->isSameDay($other->starts_at)) {
            return false;
        }

        $a = self::normalize($this->title);
        $b = self::normalize($other->title);

        return $a === $b || (min(strlen($a), strlen($b)) >= 8 && (str_contains($a, $b) || str_contains($b, $a)));
    }

    private static function normalize(string $title): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::ascii(mb_strtolower($title))) ?? '';
    }
}
