<?php

namespace Tests\Feature\Calendar;

use App\Filament\Resources\Calendars\Pages\EditCalendar;
use App\Models\Calendar;
use App\Models\CalendarEvent;
use App\Models\User;
use App\Services\CalendarSync;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
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

    public function test_allowed_hosts_come_from_the_environment_variable(): void
    {
        // Liest die echte config/services.php (ohne Test-Override aus setUp).
        putenv('NEXTCLOUD_ALLOWED_HOSTS=cloud.example.org, nc.example.org');
        $config = require config_path('services.php');
        putenv('NEXTCLOUD_ALLOWED_HOSTS');

        $this->assertSame('cloud.example.org, nc.example.org', $config['nextcloud']['allowed_hosts']);
    }

    public function test_calendar_page_works_without_any_calendar(): void
    {
        $this->get('/kalender')->assertOk()->assertSee('Der Kalender wird gerade eingerichtet');
    }

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
}
