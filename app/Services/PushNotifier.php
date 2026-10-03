<?php

namespace App\Services;

use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Posts\PostResource;
use App\Models\EventRegistration;
use App\Models\Post;
use App\Models\User;
use App\Notifications\ActivityPushNotification;
use App\Notifications\Messages\NtfyMessage;
use App\Support\PushEvents;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

/**
 * Wer bekommt welche Push-Nachricht? Nur Personen, die das Ereignis
 * abonniert haben UND die Inhalte im Backend sehen dürfen (gleiche
 * Policies wie im Backend).
 */
class PushNotifier
{
    private const REGISTRATION = [
        'confirmed' => ['Neue Anmeldung', 'tent'],
        'waitlist' => ['Neue Anmeldung (Warteliste)', 'hourglass_flowing_sand'],
        'cancelled' => ['Stornierung', 'x'],
    ];

    public function registration(EventRegistration $registration, string $kind): void
    {
        if (! isset(self::REGISTRATION[$kind])) {
            return;
        }
        $event = $registration->event;
        $recipients = $this->subscribers(PushEvents::ANMELDUNGEN)
            ->filter(fn (User $user) => Gate::forUser($user)->allows('manageParticipants', $event));
        if ($recipients->isEmpty()) {
            return;
        }

        $confirmed = $event->confirmedCount();
        $waitlist = $event->registrations()->where('status', 'waitlist')->count();
        $places = $event->capacity !== null ? "{$confirmed}/{$event->capacity} Plätze belegt" : "{$confirmed} Anmeldungen";
        [$title, $tag] = self::REGISTRATION[$kind];

        Notification::send($recipients, new ActivityPushNotification(PushEvents::ANMELDUNGEN, new NtfyMessage(
            title: $title,
            message: $event->title.': '.$places.($waitlist > 0 ? ", {$waitlist} auf der Warteliste" : ''),
            click: EventResource::getUrl('edit', ['record' => $event], panel: 'admin'),
            tags: [$tag],
        )));
    }

    /**
     * Nach dem Speichern eines Beitrags, wenn sich der Status geändert hat.
     */
    public function post(Post $post): void
    {
        $actor = auth()->user();
        $notActor = fn (User $user) => ! ($actor instanceof User && $actor->is($user));

        if ($post->status === 'review') {
            $recipients = $this->subscribers(PushEvents::BEITRAEGE_FREIGABE)
                ->filter($notActor)
                ->filter(fn (User $user) => $user->id !== $post->author_id
                    && Gate::forUser($user)->allows('publish', Post::class)
                    && Gate::forUser($user)->allows('update', $post));

            Notification::send($recipients, new ActivityPushNotification(PushEvents::BEITRAEGE_FREIGABE, new NtfyMessage(
                title: 'Beitrag wartet auf Freigabe',
                message: $post->title,
                click: PostResource::getUrl('edit', ['record' => $post], panel: 'admin'),
                tags: ['memo'],
            )));
        }

        if ($post->status === 'published') {
            $recipients = $this->subscribers(PushEvents::BEITRAEGE_NEU)->filter($notActor);
            $planned = $post->published_at?->isFuture() ? ' (erscheint am '.$post->published_at->format('d.m.Y H:i').')' : '';

            Notification::send($recipients, new ActivityPushNotification(PushEvents::BEITRAEGE_NEU, new NtfyMessage(
                title: 'Neuer Beitrag'.$planned,
                message: $post->title,
                click: route('posts.show', $post->slug),
                tags: ['newspaper'],
            )));
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function subscribers(string $event): Collection
    {
        return User::query()
            ->where('status', 'active')
            ->whereNotNull('ntfy_topic')
            ->with('roleAssignments')
            ->get()
            ->filter(fn (User $user) => $user->wantsPush($event))
            ->values();
    }
}
