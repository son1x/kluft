<?php

namespace App\Support;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Zahlungen\ZahlungResource;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Models\Zahlung;
use App\Policies\PostPolicy;
use App\Support\Zuschuesse\Antrag;
use App\Support\Zuschuesse\Geber;
use Closure;
use Throwable;

/**
 * „Was steht an“ auf der Startseite des Backends (docs/plan-ehrenamt.md,
 * Nr. 10): je nach Rollen, was Aufmerksamkeit braucht, jeweils mit Link.
 * Neue Bausteine ergänzen quellen() um eine eigene Methode.
 */
class WasStehtAn
{
    /** Veranstaltungen so viele Tage im Voraus zeigen. */
    public const VORSCHAU_TAGE = 14;

    /** Unbestätigte Anmeldungen ab so vielen Stunden melden. */
    public const UNBESTAETIGT_STUNDEN = 24;

    /**
     * Einträge: titel, text, url, farbe (danger|warning|info|gray), icon,
     * anzahl (optional). Dringendes zuerst.
     *
     * @return list<array{titel: string, text: string, url: string, farbe: string, icon: string, anzahl?: int}>
     */
    public static function fuer(User $user): array
    {
        $eintraege = [];
        foreach (self::quellen() as $quelle) {
            try {
                array_push($eintraege, ...$quelle($user));
            } catch (Throwable $e) {
                report($e);
            }
        }
        $rang = ['danger' => 0, 'warning' => 1, 'info' => 2, 'gray' => 3];
        usort($eintraege, fn (array $a, array $b) => ($rang[$a['farbe']] ?? 9) <=> ($rang[$b['farbe']] ?? 9));

        return $eintraege;
    }

    /** @return list<Closure(User): list<array<string, mixed>>> */
    private static function quellen(): array
    {
        return [
            self::freigaben(...),
            self::nachrichten(...),
            self::unbestaetigt(...),
            self::zahlungen(...),
            self::zuschussfristen(...),
            self::naechsteVeranstaltungen(...),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function freigaben(User $user): array
    {
        if (! app(PostPolicy::class)->publish($user)) {
            return [];
        }
        $n = PostResource::getEloquentQuery()->where('status', 'review')->count();

        return $n === 0 ? [] : [[
            'titel' => $n === 1 ? '1 Beitrag wartet auf Freigabe' : "{$n} Beiträge warten auf Freigabe",
            'text' => 'Durchlesen und veröffentlichen oder zurück auf „Entwurf“.',
            'url' => PostResource::getUrl('index', ['tableFilters' => ['status' => ['value' => 'review']]]),
            'farbe' => 'warning', 'icon' => 'heroicon-o-document-check', 'anzahl' => $n,
        ]];
    }

    /** @return list<array<string, mixed>> */
    private static function nachrichten(User $user): array
    {
        if (! ContactMessageResource::canViewAny()) {
            return [];
        }
        $n = ContactMessageResource::getEloquentQuery()->where('status', 'neu')->count();

        return $n === 0 ? [] : [[
            'titel' => $n === 1 ? '1 neue Nachricht' : "{$n} neue Nachrichten",
            'text' => 'Aus dem Kontaktformular, noch nicht bearbeitet.',
            'url' => ContactMessageResource::getUrl('index'),
            'farbe' => 'info', 'icon' => 'heroicon-o-inbox', 'anzahl' => $n,
        ]];
    }

    /** @return list<array<string, mixed>> */
    private static function unbestaetigt(User $user): array
    {
        if (! EventResource::canViewAny()) {
            return [];
        }
        $events = EventResource::getEloquentQuery()->where('starts_at', '>=', now()->startOfDay())->select('events.id');
        $proEvent = EventRegistration::query()->whereIn('event_id', $events)->where('status', 'pending')
            ->where('created_at', '<', now()->subHours(self::UNBESTAETIGT_STUNDEN))
            ->selectRaw('event_id, count(*) as n')->groupBy('event_id')->pluck('n', 'event_id');
        $n = (int) $proEvent->sum();
        if ($n === 0) {
            return [];
        }
        $url = $proEvent->count() === 1
            ? EventResource::getUrl('edit', ['record' => $proEvent->keys()->first(), 'relation' => '0'])
            : EventResource::getUrl('index');

        return [[
            'titel' => $n === 1 ? '1 Anmeldung seit über 24 Stunden unbestätigt' : "{$n} Anmeldungen seit über 24 Stunden unbestätigt",
            'text' => 'Kam die Bestätigungsmail nicht an? Unter „Teilnehmende“ lässt sich von Hand bestätigen.',
            'url' => $url, 'farbe' => 'warning', 'icon' => 'heroicon-o-envelope-open', 'anzahl' => $n,
        ]];
    }

    /** @return list<array<string, mixed>> */
    private static function zahlungen(User $user): array
    {
        if (! Zahlungen::zugriff($user)) {
            return [];
        }
        $n = Zahlung::query()->whereIn('status', ['offen', 'teilweise'])->whereDate('faellig_am', '<', today())->count();

        return $n === 0 ? [] : [[
            'titel' => $n === 1 ? '1 Zahlung überfällig' : "{$n} Zahlungen überfällig",
            'text' => 'Fälligkeit überschritten. Zahlungseingang prüfen oder erinnern.',
            'url' => ZahlungResource::getUrl('index'),
            'farbe' => 'danger', 'icon' => 'heroicon-o-banknotes', 'anzahl' => $n,
        ]];
    }

    /** @return list<array<string, mixed>> */
    private static function zuschussfristen(User $user): array
    {
        if (! Zuschuesse::aktiv() || ! EventResource::canViewAny()) {
            return [];
        }
        $eintraege = [];
        $events = EventResource::getEloquentQuery()->where('zuschuss', true)->where('starts_at', '>=', now()->subYear())->get();
        foreach ($events as $event) {
            /** @var Event $event */
            foreach (Antrag::geber($event) as $geber) {
                if (Antrag::status($event, $geber) !== 'offen') {
                    continue;
                }
                $frist = Antrag::frist($event, $geber);
                $tage = (int) today()->diffInDays($frist, false);
                if ($tage < 0 || $tage > 14) {
                    continue;
                }
                $eintraege[] = [
                    'titel' => 'Zuschussantrag „'.Geber::name($geber).'“: Frist '.$frist->format('d.m.'),
                    'text' => $event->title.' · '.($tage === 0 ? 'heute' : 'noch '.$tage.($tage === 1 ? ' Tag' : ' Tage')),
                    'url' => EventResource::getUrl('zuschuss', ['record' => $event]),
                    'farbe' => $tage <= 3 ? 'danger' : 'warning', 'icon' => 'heroicon-o-document-currency-euro',
                ];
            }
        }

        return $eintraege;
    }

    /** @return list<array<string, mixed>> */
    private static function naechsteVeranstaltungen(User $user): array
    {
        if (! EventResource::canViewAny() || ! ($user->isVerwaltung() || $user->hasRole('stufenadmin') || $user->hasRole('sektionsadmin'))) {
            return [];
        }

        return EventResource::getEloquentQuery()
            ->whereBetween('starts_at', [now(), now()->addDays(self::VORSCHAU_TAGE)])
            ->where('status', '!=', 'cancelled')
            ->withCount(['registrations as bestaetigt' => fn ($q) => $q->where('status', 'confirmed')])
            ->orderBy('starts_at')->limit(3)->get()
            ->map(fn (Event $e) => [
                'titel' => $e->title,
                'text' => $e->dateLabel().' · '.$e->bestaetigt.($e->capacity ? '/'.$e->capacity : '').' angemeldet',
                'url' => EventResource::getUrl('edit', ['record' => $e, 'relation' => '0']),
                'farbe' => 'gray', 'icon' => 'heroicon-o-calendar',
            ])->values()->all();
    }
}
