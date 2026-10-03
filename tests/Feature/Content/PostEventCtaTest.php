<?php

namespace Tests\Feature\Content;

use App\Models\Event;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Beiträge mit verknüpfter Veranstaltung zeigen einen Anmelde-Kasten.
 */
class PostEventCtaTest extends TestCase
{
    use RefreshDatabase;

    private function newPost(?Event $event): Post
    {
        return Post::query()->create([
            'title' => 'Einladung zum Wölflingstag', 'slug' => 'einladung', 'teaser' => 'Kommt alle!',
            'body' => '<p>Wir laden euch ein.</p>', 'status' => 'published', 'published_at' => now()->subHour(),
            'event_id' => $event?->id,
        ]);
    }

    private function event(array $attrs = []): Event
    {
        return Event::query()->create([
            'title' => 'Wölflingstag', 'slug' => 'woelflingstag', 'location' => 'Trier', 'status' => 'open',
            'starts_at' => now()->addMonth()->setTime(10, 0), 'ends_at' => now()->addMonth()->setTime(17, 0),
            'reg_closes_at' => now()->addWeeks(3)->setTime(23, 59), ...$attrs,
        ]);
    }

    #[TestDox('Offene Anmeldung: auffälliger Kasten oben, Hinweis unten, Badge in Übersicht und Startseite')]
    public function test_open_registration_box(): void
    {
        $event = $this->event();
        $this->newPost($event);

        $this->get('/aktuelles/einladung')->assertOk()
            ->assertSeeInOrder(['Jetzt anmelden', 'Wölflingstag', 'Anmeldeschluss:', 'Zur Anmeldung', 'Wir laden euch ein.', 'Zur Anmeldung'])
            ->assertSee('href="'.route('events.show', 'woelflingstag').'#anmeldung"', false);
        $this->get('/aktuelles')->assertSee('Anmeldung offen');
    }

    #[TestDox('Kasten passt sich an: Warteliste, geschlossen, abgesagt; Entwürfe und Beiträge ohne Veranstaltung zeigen nichts')]
    public function test_box_follows_registration_state(): void
    {
        $event = $this->event(['capacity' => 0, 'waitlist_enabled' => true]);
        $this->newPost($event);
        $this->get('/aktuelles/einladung')->assertSee('Auf die Warteliste');

        $event->update(['capacity' => null, 'reg_closes_at' => now()->subDay()]);
        $this->get('/aktuelles/einladung')->assertSee('Die Anmeldung ist geschlossen')->assertSee('Zur Veranstaltung')->assertDontSee('Jetzt anmelden');
        $this->get('/aktuelles')->assertDontSee('Anmeldung offen');

        $event->update(['status' => 'cancelled']);
        $this->get('/aktuelles/einladung')->assertSee('Die Veranstaltung ist abgesagt');

        $event->update(['status' => 'draft']);
        $this->get('/aktuelles/einladung')->assertOk()->assertDontSee('Zur Veranstaltung')->assertDontSee('Zur Anmeldung');

        Post::query()->update(['event_id' => null]);
        $this->get('/aktuelles/einladung')->assertOk()->assertDontSee('Zur Anmeldung');
    }
}
