<?php

namespace Tests\Feature\Content;

use App\Filament\Resources\Downloads\Pages\CreateDownload;
use App\Models\Download;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Instanzen\DvTrier\Seeders\Inhalt\DownloadsAbrechnungsformulare;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;
use ZipArchive;

/**
 * Download-Seite (Methoden, Formulare, Arbeitshilfen). Beim DV unter
 * „Diözesanverband“ mit den Abrechnungsformularen.
 */
#[Group('dv-trier')]
class DownloadsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(Download::DISK);
    }

    private function datei(string $pfad, string $inhalt): string
    {
        Storage::disk(Download::DISK)->put($pfad, $inhalt);

        return $pfad;
    }

    private function pdf(int $seiten): string
    {
        $objekte = '';
        for ($i = 0; $i < $seiten; $i++) {
            $objekte .= ($i + 3)." 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n";
        }

        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [] /Count {$seiten} >>\nendobj\n{$objekte}%%EOF";
    }

    private function download(array $attrs = []): Download
    {
        return Download::query()->create([
            'title' => 'Kennenlernspiele', 'description' => '25 Spiele ohne Material', 'category' => 'methoden',
            'stage' => 'woelflinge', 'file_path' => $this->datei('downloads/spiele.pdf', $this->pdf(12)),
            'file_name' => 'Kennenlernspiele für die Gruppenstunde.pdf', 'rights_confirmed' => true, 'published' => true,
            ...$attrs,
        ]);
    }

    #[TestDox('Typ, Größe, Seiten und Stand kommen aus der Datei')]
    public function test_file_info(): void
    {
        $pdf = $this->download();
        $this->assertSame(['pdf', 'PDF', 12], [$pdf->extension(), $pdf->badge(), $pdf->parts]);
        $this->assertMatchesRegularExpression('#^12 Seiten · \d+ Byte · Stand \d\d/\d{4}$#', $pdf->meta());
        $this->assertTrue($pdf->isNew());
        $this->assertSame('kennenlernspiele-fur-die-gruppenstunde.pdf', $pdf->downloadName());

        $zipPfad = Storage::disk(Download::DISK)->path('downloads/koffer.zip');
        @mkdir(dirname($zipPfad), 0777, true);
        $zip = new ZipArchive;
        $zip->open($zipPfad, ZipArchive::CREATE);
        $zip->addFromString('anleitung.txt', 'a');
        $zip->addFromString('karten/karte1.txt', 'b');
        $zip->addFromString('__MACOSX/._karte1.txt', 'x');
        $zip->close();
        $koffer = $this->download(['title' => 'Methodenkoffer', 'file_path' => 'downloads/koffer.zip', 'file_name' => 'koffer.zip']);
        $this->assertSame(2, $koffer->parts);
        $this->assertStringStartsWith('2 Dateien · ', $koffer->meta());

        $this->assertSame('1,4 MB', Download::groesse(1_468_006));
        $this->assertSame('620 KB', Download::groesse(634_880));
    }

    #[TestDox('Seite zeigt veröffentlichte Dateien nach Kategorie, mit Schlagworten und Infozeile')]
    public function test_index(): void
    {
        $this->download();
        $this->download(['title' => 'Antrag Führungszeugnis', 'category' => 'praevention', 'stage' => null, 'file_path' => $this->datei('downloads/fz.pdf', $this->pdf(1))]);
        $this->download(['title' => 'Entwurf intern', 'published' => false, 'file_path' => $this->datei('downloads/x.pdf', $this->pdf(1))]);
        $this->download(['title' => 'Ohne Rechte', 'rights_confirmed' => false, 'file_path' => $this->datei('downloads/y.pdf', $this->pdf(1))]);

        $this->get('/downloads?kategorie=methoden')->assertOk()
            ->assertSeeInOrder(['Downloads', 'Alle (2)', 'Methoden', 'Kennenlernspiele', 'Wölflinge', '12 Seiten', 'Prävention', 'Antrag Führungszeugnis', '1 Seite'])
            ->assertSee('kat: \'methoden\'', false)
            ->assertDontSee('Entwurf intern')
            ->assertDontSee('Ohne Rechte');
    }

    #[TestDox('Datei wird mit lesbarem Namen ausgeliefert und gezählt; unveröffentlichte nicht')]
    public function test_file_delivery(): void
    {
        $download = $this->download();
        $this->get($download->url())->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename=kennenlernspiele-fur-die-gruppenstunde.pdf');
        $this->assertSame(1, $download->fresh()->downloads);

        $download->update(['published' => false]);
        $this->get($download->url())->assertNotFound();
    }

    #[TestDox('Ersetzte und gelöschte Dateien verschwinden vom Server')]
    public function test_file_cleanup(): void
    {
        $download = $this->download();
        $download->update(['file_path' => $this->datei('downloads/neu.pdf', $this->pdf(3))]);
        Storage::disk(Download::DISK)->assertMissing('downloads/spiele.pdf');
        $this->assertSame(3, $download->fresh()->parts);

        $download->delete();
        Storage::disk(Download::DISK)->assertMissing('downloads/neu.pdf');
    }

    #[TestDox('Backend: Verwaltung legt Downloads an; veröffentlichen erst mit geklärten Rechten')]
    public function test_backend(): void
    {
        $user = User::query()->create(['name' => 'Büro', 'email' => 'buero@dpsg-trier.de', 'status' => 'active']);
        $user->roleAssignments()->create(['role' => 'verwaltung', 'scope_type' => 'global']);
        $this->actingAs($user);

        $formular = fn (bool $rechte) => Livewire::test(CreateDownload::class)->fillForm([
            'file_path' => UploadedFile::fake()->createWithContent('Fahrtkosten.pdf', $this->pdf(1)),
            'title' => 'Fahrtkostenabrechnung', 'category' => 'abrechnung',
            'rights_confirmed' => $rechte, 'published' => true,
        ])->call('create');

        $formular(false)->assertHasFormErrors(['published']);
        $formular(true)->assertHasNoFormErrors();

        $download = Download::query()->sole();
        $this->assertSame(['Fahrtkosten.pdf', 1, 'application/pdf'], [$download->file_name, $download->parts, $download->mime]);
        Storage::disk(Download::DISK)->assertExists($download->file_path);
    }

    #[TestDox('DV: Downloads unter „Diözesanverband“ mit Fahrt-, Reise- und Sachkostenabrechnung; alte Adresse leitet um')]
    public function test_dv_abrechnungsformulare(): void
    {
        $dv = Page::query()->create(['title' => 'Diözesanverband', 'slug' => 'dioezesanverband', 'body' => '<p>x</p>', 'status' => 'published', 'published_at' => now(), 'show_in_nav' => true]);
        $this->assertTrue(DB::table('migrations')->where('migration', '2026_10_06_100100_downloads_abrechnungsformulare')->exists());

        $schritt = new DownloadsAbrechnungsformulare;
        $schritt->auchInTests = true;
        $schritt->run();
        $schritt->run(); // wiederholbar

        $this->assertSame($dv->id, Page::query()->where('slug', 'downloads')->value('parent_id'));
        $this->assertSame(['Fahrtkostenabrechnung (Kilometergeld)', 'Reisekostenabrechnung', 'Sachkostenabrechnung'],
            Download::query()->orderBy('sort')->pluck('title')->all());

        $fahrt = Download::query()->where('sort', 10)->sole();
        $this->assertSame(1, $fahrt->parts);
        $this->assertTrue($fahrt->isNew());
        $this->assertSame([1, 1], Download::query()->whereIn('sort', [20, 30])->pluck('parts')->all());

        $this->get('/downloads')->assertOk()
            ->assertSeeInOrder(['Abrechnungen (Diözesanebene)', 'Fahrtkostenabrechnung', 'prüft die IBAN', 'Reisekostenabrechnung', 'Sachkostenabrechnung']);
        $this->get('/dioezesanverband')->assertOk()->assertSee('Downloads');
        $this->get('/downloads/formulare')->assertRedirect('/downloads');
        $this->get($fahrt->url())->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }
}
