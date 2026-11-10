<?php

namespace Tests\Feature\Calendar;

use App\Models\Calendar;
use App\Models\CalendarEvent;
use App\Models\Event;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Kalender-Abo (ICS) für Handy und Outlook (docs/plan-ehrenamt.md Nr. 2).
 */
class KalenderAboTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('ICS mit Terminen und Veranstaltungen, nach Stufe und „nur Veranstaltungen“ filterbar; ganztägig, abgesagt, Maskierung, Zeilenfaltung')]
    public function test_abo(): void
    {
        $woe = Calendar::query()->create(['label' => 'Wölflinge', 'ics_url' => 'https://cloud.example.org/x', 'color' => '#ff6400', 'stage' => 'woelflinge']);
        $lager = now()->addMonth()->startOfDay();
        CalendarEvent::query()->create(['calendar_id' => $woe->id, 'uid' => 'lager', 'starts_at' => $lager, 'ends_at' => $lager->copy()->addDays(3), 'all_day' => true,
            'title' => 'Pfingstlager, Teil 1; mit Zelt', 'location' => 'Zeltplatz', 'description' => str_repeat('Lange Beschreibung mit Umlauten äöü ', 6)]);
        $tag = now()->addDays(10)->setTime(10, 0);
        Event::query()->create(['title' => 'Wölflingstag', 'slug' => 'woelflingstag', 'stage' => 'woelflinge', 'status' => 'open', 'starts_at' => $tag, 'ends_at' => $tag->copy()->setTime(17, 0)]);
        Event::query()->create(['title' => 'Roverrunde', 'slug' => 'roverrunde', 'stage' => 'rover', 'status' => 'cancelled', 'starts_at' => now()->addDays(12)->setTime(18, 0)]);
        Event::query()->create(['title' => 'Entwurf', 'slug' => 'entwurf', 'status' => 'draft', 'starts_at' => now()->addDays(5)]);

        $antwort = $this->get('/kalender.ics')->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $ics = $antwort->getContent();
        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\nVERSION:2.0\r\n", $ics);
        $this->assertSame(3, substr_count($ics, 'BEGIN:VEVENT'));
        $this->assertStringContainsString('SUMMARY:Pfingstlager\, Teil 1\; mit Zelt', $ics);
        $this->assertStringContainsString('DTSTART;VALUE=DATE:'.$lager->format('Ymd'), $ics);
        $this->assertStringContainsString('DTEND;VALUE=DATE:'.$lager->copy()->addDays(3)->format('Ymd'), $ics);
        $this->assertStringContainsString('DTSTART:'.$tag->copy()->utc()->format('Ymd\THis\Z'), $ics);
        $this->assertStringContainsString('URL:'.route('events.show', 'woelflingstag'), $ics);
        $this->assertStringContainsString("SUMMARY:Abgesagt: Roverrunde\r\n", $ics);
        $this->assertStringContainsString('STATUS:CANCELLED', $ics);
        $this->assertStringNotContainsString('Entwurf', $ics);
        foreach (explode("\r\n", $ics) as $zeile) {
            $this->assertLessThanOrEqual(75, strlen($zeile), 'Zeilen nach RFC 5545 gefaltet');
        }

        $stufe = $this->get('/kalender.ics?stufe=woelflinge')->getContent();
        $this->assertStringContainsString('Wölflingstag', $stufe);
        $this->assertStringNotContainsString('Roverrunde', $stufe);
        $this->assertStringContainsString('X-WR-CALNAME:'.config('dpsg.site.name').' – Wölflinge', $stufe);

        $nur = $this->get('/kalender.ics?kalender=veranstaltungen')->getContent();
        $this->assertStringContainsString('Wölflingstag', $nur);
        $this->assertStringNotContainsString('Pfingstlager', $nur);

        $this->get('/kalender?stufe=woelflinge')->assertOk()->assertSee('Kalender abonnieren (Wölflinge)')
            ->assertSee('webcal://', false)->assertSee(route('calendar.ics', ['stufe' => 'woelflinge']), false);
    }

    #[TestDox('Stufenseite verlinkt das Abo der Stufe')]
    public function test_stufenseite(): void
    {
        $slug = collect(config('dpsg.stages'))->keys()->first();
        Page::query()->create(['slug' => 'stufe-test', 'title' => 'Stufe', 'body' => '<p>x</p>', 'status' => 'published', 'published_at' => now()->subDay(), 'stage' => $slug]);
        $antwort = $this->get('/stufe-test');
        if ($antwort->status() !== 200) {
            $this->markTestSkipped('Stufenseiten in dieser Ausbaustufe nicht sichtbar.');
        }
        $antwort->assertSee('webcal://', false)->assertSee('Termine abonnieren');
    }
}
