<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Beitrag ↔ Veranstaltung: verknüpfte Beiträge zeigen einen Anmelde-Kasten.
 * Die Einladung zur Diözesanversammlung wird gleich verknüpft; der
 * schlichte Anmeldelink im Text entfällt dann (der Kasten ersetzt ihn).
 */
return new class extends Migration
{
    private const DIVERS_LINK = '<p><a href="/veranstaltungen/dioezesanversammlung-2026"><strong>Hier geht’s zur Anmeldung</strong></a> – Anmeldeschluss ist der 8. November 2026.</p>';

    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('cover_media_id')->constrained('events')->nullOnDelete();
        });

        $event = DB::table('events')->where('slug', 'dioezesanversammlung-2026')->value('id');
        $post = DB::table('posts')->where('slug', 'einladung-zur-91-dioezesanversammlung')->first(['id', 'body', 'event_id']);
        if ($event && $post && ! $post->event_id) {
            DB::table('posts')->where('id', $post->id)->update([
                'event_id' => $event,
                'body' => preg_replace('#\s*'.preg_quote(self::DIVERS_LINK, '#').'#u', '', $post->body),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
        });
    }
};
