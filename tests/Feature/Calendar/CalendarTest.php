<?php

namespace Tests\Feature\Calendar;

use App\Filament\Resources\Calendars\Pages\EditCalendar;
use App\Models\Calendar;
use App\Models\CalendarEvent;
use App\Models\Event;
use App\Models\User;
use App\Services\CalendarSync;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Nextcloud-Kalender, Pflichtenheft 6.5 und Abnahmekriterium 8.
 */
class CalendarTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://cloud.dpsg-trier.de/remote.php/dav/public-calendars/abc123?export';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.nextcloud.allowed_hosts' => 'cloud.dpsg-trier.de']);
    }

    private function ics(): string
    {
        $first = CarbonImmutable::now()->addWeek()->next(CarbonImmutable::TUESDAY)->setTime(17, 0);
        $exdate = $first->addWeek();
        $lager = CarbonImmutable::now()->addMonths(2)->startOfDay();
        $fmt = fn (CarbonImmutable $d) => $d->format('Ymd\THis');

        return implode("\r\n", [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Nextcloud//EN',
            'BEGIN:VEVENT', 'UID:gs-1', 'SUMMARY:Gruppenstunde Wölflinge', 'LOCATION:Pfarrheim',
            'DESCRIPTION:Fragen an leitung@example.org',
            'ORGANIZER;CN=Max:mailto:max@example.org', 'ATTENDEE;CN=Erika:mailto:erika@example.org',
            'DTSTART;TZID=Europe/Berlin:'.$fmt($first), 'DTEND;TZID=Europe/Berlin:'.$fmt($first->addMinutes(90)),
            'RRULE:FREQ=WEEKLY;COUNT=4', 'EXDATE;TZID=Europe/Berlin:'.$fmt($exdate),
            'END:VEVENT',
            'BEGIN:VEVENT', 'UID:lager-1', 'SUMMARY:Pfingstlager',
            'DTSTART;VALUE=DATE:'.$lager->format('Ymd'), 'DTEND;VALUE=DATE:'.$lager->addDays(3)->format('Ymd'),
            'END:VEVENT',
            'BEGIN:VEVENT', 'UID:abgesagt', 'SUMMARY:Abgesagt', 'STATUS:CANCELLED',
            'DTSTART;TZID=Europe/Berlin:'.$fmt($first->addDay()), 'DTEND;TZID=Europe/Berlin:'.$fmt($first->addDay()->addHour()),
            'END:VEVENT',
            'END:VCALENDAR', '',
        ]);
    }

    private function calendar(array $attrs = []): Calendar
    {
        return Calendar::query()->create(['label' => 'Wölflinge', 'ics_url' => self::URL, 'color' => '#ff6400', 'stage' => 'woelflinge', ...$attrs]);
    }

    #[TestDox('Kalenderabruf löst Serientermine auf und speichert keine E-Mail-Adressen')]
    public function test_sync_expands_recurrences_and_never_stores_email_addresses(): void
    {
        Http::fake([self::URL => Http::response($this->ics(), 200, ['Content-Type' => 'text/calendar'])]);
        $calendar = $this->calendar();

        $this->assertTrue(app(CalendarSync::class)->sync($calendar));

        $this->assertSame(3, $calendar->events()->where('uid', 'gs-1')->count());
        $this->assertSame(1, $calendar->events()->where('all_day', true)->count());
        $this->assertSame(0, $calendar->events()->where('title', 'Abgesagt')->count());
        $this->assertSame('17:00', $calendar->events()->where('uid', 'gs-1')->first()->starts_at->format('H:i'));

        foreach (DB::table('calendar_events_cache')->get() as $row) {
            $this->assertStringNotContainsString('@example.org', json_encode($row));
        }
        $this->assertNotNull($calendar->fresh()->last_synced_at);
    }

    #[TestDox('Fehlgeschlagener Kalenderabruf behält die letzten gültigen Termine')]
    public function test_failed_sync_keeps_last_valid_events(): void
    {
        Http::fakeSequence(self::URL)
            ->push($this->ics(), 200)
            ->push('Fehler', 500);
        $calendar = $this->calendar();
        $sync = app(CalendarSync::class);

        $sync->sync($calendar);
        $this->assertFalse($sync->sync($calendar->fresh()));

        $this->assertSame(4, $calendar->events()->count());
        $this->assertStringContainsString('HTTP 500', $calendar->fresh()->last_error);
        $this->get('/kalender')->assertOk()->assertSee('Gruppenstunde Wölflinge');
    }

    #[TestDox('Kalenderabruf nur von erlaubten Servern, nur HTTPS, ohne Umleitungen und Riesendateien')]
    public function test_ssrf_protection_blocks_foreign_hosts_plain_http_redirects_and_large_files(): void
    {
        Http::fake([
            'https://cloud.dpsg-trier.de/umleitung' => Http::response('', 302, ['Location' => 'http://169.254.169.254/']),
            'https://cloud.dpsg-trier.de/gross' => Http::response(str_repeat('x', CalendarSync::MAX_BYTES + 1)),
            '*' => Http::response($this->ics()),
        ]);
        $sync = app(CalendarSync::class);

        foreach ([
            'https://intern.example.org/kalender.ics' => 'nicht freigegeben',
            'http://cloud.dpsg-trier.de/x.ics' => 'Nur https',
            'https://user:pw@cloud.dpsg-trier.de/x.ics' => 'Nur https',
            'https://cloud.dpsg-trier.de/umleitung' => 'HTTP 302',
            'https://cloud.dpsg-trier.de/gross' => '5 MB',
        ] as $url => $error) {
            $calendar = $this->calendar(['ics_url' => $url]);
            $this->assertFalse($sync->sync($calendar), $url);
            $this->assertStringContainsString($error, $calendar->fresh()->last_error, $url);
            $this->assertSame(0, $calendar->events()->count());
        }

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'intern.example.org') || str_starts_with($request->url(), 'http://'));
    }

    #[TestDox('Kalender zeigt Liste und Monatsansicht und filtert nach Stufe')]
    public function test_list_month_filter_and_api(): void
    {
        Http::fake([self::URL => Http::response($this->ics())]);
        $woelflinge = $this->calendar();
        app(CalendarSync::class)->sync($woelflinge);
        $dv = Calendar::query()->create(['label' => 'Diözese', 'ics_url' => self::URL, 'color' => '#003056']);
        CalendarEvent::query()->create(['calendar_id' => $dv->id, 'uid' => 'dv', 'starts_at' => now()->addDays(3)->setTime(10, 0), 'ends_at' => now()->addDays(3)->setTime(12, 0), 'title' => 'Diözesanversammlung']);

        $this->get('/kalender')->assertOk()->assertSee('Gruppenstunde Wölflinge')->assertSee('Diözesanversammlung')->assertSee('Pfingstlager');
        $this->get('/kalender?stufe=woelflinge')->assertOk()->assertSee('Gruppenstunde Wölflinge')->assertDontSee('Diözesanversammlung');
        $this->get('/kalender?kalender='.$dv->id)->assertOk()->assertSee('Diözesanversammlung')->assertDontSee('Gruppenstunde Wölflinge');

        $monat = now()->addDays(3)->format('Y-m');
        $this->get("/kalender?ansicht=monat&monat={$monat}")->assertOk()->assertSee('Diözesanversammlung');
        $this->get('/kalender?ansicht=monat&monat=kaputt')->assertOk();

        $json = $this->getJson('/api/calendar/events?from='.now()->toDateString().'&to='.now()->addMonths(3)->toDateString())->assertOk()->json();
        $this->assertContains('Diözesanversammlung', array_column($json, 'title'));
        $this->assertStringNotContainsString('@example.org', json_encode($json));
    }

    #[TestDox('Veranstaltungen aus dem Backend stehen im Kalender – mit Link, Stufenfilter und ohne Doppelung mit Nextcloud')]
    public function test_events_appear_in_calendar_without_duplicates(): void
    {
        $dv = Calendar::query()->create(['label' => 'Diözese', 'ics_url' => self::URL, 'color' => '#003056']);
        $tag = now()->addDays(10)->setTime(10, 0);
        Event::query()->create(['title' => 'Wölflingstag 2026', 'slug' => 'woelflingstag', 'location' => 'Trier', 'stage' => 'woelflinge', 'status' => 'open',
            'starts_at' => $tag, 'ends_at' => $tag->copy()->setTime(17, 0)]);
        Event::query()->create(['title' => 'Roverrunde', 'slug' => 'roverrunde', 'stage' => 'rover', 'status' => 'cancelled',
            'starts_at' => now()->addDays(12)->setTime(18, 0), 'ends_at' => now()->addDays(12)->setTime(20, 0)]);
        Event::query()->create(['title' => 'Entwurf', 'slug' => 'entwurf', 'status' => 'draft', 'starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(5)->addHour()]);
        // Dieselbe Veranstaltung steht zusätzlich in der Nextcloud (leicht anderer Titel).
        CalendarEvent::query()->create(['calendar_id' => $dv->id, 'uid' => 'doppelt', 'starts_at' => $tag->copy()->setTime(9, 30), 'ends_at' => $tag->copy()->setTime(17, 0), 'title' => 'Wölflingstag']);
        CalendarEvent::query()->create(['calendar_id' => $dv->id, 'uid' => 'dv', 'starts_at' => now()->addDays(3)->setTime(10, 0), 'ends_at' => now()->addDays(3)->setTime(12, 0), 'title' => 'Diözesanversammlung']);

        $this->get('/kalender')->assertOk()
            ->assertSeeInOrder(['Diözesanversammlung', 'Wölflingstag 2026', 'Anmeldung möglich', 'Infos und Anmeldung', 'Roverrunde', 'Abgesagt'])
            ->assertSee('/veranstaltungen/woelflingstag', false)
            ->assertDontSee('Entwurf');
        $this->assertSame(1, substr_count($this->get('/kalender')->getContent(), '>Wölflingstag'), 'Doppelter Termin aus der Nextcloud wird ausgeblendet');

        $this->get('/kalender?stufe=woelflinge')->assertSee('Wölflingstag 2026')->assertDontSee('Roverrunde')->assertDontSee('Diözesanversammlung');
        $this->get('/kalender?kalender=veranstaltungen')->assertSee('Wölflingstag 2026')->assertDontSee('Diözesanversammlung');
        $this->get('/kalender?kalender='.$dv->id)->assertSee('Diözesanversammlung')->assertDontSee('Roverrunde');
        $this->get('/kalender?ansicht=monat&monat='.$tag->format('Y-m'))->assertSee('Wölflingstag 2026');

        $json = $this->getJson('/api/calendar/events?from='.now()->toDateString().'&to='.now()->addMonths(1)->toDateString())->json();
        $event = collect($json)->firstWhere('title', 'Wölflingstag 2026');
        $this->assertSame(route('events.show', 'woelflingstag'), $event['url']);
        $this->assertNotContains('Wölflingstag', array_column($json, 'title'));
    }

    #[TestDox('Die Liste reicht ein Jahr voraus (Lager und Kurse stehen oft lange vorher fest)')]
    public function test_list_covers_twelve_months(): void
    {
        Event::query()->create(['title' => 'Sommerlager in elf Monaten', 'slug' => 'sola', 'status' => 'open', 'starts_at' => now()->addMonths(11), 'ends_at' => now()->addMonths(11)->addDays(7)]);
        Event::query()->create(['title' => 'Jubiläum in zwei Jahren', 'slug' => 'jubi', 'status' => 'open', 'starts_at' => now()->addYears(2), 'ends_at' => now()->addYears(2)->addHours(5)]);

        $this->get('/kalender')->assertOk()
            ->assertSee('Sommerlager in elf Monaten')
            ->assertDontSee('Jubiläum in zwei Jahren')
            ->assertSee('Die Liste zeigt Termine bis');
    }

    #[TestDox('Kalenderseite zeigt Veranstaltungen auch ohne eingetragenen Nextcloud-Kalender')]
    public function test_calendar_without_nextcloud_shows_events(): void
    {
        Event::query()->create(['title' => 'Leiterrunde', 'slug' => 'leiterrunde', 'status' => 'open', 'starts_at' => now()->addDays(4), 'ends_at' => now()->addDays(4)->addHours(2)]);

        $this->get('/kalender')->assertOk()->assertSee('Leiterrunde')->assertDontSee('Der Kalender wird gerade eingerichtet');
    }

    #[TestDox('Konfiguration für Kalender und Instagram ist vollständig')]
    public function test_services_config_keeps_the_calendar_and_instagram_keys(): void
    {
        // Liest die echte config/services.php (ohne Test-Override aus setUp):
        // Ein doppelter Schlüssel weiter unten würde diese Werte überschreiben.
        $config = require config_path('services.php');

        $this->assertArrayHasKey('allowed_hosts', $config['nextcloud']);
        $this->assertArrayHasKey('graph_url', $config['instagram']);
    }

    #[TestDox('Kalenderseite funktioniert auch ohne eingetragenen Kalender')]
    public function test_calendar_page_works_without_any_calendar(): void
    {
        $this->get('/kalender')->assertOk()->assertSee('Der Kalender wird gerade eingerichtet');
    }

    #[TestDox('Nur Admins verwalten Kalender; der geheime Kalenderlink erreicht nie den Browser')]
    public function test_only_admins_manage_calendars_and_stored_link_is_never_sent_to_browser(): void
    {
        Filament::setCurrentPanel('admin');
        $verwaltung = User::query()->create(['name' => 'Büro', 'email' => 'buero@dpsg-trier.de', 'status' => 'active']);
        $verwaltung->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);
        $admin = User::query()->create(['name' => 'Technik', 'email' => 'technik@dpsg-trier.de', 'status' => 'active']);
        $admin->roleAssignments()->create(['role' => 'admin', 'scope_type' => 'global']);
        $calendar = $this->calendar();

        $this->actingAs($verwaltung)->get('/admin/calendars')->assertForbidden();
        $this->actingAs($admin)->get('/admin/calendars')->assertOk();
        $this->actingAs($admin)->get("/admin/calendars/{$calendar->id}/edit")->assertOk()->assertDontSee('abc123');

        Livewire::test(EditCalendar::class, ['record' => $calendar->getRouteKey()])
            ->fillForm(['label' => 'Wölflinge DV'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(self::URL, $calendar->fresh()->ics_url);
        $this->assertSame('Wölflinge DV', $calendar->fresh()->label);

        Livewire::test(EditCalendar::class, ['record' => $calendar->getRouteKey()])
            ->fillForm(['ics_url' => 'https://evil.example.org/x.ics'])
            ->call('save')
            ->assertHasFormErrors(['ics_url']);
    }

    #[TestDox('Normaler Nextcloud-Freigabelink und webcal:// werden automatisch zur ICS-Adresse')]
    public function test_nextcloud_share_links_are_converted_to_ics_export(): void
    {
        $this->assertSame(self::URL, CalendarSync::normalizeUrl('https://cloud.dpsg-trier.de/index.php/apps/calendar/p/abc123'));
        $this->assertSame(self::URL, CalendarSync::normalizeUrl('https://cloud.dpsg-trier.de/apps/calendar/p/abc123/dayGridMonth/now'));
        $this->assertSame(self::URL, CalendarSync::normalizeUrl('webcal://cloud.dpsg-trier.de/remote.php/dav/public-calendars/abc123'));
        $this->assertSame(self::URL, CalendarSync::normalizeUrl(' '.self::URL.' '));

        // Bereits gespeicherter Freigabelink funktioniert ohne Neueingabe.
        Http::fake([self::URL => Http::response($this->ics(), 200, ['Content-Type' => 'text/calendar'])]);
        $calendar = $this->calendar(['ics_url' => 'https://cloud.dpsg-trier.de/index.php/apps/calendar/p/abc123']);
        $this->assertTrue(app(CalendarSync::class)->sync($calendar));
        $this->assertNull($calendar->fresh()->last_error);
    }

    #[TestDox('Leitet ein Link weiter, erklärt die Fehlermeldung, welcher Link richtig ist')]
    public function test_redirecting_link_gets_helpful_error(): void
    {
        Http::fake([self::URL => Http::response('', 303, ['Location' => 'https://cloud.dpsg-trier.de/login'])]);
        $calendar = $this->calendar();

        $this->assertFalse(app(CalendarSync::class)->sync($calendar));
        $this->assertStringContainsString('leitet weiter', $calendar->fresh()->last_error);
    }
}
