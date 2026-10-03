<?php

namespace Tests\Feature\Content;

use App\Models\Media;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * WebP-Varianten der Mediathek, Pflichtenheft F-CMS-04 und 10.3.
 */
class ImageVariantsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function upload(int $width, int $height, string $mime = 'image/jpeg'): Media
    {
        $img = imagecreatetruecolor($width, $height);
        ob_start();
        imagejpeg($img);
        Storage::disk('public')->put('media/foto.jpg', ob_get_clean());

        return Media::query()->create(['path' => 'media/foto.jpg', 'mime' => $mime, 'size' => 1, 'alt_text' => 'Lager', 'credit' => 'DPSG']);
    }

    #[TestDox('Bild-Upload erzeugt WebP-Varianten, ohne kleine Bilder hochzuskalieren')]
    public function test_upload_creates_webp_variants_without_upscaling(): void
    {
        $media = $this->upload(1200, 800)->fresh();

        $this->assertSame([480, 960, 1200], array_map('intval', array_keys($media->variants)));
        $this->assertSame([1200, 800], [$media->width, $media->height]);
        foreach ($media->variants as $width => $path) {
            [$w, , $type] = getimagesizefromstring(Storage::disk('public')->get($path));
            $this->assertSame(IMAGETYPE_WEBP, $type);
            $this->assertSame((int) $width, $w);
        }

        $small = $this->upload(300, 200)->fresh();
        $this->assertSame([300], array_map('intval', array_keys($small->variants)));
    }

    #[TestDox('Varianten werden mit dem Bild gelöscht; PDFs bleiben unberührt')]
    public function test_variants_are_removed_with_the_image_and_pdfs_are_skipped(): void
    {
        $media = $this->upload(1000, 600)->fresh();
        $paths = array_values($media->variants);
        $media->delete();
        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }

        Storage::disk('public')->put('media/doc.pdf', '%PDF-1.4');
        $pdf = Media::query()->create(['path' => 'media/doc.pdf', 'mime' => 'application/pdf', 'size' => 1, 'alt_text' => 'x', 'credit' => 'x']);
        $this->assertNull($pdf->fresh()->variants);
    }

    #[TestDox('Website liefert WebP in passenden Größen mit Original als Rückfall')]
    public function test_frontend_serves_webp_srcset_with_original_fallback(): void
    {
        $media = $this->upload(1600, 900);
        Post::query()->create(['title' => 'Lager', 'slug' => 'lager', 'teaser' => 't', 'body' => '<p>b</p>', 'status' => 'published', 'published_at' => now()->subHour(), 'cover_media_id' => $media->id]);

        $this->get('/aktuelles/lager')->assertOk()
            ->assertSee('<source type="image/webp"', false)
            ->assertSee('-480.webp 480w', false)
            ->assertSee('-1600.webp 1600w', false)
            ->assertSee('media/foto.jpg" alt="Lager"', false)
            ->assertSee('width="1600" height="900"', false);
    }

    #[TestDox('Ausgetauschtes Bild bekommt neue Varianten unter neuen Dateinamen; die alten verschwinden')]
    public function test_replacing_the_image_renames_variants(): void
    {
        $media = $this->upload(800, 600)->fresh();
        $old = array_values($media->variants);

        $img = imagecreatetruecolor(1000, 500);
        ob_start();
        imagejpeg($img);
        Storage::disk('public')->put('media/neu.jpg', ob_get_clean());
        $media->update(['path' => 'media/neu.jpg']);

        $new = $media->fresh();
        $this->assertSame([480, 960, 1000], array_map('intval', array_keys($new->variants)));
        $this->assertSame([1000, 500], [$new->width, $new->height]);
        $this->assertEmpty(array_intersect($old, $new->variants));
        foreach ($old as $path) {
            Storage::disk('public')->assertMissing($path);
        }
        foreach ($new->variants as $path) {
            Storage::disk('public')->assertExists($path);
        }
    }

    #[TestDox('Befehl erzeugt Varianten für bereits vorhandene Bilder')]
    public function test_command_backfills_existing_images(): void
    {
        $media = $this->upload(800, 600);
        $media->forceFill(['variants' => null])->saveQuietly();

        $this->artisan('media:variants')->assertSuccessful();
        $this->assertNotEmpty($media->fresh()->variants);
    }
}
