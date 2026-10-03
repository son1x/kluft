<?php

use App\Models\Redirect;
use Database\Seeders\DemoContentSeeder;
use Database\Seeders\RequiredPagesSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Rechtsträgerstruktur unter „Diözesanverband“: Seite „Rechtsträger“
 * (Landesamt St. Georg e. V., dpsg trevirensis gGmbH) mit den Unterseiten
 * Stiftung und Freunde & Förderer; 301 von den alten WordPress-Adressen.
 * Impressum: inhaltlich verantwortlich ist Marc Basenach. Ändert nur
 * Inhalte im Ausgangszustand; frische Installationen bekommen dieselbe
 * Struktur über die Seeder.
 */
return new class extends Migration
{
    private const OLD_ROLE = 'Geschäftsführender Bildungsreferent';

    public function up(): void
    {
        $dv = DB::table('pages')->where('slug', 'dioezesanverband')->value('id');
        if ($dv) {
            $parent = $this->ensurePage(['slug' => 'rechtstraeger', 'title' => 'Rechtsträger', 'parent_id' => $dv, 'nav_sort' => 50,
                'seo_description' => 'Landesamt St. Georg e. V., dpsg trevirensis gGmbH, Stiftung und Förderverein', 'body' => DemoContentSeeder::RECHTSTRAEGER_BODY]);
            $this->ensurePage(['slug' => 'stiftung', 'title' => 'Stiftung DPSG Diözesanverband Trier', 'parent_id' => $parent, 'nav_sort' => 10,
                'seo_description' => 'Pfadfinderisches Lernen und Leben langfristig stärken', 'body' => DemoContentSeeder::STIFTUNG_BODY]);
            $this->ensurePage(['slug' => 'freunde-und-foerderer', 'title' => 'Freunde & Förderer', 'parent_id' => $parent, 'nav_sort' => 20,
                'seo_description' => 'Der Förderverein für Ehemalige, Mitglieder und Freund*innen der DPSG', 'body' => DemoContentSeeder::FOERDERER_BODY]);

            foreach ([
                '/dioezesanverband/rechtstraeger' => '/rechtstraeger',
                '/dioezesanverband/rechtstraeger/rechtstraegerstruktur' => '/rechtstraeger',
                '/dioezesanverband/rechtstraeger/stiftung' => '/stiftung',
                '/dioezesanverband/rechtstraeger/freunde-und-foerderer' => '/freunde-und-foerderer',
            ] as $from => $to) {
                DB::table('redirects')->insertOrIgnore([
                    'from_path' => $from, 'to_path' => $to, 'status_code' => 301, 'source' => 'manuell',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            // Weiterleitungen sind dauerhaft gecacht; direkte Inserts leeren ihn nicht.
            Cache::forget(Redirect::CACHE_KEY);
        }

        DB::table('people')->where('name', 'Marc Basenach')->where('role', self::OLD_ROLE)
            ->update(['role' => 'Geschäftsführender Bildungsreferent, Geschäftsführer des Rechtsträgers', 'updated_at' => now()]);

        // Impressum nur ersetzen, solange es noch die ursprünglichen Platzhalter enthält.
        DB::table('pages')->where('slug', 'impressum')->where('body', 'like', '%[TODO: Name, Anschrift wie oben]%')
            ->update(['body' => RequiredPagesSeeder::IMPRESSUM_BODY, 'updated_at' => now()]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ensurePage(array $data): int
    {
        $existing = DB::table('pages')->where('slug', $data['slug'])->value('id');
        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table('pages')->insertGetId([
            'status' => 'published', 'published_at' => now(), 'show_in_nav' => true,
            'created_at' => now(), 'updated_at' => now(), ...$data,
        ]);
    }

    public function down(): void
    {
        // Rein inhaltlich; nichts zurückzuholen.
    }
};
