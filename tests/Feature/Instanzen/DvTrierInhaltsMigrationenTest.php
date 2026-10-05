<?php

namespace Tests\Feature\Instanzen;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * DV Trier: Inhalts-Migrationen unter instanzen/dv-trier/migrations.
 */
#[Group('dv-trier')]
class DvTrierInhaltsMigrationenTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Cartoon 1: „BP“ statt „PB“ in Alternativtext und Seite, Bild neu geladen')]
    public function test_cartoon_bp_statt_pb(): void
    {
        $alt = 'Cartoon: Pfadfinder mit Hut – PB BeratungsPower &amp; Coaching';
        $pageId = DB::table('pages')->insertGetId([
            'title' => 'Stöbern & Schmökern', 'slug' => 'stoebern-test', 'status' => 'published',
            'body' => '<p><img src="/uploads/media/2026/beraten-online-cartoon-1.jpg" alt="'.$alt.'"></p>',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('media')->insert([
            'path' => 'media/2026/beraten-online-cartoon-1.jpg', 'mime' => 'image/jpeg', 'size' => 1,
            'alt_text' => 'Cartoon – PB BeratungsPower & Coaching', 'credit' => 'Test', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration = require base_path('instanzen/dv-trier/migrations/2026_10_06_090000_beraten_cartoon_bp_statt_pb.php');
        $migration->up();
        $migration->up();

        $body = (string) DB::table('pages')->where('id', $pageId)->value('body');
        $this->assertStringContainsString('cartoon-1.jpg?v=2" alt="Cartoon: Pfadfinder mit Hut – BP BeratungsPower', $body);
        $this->assertStringNotContainsString('?v=2?v=2', $body);
        $this->assertSame('Cartoon – BP BeratungsPower & Coaching', DB::table('media')->value('alt_text'));
    }
}
