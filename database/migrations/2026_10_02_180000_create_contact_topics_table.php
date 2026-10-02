<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Themen des Kontaktformulars (F-CMS-09). Empfänger: feste Adresse
 * (Funktionsadresse) oder – ohne Adresse – die Stufenreferent*innen bzw.
 * Fachbereichs-Ansprechpersonen, sonst das Diözesanbüro. Nachrichten
 * selbst werden nicht gespeichert, nur per Mail weitergeleitet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_topics', function (Blueprint $table) {
            $table->id();
            $table->string('label', 100);
            $table->string('slug', 60)->unique()->comment('Für Links wie /kontakt?thema=woelflinge');
            $table->string('email', 254)->nullable()->comment('Feste Empfängeradresse; leer = Ansprechpersonen bzw. Diözesanbüro');
            $table->string('stage', 20)->nullable();
            $table->string('section', 60)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        if (app()->runningUnitTests()) {
            return;
        }

        $topics = [['label' => 'Allgemeine Frage an das Diözesanbüro', 'slug' => 'allgemein']];
        $topics[] = ['label' => 'Mitmachen: Stamm in meiner Nähe', 'slug' => 'mitmachen'];
        foreach (config('dpsg.stages') as $key => $stage) {
            $topics[] = ['label' => 'Stufe: '.$stage['label'], 'slug' => $key, 'stage' => $key];
        }
        foreach (config('dpsg.sections') as $key => $section) {
            $topics[] = ['label' => 'Fachbereich: '.$section['label'], 'slug' => $key, 'section' => $key];
        }
        $topics[] = ['label' => 'Georgsbrief und Georgsbote', 'slug' => 'georgsbrief'];
        $topics[] = ['label' => 'Website (Fehler, Hinweise)', 'slug' => 'website'];

        foreach ($topics as $i => $topic) {
            DB::table('contact_topics')->insert([
                'stage' => null, 'section' => null, 'email' => null, ...$topic,
                'sort' => ($i + 1) * 10, 'active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Platzhalter auf der Kontaktseite durch das Formular ersetzen.
        $page = DB::table('pages')->where('slug', 'kontakt')->first(['id', 'body']);
        if ($page && str_contains($page->body, 'Kontaktformular folgt')) {
            DB::table('pages')->where('id', $page->id)->update([
                'body' => preg_replace('#\s*<p>\[TODO: Kontaktformular folgt[^<]*</p>#u', '', $page->body),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_topics');
    }
};
