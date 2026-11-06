<?php

namespace Tests\Feature\Videokonferenzen;

use App\Filament\Resources\Videokonferenzen\Pages\CreateVideokonferenz;
use App\Filament\Resources\Videokonferenzen\Pages\ListVideokonferenzen;
use App\Filament\Resources\Videokonferenzen\Pages\ViewVideokonferenz;
use App\Mail\VideokonferenzMail;
use App\Models\User;
use App\Models\Videokonferenz;
use App\Services\Jitsi\Jitsi;
use App\Support\Features;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Videokonferenzen auf meet.dpsg-trier.de (docs/videokonferenzen.md,
 * Projektdokumentation Jitsi Kapitel 8).
 */
class VideokonferenzenTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'testsecret-0123456789abcdef0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();
        Features::setzen(Features::VIDEOKONFERENZEN, true);
        config(['services.jitsi' => ['url' => 'https://meet.dpsg-trier.de', 'secret' => self::SECRET, 'sub' => 'meet.dpsg-trier.de']]);
        Filament::setCurrentPanel('admin');
    }

    private function user(string $email, ?string $role = 'vorstand'): User
    {
        $user = User::query()->create(['name' => $email, 'email' => $email, 'status' => 'active']);
        if ($role) {
            $user->roleAssignments()->create(['role' => $role, 'scope_type' => 'global']);
        }

        return $user->fresh();
    }

    private static function b64uDecode(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/').str_repeat('=', (4 - strlen($s) % 4) % 4));
    }

    #[TestDox('Token: HS256-Signatur stimmt, Claims wie bei jitsi-openid, Base64url ohne Füllzeichen')]
    public function test_token(): void
    {
        $token = Jitsi::ausConfig()->token('dak-woelflinge-k3x9q2', 1_800_000_000, 'Moderator', 1_700_000_000);
        [$kopf, $inhalt, $signatur] = explode('.', $token);

        $this->assertSame(['alg' => 'HS256', 'typ' => 'JWT'], json_decode(self::b64uDecode($kopf), true));
        $this->assertSame([
            'iss' => 'jitsi', 'aud' => 'jitsi', 'sub' => 'meet.dpsg-trier.de', 'room' => 'dak-woelflinge-k3x9q2',
            'iat' => 1_700_000_000, 'exp' => 1_800_000_000, 'context' => ['user' => ['name' => 'Moderator']],
        ], json_decode(self::b64uDecode($inhalt), true));
        $erwartet = rtrim(strtr(base64_encode(hash_hmac('sha256', $kopf.'.'.$inhalt, self::SECRET, true)), '+/', '-_'), '=');
        $this->assertSame($erwartet, $signatur);
        $this->assertDoesNotMatchRegularExpression('/[+\/=]/', $token);
    }

    #[TestDox('Ablauf: 23:59:59 Uhr am Folgetag in Ortszeit, auch über Zeitumstellung und Jahreswechsel')]
    public function test_ablauf(): void
    {
        $tz = 'Europe/Berlin';
        $faelle = [
            ['2026-11-20 19:00', '2026-11-21 23:59:59'],
            ['2026-10-24 20:00', '2026-10-25 23:59:59'], // Umstellung auf Winterzeit
            ['2027-03-27 10:00', '2027-03-28 23:59:59'], // Umstellung auf Sommerzeit
            ['2026-12-31 23:30', '2027-01-01 23:59:59'],
            ['2026-11-20 00:15', '2026-11-21 23:59:59'],
        ];
        foreach ($faelle as [$beginn, $ende]) {
            $exp = Jitsi::ablauf(CarbonImmutable::parse($beginn, $tz));
            $this->assertSame($ende, CarbonImmutable::createFromTimestamp($exp, $tz)->format('Y-m-d H:i:s'), $beginn);
        }
        // Beginn in UTC angegeben: maßgeblich ist der Tag in Ortszeit (23:30 UTC = 00:30 am Folgetag in Berlin).
        $exp = Jitsi::ablauf(CarbonImmutable::parse('2026-11-20 23:30', 'UTC'));
        $this->assertSame('2026-11-22 23:59:59', CarbonImmutable::createFromTimestamp($exp, $tz)->format('Y-m-d H:i:s'));
    }

    #[TestDox('Raumname: klein, Umlaute ersetzt, höchstens 30 Zeichen plus zufälliger Anhang')]
    public function test_raumname(): void
    {
        $this->assertMatchesRegularExpression('/^gruppenstunde-woelflinge-[a-z2-9]{6}$/', Jitsi::raumName('Gruppenstunde Wölflinge'));
        $this->assertMatchesRegularExpression('/^strasse-mass-[a-z2-9]{6}$/', Jitsi::raumName('Straße & Maß!'));
        $this->assertMatchesRegularExpression('/^konferenz-[a-z2-9]{6}$/', Jitsi::raumName('!!!'));
        $lang = Jitsi::raumName(str_repeat('Diözesanversammlung ', 5));
        $this->assertLessThanOrEqual(37, strlen($lang));
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $lang);
        $this->assertNotSame(Jitsi::raumName('Test'), Jitsi::raumName('Test'));
    }

    #[TestDox('Moderator-Link nur bis zum Ablauf und nur mit Secret; Einladung ohne Token')]
    public function test_links(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-20 12:00', 'Europe/Berlin'));
        $k = Videokonferenz::query()->create(['titel' => 'DAK Wölflinge', 'beginn' => Carbon::parse('2026-11-20 19:00', 'Europe/Berlin')]);

        $this->assertStringStartsWith('https://meet.dpsg-trier.de/dak-woelflinge-', $k->teilnahmeLink());
        $this->assertStringStartsWith($k->teilnahmeLink().'?jwt=', (string) $k->moderatorLink());
        $this->assertStringNotContainsString('jwt', $k->einladung());
        $this->assertStringContainsString('Freitag, 20.11.2026, 19:00 Uhr', $k->einladung());

        Carbon::setTestNow(Carbon::parse('2026-11-22 00:00:01', 'Europe/Berlin'));
        $this->assertNull($k->moderatorLink());

        Carbon::setTestNow(Carbon::parse('2026-11-20 12:00', 'Europe/Berlin'));
        config(['services.jitsi.secret' => null]);
        $this->assertNull($k->moderatorLink());
        Carbon::setTestNow();
    }

    #[TestDox('Backend: anlegen führt zur Linkseite; jede*r sieht nur eigene, Verwaltung alle; ohne Rolle oder Funktion kein Zugriff')]
    public function test_backend(): void
    {
        $anna = $this->user('anna@dpsg-trier.de');
        $ben = $this->user('ben@dpsg-trier.de', 'stufenadmin');
        $verwaltung = $this->user('v@dpsg-trier.de', 'verwaltung');
        $autor = $this->user('autor@dpsg-trier.de', 'autor');

        $this->actingAs($anna);
        Livewire::test(CreateVideokonferenz::class)
            ->fillForm(['titel' => 'Vorstandssitzung', 'beginn' => now()->addDay()->setTime(19, 0)->format('Y-m-d H:i')])
            ->call('create')
            ->assertHasNoFormErrors();
        $k = Videokonferenz::query()->firstOrFail();
        $this->assertSame($anna->id, $k->user_id);
        $this->assertStringStartsWith('vorstandssitzung-', $k->raum);

        Livewire::test(ViewVideokonferenz::class, ['record' => $k->getRouteKey()])
            ->assertOk()->assertSee($k->teilnahmeLink())->assertSee('?jwt=', false);

        $this->actingAs($ben);
        Livewire::test(ListVideokonferenzen::class)->assertCanNotSeeTableRecords([$k]);
        $this->get('/admin/videokonferenzen/'.$k->id)->assertNotFound();

        $this->actingAs($verwaltung);
        Livewire::test(ListVideokonferenzen::class)->assertCanSeeTableRecords([$k]);

        $this->actingAs($autor)->get('/admin/videokonferenzen')->assertForbidden();

        Features::setzen(Features::VIDEOKONFERENZEN, false);
        $this->actingAs($anna)->get('/admin/videokonferenzen')->assertForbidden();
    }

    #[TestDox('Beginn mehr als einen Tag zurück wird abgelehnt')]
    public function test_beginn_in_der_vergangenheit(): void
    {
        $this->actingAs($this->user('anna@dpsg-trier.de'));
        Livewire::test(CreateVideokonferenz::class)
            ->fillForm(['titel' => 'Zu spät', 'beginn' => now()->subDays(3)->format('Y-m-d H:i')])
            ->call('create')
            ->assertHasFormErrors(['beginn']);
    }

    #[TestDox('Anlegen mit Adressen: je Person eine Mail, Moderation mit Token, Teilnehmende ohne; Adressen verschlüsselt')]
    public function test_einladungen_beim_anlegen(): void
    {
        Mail::fake();
        $anna = $this->user('anna@dpsg-trier.de');
        $this->actingAs($anna);

        Livewire::test(CreateVideokonferenz::class)
            ->fillForm([
                'titel' => 'DAK Wölflinge',
                'beginn' => now()->addDay()->setTime(19, 0)->format('Y-m-d H:i'),
                'dauer_minuten' => 90,
                'moderation_emails' => ['Mod@Example.org'],
                'teilnehmende_emails' => ['a@example.org', 'b@example.org', 'mod@example.org', 'a@example.org'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $k = Videokonferenz::query()->firstOrFail();
        $this->assertSame(['mod@example.org'], $k->moderation_emails);
        $this->assertNotNull($k->eingeladen_am);
        $roh = (string) DB::table('videokonferenzen')->value('teilnehmende_emails');
        $this->assertStringNotContainsString('example.org', $roh);

        Mail::assertQueued(VideokonferenzMail::class, 3);
        Mail::assertQueued(VideokonferenzMail::class, fn (VideokonferenzMail $m) => $m->moderation && $m->hasTo('mod@example.org'));
        Mail::assertQueued(VideokonferenzMail::class, fn (VideokonferenzMail $m) => ! $m->moderation && $m->hasTo('a@example.org'));
        Mail::assertNotQueued(VideokonferenzMail::class, fn (VideokonferenzMail $m) => ! $m->moderation && $m->hasTo('mod@example.org'));
    }

    #[TestDox('Mailinhalt: Moderation mit Moderator-Link, Teilnehmende nur mit Teilnahme-Link; Kalendereintrag ohne Token')]
    public function test_mailinhalt_und_kalender(): void
    {
        $anna = $this->user('anna@dpsg-trier.de');
        $k = Videokonferenz::query()->create(['titel' => 'Vorstand, Sitzung', 'beginn' => now()->addDay()->setTime(19, 0), 'dauer_minuten' => 90, 'user_id' => $anna->id]);

        $mod = (new VideokonferenzMail($k, true))->render();
        $teil = (new VideokonferenzMail($k, false))->render();
        $this->assertStringContainsString('?jwt=', $mod);
        $this->assertStringContainsString('nicht weitergeben', $mod);
        $this->assertStringNotContainsString('jwt', $teil);
        $this->assertStringContainsString($k->teilnahmeLink(), $teil);
        $antwortAn = (new VideokonferenzMail($k))->envelope()->replyTo;
        $this->assertSame('anna@dpsg-trier.de', $antwortAn[0]->address ?? null);

        $ics = $k->ics();
        $this->assertStringContainsString('DTSTART:'.$k->beginn->copy()->utc()->format('Ymd\\THis\\Z'), $ics);
        $this->assertStringContainsString('DTEND:'.$k->beginn->copy()->addMinutes(90)->utc()->format('Ymd\\THis\\Z'), $ics);
        $this->assertStringContainsString('SUMMARY:Vorstand\\, Sitzung', $ics);
        $this->assertStringNotContainsString('jwt', $ics);
        $this->assertCount(1, (new VideokonferenzMail($k))->attachments());
    }

    #[TestDox('Detailseite: „Einladungen senden“ lädt nach und merkt sich die Adressen')]
    public function test_nachladen(): void
    {
        Mail::fake();
        $anna = $this->user('anna@dpsg-trier.de');
        $this->actingAs($anna);
        $k = Videokonferenz::query()->create(['titel' => 'Leiterrunde', 'beginn' => now()->addDay(), 'user_id' => $anna->id, 'teilnehmende_emails' => ['a@example.org']]);

        Livewire::test(ViewVideokonferenz::class, ['record' => $k->getRouteKey()])
            ->callAction('einladen', ['moderation_emails' => [], 'teilnehmende_emails' => ['c@example.org']])
            ->assertHasNoActionErrors();

        Mail::assertQueued(VideokonferenzMail::class, 1);
        Mail::assertQueued(VideokonferenzMail::class, fn (VideokonferenzMail $m) => $m->hasTo('c@example.org'));
        $this->assertSame(['a@example.org', 'c@example.org'], $k->fresh()->teilnehmende_emails);
    }

    #[TestDox('Aufräumen: Adressen 7 Tage nach der Konferenz weg, Konferenzen nach 12 Monaten')]
    public function test_aufraeumen(): void
    {
        $alt = Videokonferenz::query()->create(['titel' => 'Alt', 'beginn' => now()->subDays(8), 'teilnehmende_emails' => ['a@example.org']]);
        $neu = Videokonferenz::query()->create(['titel' => 'Neu', 'beginn' => now()->subDays(2), 'teilnehmende_emails' => ['b@example.org']]);
        $uralt = Videokonferenz::query()->create(['titel' => 'Uralt', 'beginn' => now()->subMonths(13)]);

        $this->artisan('videokonferenzen:aufraeumen')->assertSuccessful();

        $this->assertNull($alt->fresh()->teilnehmende_emails);
        $this->assertSame(['b@example.org'], $neu->fresh()->teilnehmende_emails);
        $this->assertNull($uralt->fresh());
    }
}
