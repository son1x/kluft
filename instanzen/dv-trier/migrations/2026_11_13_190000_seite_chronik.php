<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/*
| Seite „Chronik“ unter „Diözesanverband“: Zeitstrahl der Vorstände und
| Kuraten (partials/chronik.blade.php, Daten in instanzen/dv-trier/chronik.php).
| Legt die Seite nur an, wenn es sie noch nicht gibt; Text und Menü lassen sich
| danach im Backend ändern.
*/
return new class extends Migration
{
    public const TEXT = '<p>Seit 1947 führen ehrenamtliche Vorstände den Diözesanverband Trier, begleitet von Kuratinnen und Kuraten. Hier steht, wer wann dabei war – soweit sich das rekonstruieren ließ.</p>';

    /** In Tests nur auf ausdrücklichen Wunsch (andere Tests zählen Seiten). */
    public bool $auchInTests = false;

    public function up(): void
    {
        if (app()->runningUnitTests() && ! $this->auchInTests) {
            return;
        }
        if (Page::query()->where('slug', 'chronik')->exists()) {
            return;
        }
        $eltern = Page::query()->where('slug', 'dioezesanverband')->first();
        Page::query()->create([
            'slug' => 'chronik',
            'title' => 'Chronik der Diözesanleitung',
            'parent_id' => $eltern?->getKey(),
            'body' => self::TEXT,
            'seo_description' => 'Wer den Diözesanverband Trier seit 1947 geführt hat: Vorstände und Kuraten im Zeitstrahl',
            'status' => 'published',
            'published_at' => now(),
            'show_in_nav' => (bool) $eltern,
            'nav_sort' => 25,
        ]);
    }

    public function down(): void
    {
        // Inhalt bleibt.
    }
};
