<?php

use App\Models\Media;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Inhalt Roden (nur KLUFT_INSTANZ=roden, nach dem ersten Deploy): Personen
 * des Stammesvorstands mit Gruppenfoto und vorstand@dpsg-roden.de, Ämter von
 * Hendrik Schuler und Michele Franke, Vorstandsadresse auf der Seite
 * „Stammesvorstand“. Vorhandenes bleibt unberührt; im Backend Geändertes
 * wird nicht überschrieben.
 */
return new class extends Migration
{
    private const VORSTAND = 'vorstand@dpsg-roden.de';

    public function up(): void
    {
        if (config('dpsg.instanz') !== 'roden' || ! DB::table('pages')->where('slug', 'start')->exists()) {
            return;
        }

        $this->vorstand();
        $this->aemter();

        // Seite „Stammesvorstand“: Adresse nur tauschen, solange der Text dem Startinhalt entspricht.
        $alt = 'per E-Mail an <a href="mailto:kontakt@dpsg-roden.de">kontakt@dpsg-roden.de</a>.';
        $page = DB::table('pages')->where('slug', 'stammesvorstand')->first();
        if ($page && str_contains((string) $page->body, $alt)) {
            DB::table('pages')->where('id', $page->id)->update([
                'body' => str_replace($alt, 'per E-Mail an <a href="mailto:'.self::VORSTAND.'">'.self::VORSTAND.'</a>.', (string) $page->body),
                'updated_at' => now(),
            ]);
        }
    }

    private function vorstand(): void
    {
        if (DB::table('people')->where('gremium', 'leitung')->exists()) {
            return;
        }

        $now = now();
        $ids = [];
        foreach (['Kimberly Lamprecht', 'Flavio Russello'] as $i => $name) {
            $ids[$name] = DB::table('people')->insertGetId([
                'gremium' => 'leitung', 'name' => $name, 'role' => 'Stammesvorstand', 'email' => self::VORSTAND,
                'consent' => true, 'active' => true, 'sort' => ($i + 1) * 10, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $source = base_path('instanzen/roden/dateien/stammesvorstand.jpg');
        if (! File::exists($source) || DB::table('team_photos')->where('gremium', 'leitung')->exists()) {
            return;
        }
        File::ensureDirectoryExists(public_path('uploads/media'));
        File::copy($source, public_path('uploads/media/stammesvorstand-roden.jpg'));

        $media = Media::query()->create([
            'path' => 'media/stammesvorstand-roden.jpg',
            'mime' => 'image/jpeg',
            'size' => File::size($source),
            'alt_text' => 'Kimberly Lamprecht und Flavio Russello in Kluft und Halstuch vor den Fahnenmasten und der großen Holzhand mit Pfadfindergruß',
            'credit' => 'Julia Hafner',
            'rights_confirmed' => true,
        ]);

        $photoId = DB::table('team_photos')->insertGetId([
            'gremium' => 'leitung', 'media_id' => $media->id, 'title' => 'Unser Stammesvorstand',
            'note' => null, 'active' => true, 'created_at' => $now, 'updated_at' => $now,
        ]);

        // Gesichtsmitte in Prozent, von links nach rechts.
        foreach (['Kimberly Lamprecht' => [27.2, 36.7], 'Flavio Russello' => [53.3, 34.4]] as $name => [$x, $y]) {
            DB::table('team_photo_spots')->insert(['team_photo_id' => $photoId, 'person_id' => $ids[$name], 'x' => $x, 'y' => $y]);
        }
    }

    private function aemter(): void
    {
        foreach (['Hendrik Schuler' => 'Kassenwart und Mitgliederbeauftragter', 'Michele Franke' => 'Rüsthaus-Bestellungen'] as $name => $amt) {
            DB::table('people')->where('gremium', 'dak')->where('name', $name)
                ->where(fn ($q) => $q->whereNull('role')->orWhere('role', ''))
                ->update(['role' => $amt, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Inhalte bleiben stehen; im Backend änderbar.
    }
};
