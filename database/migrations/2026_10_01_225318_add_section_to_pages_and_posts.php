<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sektionszuordnung (z. B. "bolivien") für Seiten und Beiträge, s.
 * Pflichtenheft 6.2/8.2. Als Schlüssel statt section_id, weil Sektionen in
 * config/dpsg.php definiert sind und derselbe Schlüssel im Rollen-Scope
 * (role_assignments.scope_key) steht.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('section', 30)->nullable()->after('stage')->index();
        });
        Schema::table('posts', function (Blueprint $table) {
            $table->string('section', 30)->nullable()->after('scope')->index();
        });

        DB::table('pages')->where('slug', 'bolivien')->update(['section' => 'bolivien']);
    }

    public function down(): void
    {
        foreach (['pages', 'posts'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['section']);
                $table->dropColumn('section');
            });
        }
    }
};
