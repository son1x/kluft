<?php

namespace Tests\Feature\Aufrufe;

use App\Filament\Pages\Aufrufe as AufrufeSeite;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Widgets\AufrufeVerlauf;
use App\Filament\Widgets\Meistgelesen;
use App\Models\GeorgsboteIssue;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Seitenaufruf;
use App\Models\User;
use App\Support\Aufrufe;
use App\Support\Features;
use App\Support\ParticipantLogin;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Seitenaufrufe zählen ohne Personenbezug (docs/aufrufe.md,
 * docs/auftrag-aufrufzaehler.md A7). Läuft in allen Instanzen, der Schalter
 * wird dafür eingeschaltet.
 */
class AufrufeTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (X11; Linux x86_64; rv:140.0) Gecko/20100101 Firefox/140.0';

    protected function setUp(): void
    {
        parent::setUp();
        Features::setzen(Features::AUFRUFE, true);
        $this->withHeaders(['User-Agent' => self::BROWSER]);
    }

    /** @return array<string, int> Pfad => Aufrufe heute */
    private function zaehler(): array
    {
        return Seitenaufruf::query()->whereDate('datum', today())->pluck('aufrufe', 'pfad')->map(fn ($n) => (int) $n)->all();
    }

    private function seite(string $slug = 'ueber-uns', string $titel = 'Über uns'): Page
    {
        return Page::query()->create(['slug' => $slug, 'title' => $titel, 'body' => '<p>Text</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
    }

    private function beitrag(string $slug = 'sommerlager', string $titel = 'Sommerlager'): Post
    {
        return Post::query()->create(['title' => $titel, 'slug' => $slug, 'teaser' => 't', 'body' => '<p>b</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
    }

    private function user(string $role, string $scopeType = 'global'): User
    {
        $user = User::query()->create(['name' => ucfirst($role), 'email' => $role.'@dpsg-trier.de', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => $role, 'scope_type' => $scopeType]);

        return $user->fresh();
    }

    #[TestDox('Startseite, Seite und Beitrag zählen je Aufruf genau einmal; der zweite Aufruf am selben Tag erhöht auf 2')]
    public function test_zaehlt_oeffentliche_seiten(): void
    {
        $this->seite();
        $this->beitrag();

        $this->get('/')->assertOk();
        $this->get('/ueber-uns')->assertOk();
        $this->get('/aktuelles/sommerlager')->assertOk();
        $this->assertSame(['/' => 1, '/aktuelles/sommerlager' => 1, '/ueber-uns' => 1], collect($this->zaehler())->sortKeys()->all());

        $this->get('/ueber-uns/')->assertOk();
        $this->assertSame(2, $this->zaehler()['/ueber-uns']);
    }

    #[TestDox('Query-String wird entfernt, Pfad kleingeschrieben')]
    public function test_pfad_normalisiert(): void
    {
        $this->get('/aktuelles?seite=2')->assertOk();
        $this->assertSame(['/aktuelles' => 1], $this->zaehler());

        $this->assertSame('/', Aufrufe::pfad(''));
        $this->assertSame('/', Aufrufe::pfad('/'));
        $this->assertSame('/aktuelles/lager', Aufrufe::pfad('/Aktuelles/Lager/?x=1#oben'));
        $this->assertSame(255, mb_strlen(Aufrufe::pfad('/'.str_repeat('a', 400))));
    }

    #[TestDox('Nicht gezählt: Bots, leerer User-Agent, HEAD, 404, Weiterleitung')]
    public function test_nicht_gezaehlt_bots_head_404_weiterleitung(): void
    {
        $this->seite();
        Redirect::query()->create(['from_path' => '/alte-seite', 'to_path' => '/ueber-uns', 'status_code' => 301]);

        foreach (['Googlebot/2.1 (+http://www.google.com/bot.html)', 'curl/8.5.0', 'Mozilla/5.0 HeadlessChrome/120', 'WhatsApp/2.23', 'UptimeRobot/2.0', ''] as $agent) {
            $this->withHeaders(['User-Agent' => $agent])->get('/ueber-uns')->assertOk();
        }
        $this->withHeaders(['User-Agent' => self::BROWSER]);
        $this->call('HEAD', '/ueber-uns')->assertOk();
        $this->get('/gibt-es-nicht')->assertNotFound();
        $this->get('/alte-seite')->assertRedirect();

        $this->assertSame([], $this->zaehler());
    }

    #[TestDox('Nicht gezählt: Backend, /api/status, PDFs')]
    public function test_nicht_gezaehlt_backend_api_pdf(): void
    {
        $this->get('/admin/login');
        $this->get('/api/status');
        $this->get('/status');
        if (Features::enabled(Features::GEORGSBOTE)) {
            Storage::fake('local');
            Storage::disk('local')->put('georgsbote/2025-1.pdf', '%PDF-1.4');
            GeorgsboteIssue::query()->create(['year' => 2025, 'issue_no' => 1, 'title' => 'Heft', 'pdf_path' => 'georgsbote/2025-1.pdf', 'published' => true, 'privacy_checked' => true]);
            $this->get('/georgsbote/2025/1.pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        }

        $this->assertArrayNotHasKey('/admin/login', $this->zaehler());
        $this->assertArrayNotHasKey('/api/status', $this->zaehler());
        $this->assertSame([], array_filter($this->zaehler(), fn (string $pfad) => str_ends_with($pfad, '.pdf'), ARRAY_FILTER_USE_KEY));
    }

    #[TestDox('Nicht gezählt: angemeldete Redaktion und angemeldete Teilnehmende')]
    public function test_nicht_gezaehlt_angemeldete(): void
    {
        $this->seite();

        $this->actingAs($this->user('autor', 'own'))->get('/ueber-uns')->assertOk();
        auth()->logout();

        $this->withSession(['teilnehmende' => ['email' => 'eltern@example.org', 'until' => now()->addHour()->timestamp]])
            ->get('/ueber-uns')->assertOk();
        $this->assertNotNull(app(ParticipantLogin::class)->email(request()));

        $this->assertSame([], $this->zaehler());
    }

    #[TestDox('Nicht gezählt: „Do Not Track“, „Global Privacy Control“, Vorabladen')]
    public function test_nicht_gezaehlt_widerspruch_und_prefetch(): void
    {
        $this->seite();

        $this->withHeaders(['DNT' => '1'])->get('/ueber-uns')->assertOk();
        $this->withHeaders(['DNT' => null, 'Sec-GPC' => '1'])->get('/ueber-uns')->assertOk();
        $this->withHeaders(['Sec-GPC' => null, 'Sec-Purpose' => 'prefetch;prerender'])->get('/ueber-uns')->assertOk();
        $this->withHeaders(['Sec-Purpose' => null, 'Purpose' => 'prefetch'])->get('/ueber-uns')->assertOk();
        $this->assertSame([], $this->zaehler());

        $this->withHeaders(['Purpose' => null, 'DNT' => '0'])->get('/ueber-uns')->assertOk();
        $this->assertSame(['/ueber-uns' => 1], $this->zaehler());
    }

    #[TestDox('In der Datenbank stehen nur Tag, Pfad und Anzahl – keine IP, kein User-Agent')]
    public function test_nur_tag_pfad_anzahl(): void
    {
        $this->seite();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])->get('/ueber-uns')->assertOk();

        $this->assertEqualsCanonicalizing(['datum', 'pfad', 'aufrufe'], Schema::getColumnListing('seitenaufrufe'));
        $zeilen = DB::table('seitenaufrufe')->get()->map(fn ($zeile) => (array) $zeile)->all();
        $this->assertCount(1, $zeilen);
        $this->assertSame('/ueber-uns', $zeilen[0]['pfad']);
        $this->assertSame(1, (int) $zeilen[0]['aufrufe']);
        $this->assertStringStartsWith(today()->toDateString(), (string) $zeilen[0]['datum']);

        $alles = json_encode($zeilen, JSON_UNESCAPED_UNICODE);
        foreach (['203.0.113', 'Mozilla', 'Firefox'] as $verboten) {
            $this->assertStringNotContainsString($verboten, $alles);
        }
    }

    #[TestDox('Schalter aus: nichts gezählt, Seite „Aufrufe“, Widget und Angabe beim Bearbeiten unsichtbar')]
    public function test_schalter_aus(): void
    {
        Features::setzen(Features::AUFRUFE, false);
        $this->seite();
        $this->get('/ueber-uns')->assertOk();
        $this->assertSame([], $this->zaehler());

        Filament::setCurrentPanel('admin');
        $this->actingAs($this->user('verwaltung'));
        $this->assertFalse(AufrufeSeite::canAccess());
        $this->assertFalse(Meistgelesen::canView());
        $this->assertFalse(AufrufeVerlauf::canView());
        $this->get('/admin/aufrufe')->assertForbidden();
        $this->get('/admin')->assertOk()->assertDontSee('Meistgelesen (30 Tage)');
        $this->assertNull(Aufrufe::angabe('/ueber-uns'));
    }

    #[TestDox('Standard: nur beim DV an, bei Stamm und Bezirk aus')]
    public function test_standard_je_instanz(): void
    {
        $instanz = getenv('KLUFT_INSTANZ') ?: 'dv-trier';
        $this->assertSame($instanz === 'dv-trier', Features::standard(Features::AUFRUFE));
        $this->assertFalse(Features::KATALOG[Features::AUFRUFE]['standard']);
    }

    #[TestDox('Rechte: Autor*in sieht „Aufrufe“ nicht, Verwaltung und Vorstand schon')]
    public function test_rechte(): void
    {
        Filament::setCurrentPanel('admin');

        $this->actingAs($this->user('autor', 'own'))->get('/admin/aufrufe')->assertForbidden();
        $this->assertFalse(Meistgelesen::canView());

        foreach (['verwaltung', 'vorstand'] as $rolle) {
            $this->actingAs($this->user($rolle))->get('/admin/aufrufe')->assertOk()
                ->assertSee('Gezählt werden Aufrufe ohne Bots und ohne angemeldete Redaktion.');
            $this->assertTrue(Meistgelesen::canView());
        }
    }

    #[TestDox('Seite „Aufrufe“: Titel statt Pfad, Summe im Zeitraum, Suche, Zeitraum wählbar')]
    public function test_backend_seite(): void
    {
        $this->seite();
        $this->beitrag();
        DB::table('seitenaufrufe')->insert([
            ['datum' => today()->toDateString(), 'pfad' => '/', 'aufrufe' => 7],
            ['datum' => today()->toDateString(), 'pfad' => '/ueber-uns', 'aufrufe' => 3],
            ['datum' => today()->subDays(3)->toDateString(), 'pfad' => '/aktuelles/sommerlager', 'aufrufe' => 5],
            ['datum' => today()->subDays(40)->toDateString(), 'pfad' => '/alt', 'aufrufe' => 100],
        ]);

        Filament::setCurrentPanel('admin');
        $this->actingAs($this->user('verwaltung'));

        Livewire::test(AufrufeSeite::class)
            ->assertSee('Startseite')->assertSee('Über uns')->assertSee('Sommerlager')
            ->assertDontSee('/alt')
            ->searchTable('Sommer')
            ->assertSee('Sommerlager')->assertDontSee('Über uns')
            ->searchTable('')
            ->set('zeitraum', '7')
            ->assertSee('Sommerlager');

        Livewire::test(AufrufeVerlauf::class, ['zeitraum' => '30'])->assertSee('Summe im Zeitraum: 15 Aufrufe');
        Livewire::test(AufrufeVerlauf::class, ['zeitraum' => 'jahr'])->assertSee('Aktuelles Jahr');

        $this->assertSame(['Startseite', 'Sommerlager', 'Über uns'], collect(Aufrufe::top(...Aufrufe::zeitraum('30')))
            ->map(fn ($zeile) => Aufrufe::titel([$zeile->pfad])[$zeile->pfad])->all());
    }

    #[TestDox('Dashboard-Widget „Meistgelesen (30 Tage)“ und Angabe beim Bearbeiten eines Beitrags')]
    public function test_widget_und_angabe_beim_bearbeiten(): void
    {
        $beitrag = $this->beitrag();
        DB::table('seitenaufrufe')->insert([
            ['datum' => today()->toDateString(), 'pfad' => '/aktuelles/sommerlager', 'aufrufe' => 4],
            ['datum' => today()->subMonths(3)->toDateString(), 'pfad' => '/aktuelles/sommerlager', 'aufrufe' => 6],
        ]);

        Filament::setCurrentPanel('admin');
        $this->actingAs($this->user('verwaltung'));
        Livewire::test(Meistgelesen::class)->assertSee('Meistgelesen (30 Tage)')->assertSee('Sommerlager')->assertSee('Alle Aufrufe ansehen');

        $this->actingAs($autor = $this->user('autor', 'own'));
        $beitrag->update(['author_id' => $autor->id]);
        Livewire::test(EditPost::class, ['record' => $beitrag->getRouteKey()])->assertSee('Aufrufe: 30 Tage 4, gesamt 10');
        $this->assertSame('Aufrufe: 30 Tage 0, gesamt 0', Aufrufe::angabe('/gibt-es-nicht'));
    }

    #[TestDox('Aufräumen löscht nur Zähler älter als 25 Monate')]
    public function test_aufraeumen(): void
    {
        DB::table('seitenaufrufe')->insert([
            ['datum' => today()->subMonths(25)->subDay()->toDateString(), 'pfad' => '/alt', 'aufrufe' => 1],
            ['datum' => today()->subMonths(25)->toDateString(), 'pfad' => '/grenze', 'aufrufe' => 1],
            ['datum' => today()->subMonths(13)->toDateString(), 'pfad' => '/vorjahr', 'aufrufe' => 1],
        ]);

        $this->artisan('aufrufe:aufraeumen')->assertSuccessful();

        $this->assertEqualsCanonicalizing(['/grenze', '/vorjahr'], Seitenaufruf::query()->pluck('pfad')->all());
    }

    #[TestDox('Fehler beim Schreiben (z. B. fehlende Tabelle) machen keine Seite kaputt')]
    public function test_fehler_beim_schreiben(): void
    {
        $this->seite();
        Schema::drop('seitenaufrufe');

        $this->get('/ueber-uns')->assertOk()->assertSee('Über uns');
    }

    #[TestDox('Livegang: Zähler zurücksetzen per Befehl und Knopf (nur Admins); Seite zeigt danach 0')]
    public function test_zuruecksetzen(): void
    {
        Seitenaufruf::query()->insert([
            ['datum' => today()->subDays(3)->toDateString(), 'pfad' => '/', 'aufrufe' => 40],
            ['datum' => today()->toDateString(), 'pfad' => '/aktuelles', 'aufrufe' => 2],
        ]);
        $this->assertSame(42, Aufrufe::gesamt());
        $this->assertSame(today()->subDays(3)->toDateString(), Aufrufe::seit()->toDateString());

        $this->artisan('aufrufe:zuruecksetzen', ['--force' => true])->assertSuccessful();
        $this->assertSame(0, Aufrufe::gesamt());
        $this->assertNull(Aufrufe::seit());

        Seitenaufruf::query()->insert(['datum' => today()->toDateString(), 'pfad' => '/', 'aufrufe' => 5]);
        Filament::setCurrentPanel('admin');

        $this->actingAs($this->user('verwaltung'));
        Livewire::test(AufrufeSeite::class)->assertActionHidden('zuruecksetzen');

        $this->actingAs($this->user('admin'));
        $this->get('/admin/aufrufe')->assertOk()->assertSee('5 Aufrufe insgesamt');
        Livewire::test(AufrufeSeite::class)->callAction('zuruecksetzen')->assertNotified();
        $this->assertSame(0, Seitenaufruf::query()->count());
        $this->get('/admin/aufrufe')->assertOk()->assertSee('Noch keine Aufrufe gezählt (0).');
    }
}
