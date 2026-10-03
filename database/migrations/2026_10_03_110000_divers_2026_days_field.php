<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Diözesanversammlung 2026: Anwesenheit nach Tagen abfragen (neues
 * Baukasten-Feld „tage“), damit die Teilnehmerliste zeigt, wer wann da ist.
 */
return new class extends Migration
{
    public function up(): void
    {
        $event = DB::table('events')->where('slug', 'dioezesanversammlung-2026')->first(['id', 'form_fields']);
        if (! $event) {
            return;
        }

        $fields = json_decode((string) $event->form_fields, true) ?: [];
        if (isset($fields['tage'])) {
            return;
        }

        DB::table('events')->where('id', $event->id)->update([
            'form_fields' => json_encode([...$fields, 'tage' => 'required']),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        //
    }
};
