<?php

namespace Tests\Feature\Content;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Chronik der Diözesanleitung (DV Trier): Seite, Zeitstrahl nach Jahrzehnten, Kuraten und Vorsitz.
 */
#[Group('dv-trier')]
class ChronikTest extends TestCase
{
    use RefreshDatabase;

    private function seite(): Page
    {
        $migration = require base_path('instanzen/dv-trier/migrations/2026_11_13_190000_seite_chronik.php');
        $migration->auchInTests = true;
        Page::query()->create(['slug' => 'dioezesanverband', 'title' => 'Diözesanverband', 'body' => '<p>DV</p>', 'status' => 'published', 'published_at' => now(), 'show_in_nav' => true]);
        $migration->up();

        return Page::query()->where('slug', 'chronik')->firstOrFail();
    }

    #[TestDox('Migration legt die Seite unter „Diözesanverband“ an, nur einmal, und lässt eine bearbeitete Seite in Ruhe')]
    public function test_migration(): void
    {
        $seite = $this->seite();
        $this->assertSame(Page::query()->where('slug', 'dioezesanverband')->value('id'), $seite->parent_id);
        $this->assertSame('published', $seite->status);
        $this->assertTrue($seite->show_in_nav);

        $seite->update(['title' => 'Unsere Geschichte']);
        $migration = require base_path('instanzen/dv-trier/migrations/2026_11_13_190000_seite_chronik.php');
        $migration->auchInTests = true;
        $migration->up();
        $this->assertSame(1, Page::query()->where('slug', 'chronik')->count());
        $this->assertSame('Unsere Geschichte', $seite->fresh()->title);
    }

    #[TestDox('Seite zeigt den Zeitstrahl: Jahrzehnte, Vorsitz und Kurat*innen, Filter, Aktuell-Markierung, unbekannte Zeiträume')]
    public function test_zeitstrahl(): void
    {
        $this->seite();
        $eintraege = collect(config('dpsg.chronik.eintraege'));
        $this->assertGreaterThan(20, $eintraege->count());

        $html = $this->get('/chronik')->assertOk()->getContent();
        foreach (['2010er', '1990er', '1940er'] as $jahrzehnt) {
            $this->assertStringContainsString($jahrzehnt, $html);
        }
        foreach ($eintraege->whereNotNull('von') as $e) {
            $this->assertStringContainsString(e($e['name']), $html);
        }
        $this->assertStringContainsString('Kuratinnen und Kuraten', $html);
        $this->assertStringContainsString('Zeitraum nicht bekannt', $html);
        $this->assertStringContainsString('Stellvertretender Vorstand', $html);
        $this->assertSame(1, substr_count($html, '>aktuell<'));
        $this->assertStringContainsString('seit 2016', $html);
        $this->assertStringContainsString('ab 1950', $html);
        $this->assertStringContainsString('href="'.url('/dioezesanleitung').'"', $html);
    }

    #[TestDox('Ohne Chronik-Daten bleibt der Zeitstrahl leer (andere Instanzen), die Seite zeigt nur den Text')]
    public function test_ohne_daten(): void
    {
        $this->seite();
        config(['dpsg.chronik' => []]);

        $html = $this->get('/chronik')->assertOk()->assertSee('Seit 1947')->getContent();
        $this->assertStringNotContainsString('Zeitstrahl der Diözesanleitung', $html);
    }

    #[TestDox('Daten: Amt gültig, Jahre sinnvoll, keine doppelten Namen mit gleichem Amt und Beginn')]
    public function test_daten(): void
    {
        $eintraege = collect(config('dpsg.chronik.eintraege'));
        foreach ($eintraege as $e) {
            $this->assertContains($e['amt'], ['vorsitz', 'kurat', 'stellv']);
            $this->assertNotSame('', trim($e['name']));
            if ($e['von'] && $e['bis']) {
                $this->assertLessThanOrEqual($e['bis'], $e['von'], $e['name']);
            }
        }
        $this->assertSame($eintraege->count(), $eintraege->unique(fn ($e) => $e['name'].'|'.$e['amt'])->count());
    }
}
