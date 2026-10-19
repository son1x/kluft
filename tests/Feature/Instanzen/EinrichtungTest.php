<?php

namespace Tests\Feature\Instanzen;

use App\Models\Gruppierung;
use App\Models\Page;
use App\Models\Person;
use App\Models\User;
use App\Support\Ausbau;
use App\Support\Einrichtung\Installer;
use App\Support\Einrichtung\StammVorlage;
use Database\Seeders\Einrichtung\StammStartSeeder;
use Dotenv\Dotenv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Einrichtungsassistent für Stammes-Websites (docs/einrichtung.md).
 * Der komplette Ablauf im Browser wird vor jedem Release von Hand in einer
 * frischen Kopie geprüft (s. dort); hier die Bausteine.
 */
class EinrichtungTest extends TestCase
{
    use RefreshDatabase;

    private string $basis;

    /** @var array<string, string> */
    private const FORMULAR = [
        'stamm' => 'Stamm Don Bosco', 'ort' => 'Beispielstadt', 'nummer' => '21 02 08', 'dioezese' => 'Trier',
        'domain' => 'https://Stamm-Don-Bosco.de/', 'email' => 'Vorstand@stamm-don-bosco.de', 'vorstand' => 'Erika Beispiel',
        'strasse' => 'Kirchplatz 1', 'plz_ort' => '54290 Beispielstadt',
        'db_art' => 'sqlite', 'mail_host' => 'smtp.example.org', 'mail_port' => '465', 'mail_user' => 'web', 'mail_pass' => 'geh\'eim$pa"ss',
        'mail_from' => 'website@stamm-don-bosco.de', 'admin_kind' => 'rolle', 'admin_name' => 'Stammesvorstand', 'admin_email' => 'vorstand@stamm-don-bosco.de',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->basis = sys_get_temp_dir().'/kluft-einrichtung-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->basis.'/storage');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->basis);
        parent::tearDown();
    }

    #[TestDox('Formular: Angaben werden bereinigt; Pflichtfelder und Formate werden geprüft')]
    public function test_pruefen(): void
    {
        $installer = new Installer($this->basis);
        $ergebnis = $installer->pruefen([...self::FORMULAR, 'stufen' => ['woelflinge', 'rover', 'unsinn']]);

        $this->assertSame([], $ergebnis['fehler']);
        $a = $ergebnis['angaben'];
        $this->assertSame('Don Bosco', $a['stamm']);
        $this->assertSame('210208', $a['nummer']);
        $this->assertSame('stamm-don-bosco.de', $a['domain']);
        $this->assertSame('vorstand@stamm-don-bosco.de', $a['email']);
        $this->assertSame(['woelflinge', 'rover'], $a['stufen']);
        $this->assertSame('rolle', $a['_zugang']['kind']);
        $this->assertSame('', $a['dioezese_url']);

        $a = $installer->pruefen([...self::FORMULAR, 'stufen' => ['rover'], 'eltern_url' => 'www.dpsg-trier.de'])['angaben'];
        $this->assertSame('https://www.dpsg-trier.de', $a['dioezese_url']);
        $this->assertArrayNotHasKey('eltern_url', $a);
        $a = $installer->pruefen([...self::FORMULAR, 'stufen' => ['rover'], 'bezirk' => 'Mosel', 'eltern_url' => 'https://bezirk-mosel.de'])['angaben'];
        $this->assertSame('https://bezirk-mosel.de', $a['bezirk_url']);

        $fehler = $installer->pruefen(['nummer' => '123', 'domain' => 'kein domain', 'eltern_url' => 'kein link'])['fehler'];
        $this->assertEqualsCanonicalizing(['stamm', 'email', 'domain', 'nummer', 'eltern_url', 'stufen', 'mail', 'admin'], array_keys($fehler));
        $this->assertSame('stamm-don-bosco', Installer::schluessel('Don Bosco'));
        $this->assertSame('stamm-st-georg-oe', Installer::schluessel('St. Georg Ö'));
    }

    #[TestDox('Vorbelegung: füllt nur erlaubte Felder vor, nie Passwörter; Gruppenstunden nur für gewählte Stufen')]
    public function test_vorbelegung_und_gruppenstunden(): void
    {
        $installer = new Installer($this->basis);
        $this->assertSame([], $installer->vorbelegung());

        File::ensureDirectoryExists(dirname($this->basis.'/'.Installer::VORBELEGUNG_DATEI));
        File::put($this->basis.'/'.Installer::VORBELEGUNG_DATEI, json_encode([
            'stamm' => 'Don Bosco', 'stufen' => ['biber', 'rover'], 'gruppenstunden' => ['biber' => 'dienstags, 16 Uhr'],
            'mail_pass' => 'geheim', 'db_pass' => 'geheim', 'unbekannt' => 'x', 'nami' => true,
            'seiten' => [
                ['slug' => 'unser-stamm', 'title' => 'Unser Stamm', 'body' => '<p>Geschichte</p>'],
                ['slug' => '../böse', 'title' => 'X', 'body' => '<p>x</p>'],
                ['slug' => 'ohne-text', 'title' => 'Ohne Text'],
            ],
        ]));
        $this->assertSame(['unser-stamm'], array_column($installer->vorbereiteteSeiten(), 'slug'));
        $v = $installer->vorbelegung();
        $this->assertSame('Don Bosco', $v['stamm']);
        $this->assertSame(['biber', 'rover'], $v['stufen']);
        $this->assertArrayNotHasKey('mail_pass', $v);
        $this->assertArrayNotHasKey('db_pass', $v);
        $this->assertArrayNotHasKey('unbekannt', $v);
        $this->assertSame('1', $v['nami']);

        $a = $installer->pruefen([...self::FORMULAR, 'stufen' => ['biber', 'rover'], 'gruppenstunden' => [
            'biber' => '  dienstags,   16:00–17:00 Uhr ', 'rover' => '', 'pfadi' => 'nicht gewählt',
        ]])['angaben'];
        $this->assertSame(['biber' => 'dienstags, 16:00–17:00 Uhr'], $a['gruppenstunden']);
        $this->assertSame(Ausbau::GRUPPEN, $a['ausbau']);

        $a = $installer->pruefen([...self::FORMULAR, 'stufen' => ['biber'], 'ausbau' => '4', 'gruppenstunden_orte' => ['biber' => ' Pfarrheim  St. Martin ', 'rover' => 'nicht gewählt']])['angaben'];
        $this->assertSame(Ausbau::ANMELDUNGEN, $a['ausbau']);
        $this->assertSame(['biber' => 'Pfarrheim St. Martin'], $a['gruppenstunden_orte']);
        $this->assertSame(Ausbau::GRUPPEN, $installer->pruefen([...self::FORMULAR, 'stufen' => ['biber'], 'ausbau' => '9'])['angaben']['ausbau']);
        $this->assertSame(Ausbau::ANMELDUNGEN, StammVorlage::config(['stamm' => 'X', 'ausbau' => 4])['ausbau']);
    }

    #[TestDox('Einrichtungscode: liegt nur unter storage/, wird genau verglichen; mit .env gilt die Installation als eingerichtet')]
    public function test_code_und_sperre(): void
    {
        $installer = new Installer($this->basis);
        $code = $installer->code();
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{12}$/', $code);
        $this->assertSame($code, $installer->code());
        $this->assertTrue($installer->codeStimmt(strtolower(substr($code, 0, 6)).' '.substr($code, 6)));
        $this->assertFalse($installer->codeStimmt('FALSCH'));
        $this->assertFileExists($this->basis.'/'.Installer::CODE_DATEI);

        $this->assertFalse($installer->eingerichtet());
        File::put($this->basis.'/.env', 'APP_KEY=x');
        $this->assertTrue($installer->eingerichtet());
        $this->expectExceptionMessage('bereits eingerichtet');
        $installer->einrichten($installer->pruefen([...self::FORMULAR, 'stufen' => ['rover']])['angaben']);
    }

    #[TestDox('.env: alle Werte – auch Passwörter mit Sonderzeichen – kommen unverändert bei Laravel an')]
    public function test_env(): void
    {
        $installer = new Installer($this->basis);
        $a = $installer->pruefen([...self::FORMULAR, 'stufen' => ['rover']])['angaben'];
        $env = $installer->env('stamm-don-bosco', $a, $a['_db'], $a['_mail'], 'base64:'.base64_encode(random_bytes(32)), 'backup-geheim');

        $werte = Dotenv::parse($env);
        $this->assertSame('stamm-don-bosco', $werte['KLUFT_INSTANZ']);
        $this->assertSame('https://stamm-don-bosco.de', $werte['APP_URL']);
        $this->assertSame('geh\'eim$pa"ss', $werte['MAIL_PASSWORD']);
        $this->assertSame('smtps', $werte['MAIL_SCHEME']);
        $this->assertSame('sqlite', $werte['DB_CONNECTION']);
        $this->assertSame('false', $werte['APP_DEBUG']);
        $this->assertSame('off', $werte['KLUFT_UPDATES']);
        $this->assertSame('DPSG Stamm Don Bosco', $werte['APP_NAME']);
        $this->assertSame('off', $werte['MITGLIEDER_DRIVER']);

        $a = $installer->pruefen([...self::FORMULAR, 'stufen' => ['rover'], 'nami' => '1'])['angaben'];
        $this->assertTrue($a['nami']);
        $this->assertSame('nami', Dotenv::parse($installer->env('stamm-don-bosco', $a, $a['_db'], $a['_mail'], 'base64:x', 'b'))['MITGLIEDER_DRIVER']);

        foreach (['einfach', 'mit leerzeichen', 'a\'b', 'a"b\\c', '$HOME', 'x#y'] as $wert) {
            $this->assertSame($wert, Dotenv::parse('X='.Installer::wert($wert))['X'], $wert);
        }
    }

    #[TestDox('Vorlage: vollständige Stammes-Konfiguration ohne Verzeichnis und Karte, Code-Login an')]
    public function test_vorlage(): void
    {
        $c = StammVorlage::config(['stamm' => 'Don Bosco', 'ort' => 'Beispielstadt', 'email' => 'vorstand@stamm-don-bosco.de', 'domain' => 'stamm-don-bosco.de', 'telefon' => '0651 123 45', 'dioezese' => 'Trier']);

        foreach (array_keys((array) require base_path('instanzen/saar-hochwald/config.php')) as $schluessel) {
            $this->assertArrayHasKey($schluessel, $c, $schluessel);
        }
        $this->assertSame('DPSG Stamm Don Bosco', $c['site']['name']);
        $this->assertSame('Stammesvorstand', $c['site']['office']['label']);
        $this->assertNull($c['directory']['path']);
        $this->assertNull($c['map']['pmtiles']);
        $this->assertTrue($c['features']['code_login']);
        $this->assertSame('+4965112345', $c['contact']['phone_href']);
        $this->assertStringContainsString('vorstand@stamm-don-bosco.de', $c['event_consent']['text']);
        $this->assertSame(StammStartSeeder::class, $c['seeders']['start']);
        $this->assertSame('https://www.dpsg-trier.de', $c['site']['parent']['url']);

        // Ohne bekannte Ebene kein doppelter Link auf dpsg.de, mit Bezirks-Website der Bezirk.
        $this->assertNull(StammVorlage::config(['stamm' => 'Don Bosco', 'dioezese' => 'Köln'])['site']['parent']);
        $this->assertSame(
            ['label' => 'DPSG Bezirk Mosel', 'url' => 'https://bezirk-mosel.de'],
            StammVorlage::config(['stamm' => 'Don Bosco', 'dioezese' => 'Trier', 'bezirk' => 'Mosel', 'bezirk_url' => 'https://bezirk-mosel.de'])['site']['parent'],
        );
    }

    #[TestDox('Instanz im Repository (Roden): vollständige Konfiguration; vorbereitete Seiten ersetzen frische Startseiten, Gepflegtes bleibt')]
    public function test_instanz_roden(): void
    {
        $c = require base_path('instanzen/roden/config.php');
        foreach (array_keys((array) require base_path('instanzen/saar-hochwald/config.php')) as $schluessel) {
            $this->assertArrayHasKey($schluessel, $c, $schluessel);
        }
        $this->assertSame('DPSG Stamm Graf Folke Bernadotte', $c['site']['name']);
        $this->assertSame(Ausbau::ANMELDUNGEN, $c['ausbau']);
        $this->assertFileExists(base_path('instanzen/roden/public/images/logo.png'));
        $this->assertNotEmpty(Installer::seitenPruefen($c['einrichtung']['seiten']));

        config(['dpsg' => array_replace(config('dpsg'), $c)]);
        Page::query()->delete();
        DB::table('contact_topics')->delete();
        $this->seed(StammStartSeeder::class);

        $this->assertSame('Pfadfinden in Saarlouis-Roden', Page::query()->where('slug', 'start')->value('title'));
        $this->assertStringContainsString('Kimberly Lamprecht', (string) Page::query()->where('slug', 'stammesvorstand')->value('body'));
        $this->assertTrue(Page::query()->where('slug', 'foerderverein')->where('show_in_nav', true)->exists());
        $this->assertStringContainsString('dienstags, 16:00–17:00 Uhr', (string) Page::query()->where('slug', 'stufe-biber')->value('body'));

        // Erster Zugang aus angaben.json: Rollen-Zugang „Stammesvorstand“ als Admin mit E-Mail-Code.
        $zugang = User::query()->where('email', 'kontakt@dpsg-roden.de')->firstOrFail();
        $this->assertSame(['email', 'rolle', 'active'], [$zugang->login, $zugang->kind, $zugang->status]);
        $this->assertTrue($zugang->fresh()->isAdmin());

        Page::query()->where('slug', 'start')->update(['title' => 'Von Hand geändert']);
        $this->seed(StammStartSeeder::class);
        $this->assertSame('Von Hand geändert', Page::query()->where('slug', 'start')->value('title'));
        $this->assertSame(1, User::query()->count(), 'kein zweiter Zugang beim erneuten Seeden');

        // Altersangaben als Klassenstufen, Leitungsteams je Stufe – auch beim zweiten Lauf nur einmal.
        $this->assertSame('3. bis 5. Klasse', config('dpsg.stages.woelflinge.age'));
        $this->assertSame([7, 11], [config('dpsg.stages.woelflinge.from'), config('dpsg.stages.woelflinge.to')]);
        $this->assertStringContainsString('dienstags, 17:15–18:45 Uhr', (string) Page::query()->where('slug', 'stufe-jupfi')->value('body'));
        $this->assertSame(['Michele Franke', 'Eva Schmidt', 'Less Knoll'], Person::query()->where('stage', 'jupfi')->orderBy('sort')->pluck('name')->all());
        $this->assertSame(13, Person::query()->where('gremium', 'dak')->count());
        $this->get('/stufe-woelflinge')->assertOk()->assertSee('3. bis 5. Klasse')->assertSee('Hendrik Schuler')->assertSee('Im Leitungsteam');
    }

    #[TestDox('Startinhalt: Seiten, Stufen, Themen und der Stamm als Gruppierung – zweimal ausgeführt ändert nichts')]
    public function test_startinhalt(): void
    {
        $angaben = ['stamm' => 'Don Bosco', 'ort' => 'Beispielstadt', 'nummer' => '210208', 'email' => 'vorstand@stamm-don-bosco.de', 'domain' => 'stamm-don-bosco.de', 'vorstand' => 'Erika Beispiel', 'stufen' => ['woelflinge', 'pfadi'],
            'gruppenstunden' => ['woelflinge' => 'donnerstags, 16:30–18:00 Uhr', 'pfadi' => 'freitags, 18 Uhr'], 'gruppenstunden_orte' => ['pfadi' => 'Pfarrheim St. Martin'],
            'strasse' => 'Kirchplatz 1', 'plz_ort' => '54290 Beispielstadt'];
        config(['dpsg' => array_replace(config('dpsg'), StammVorlage::config($angaben))]);
        Page::query()->delete();
        DB::table('contact_topics')->delete();

        $this->seed(StammStartSeeder::class);
        $this->seed(StammStartSeeder::class);

        $this->assertEqualsCanonicalizing(
            ['start', 'stufen', 'stufe-woelflinge', 'stufe-pfadi', 'stammesvorstand', 'leiter-werden', 'kontakt', 'impressum', 'datenschutz'],
            Page::query()->pluck('slug')->all(),
        );
        $this->assertSame('Pfadfinden in Beispielstadt', Page::query()->where('slug', 'start')->value('title'));
        $this->assertStringContainsString('Erika Beispiel', (string) Page::query()->where('slug', 'impressum')->value('body'));
        $this->assertSame(['allgemein', 'mitmachen', 'woelflinge', 'pfadi', 'website'], DB::table('contact_topics')->orderBy('sort')->pluck('slug')->all());
        $stamm = Gruppierung::query()->where('nummer', '210208')->firstOrFail();
        $this->assertSame('Don Bosco', $stamm->name);
        $this->assertSame(['woelflinge', 'pfadi'], $stamm->stufen());

        // Die Routen der Test-Instanz (DV) haben ein Verzeichnis; der Hinweis
        // „Schnupperstunde anfragen“ statt „Stamm in der Nähe finden“ ist im
        // Browser-Durchlauf geprüft (docs/einrichtung.md).
        $this->get('/stufe-woelflinge')->assertOk()->assertSee('donnerstags, 16:30–18:00 Uhr')->assertSee('Kirchplatz 1, 54290 Beispielstadt');
        $this->get('/stufe-pfadi')->assertOk()->assertSee('freitags, 18 Uhr')->assertSee('Pfarrheim St. Martin')->assertDontSee('Kirchplatz 1, 54290 Beispielstadt')
            ->assertSee('Das Leitungsteam')->assertSee('Leitungsrunde')->assertDontSee('mitzuarbeiten')
            ->assertDontSee('Stufenreferent');
        $this->get('/')->assertOk()->assertSee('Pfadfinden in Beispielstadt');
    }
}
