<?php

namespace Tests\Feature\Umfragen;

use App\Models\Umfrage;
use App\Services\Umfragen\Auswertung;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Terminfindung als Fragetyp in Umfragen (docs/plan-ehrenamt.md Nr. 4).
 */
class TerminfindungTest extends TestCase
{
    use RefreshDatabase;

    #[TestDox('Je Vorschlag ja / wenn nötig / nein; ungültige Werte fallen weg; Auswertung mit Summen und bestem Termin; Text für Export')]
    public function test_terminfindung(): void
    {
        Features::setzen(Features::UMFRAGEN, true);
        $umfrage = Umfrage::query()->create(['titel' => 'Leiterrunde Dezember', 'status' => 'offen', 'anonym' => true, 'mehrfach' => true]);
        $umfrage->fragen()->create(['typ' => 'kurztext', 'text' => 'Name', 'sort' => 1]);
        $frage = $umfrage->fragen()->create(['typ' => 'termin', 'text' => 'Wann kannst du?', 'pflicht' => true, 'sort' => 2,
            'optionen' => ['Di, 2.12., 19 Uhr', 'Do, 4.12., 19 Uhr', 'Mo, 8.12., 20 Uhr']]);
        $k = $frage->schluessel;

        $this->get($umfrage->url())->assertOk()
            ->assertSeeInOrder(['Di, 2.12., 19 Uhr', 'Ja', 'Wenn nötig', 'Nein', 'Do, 4.12., 19 Uhr'])
            ->assertSee('name="a['.$k.'][0]"', false)->assertSee('role="radiogroup"', false);

        $senden = fn (array $a) => $this->from($umfrage->url())->post(route('umfragen.absenden', $umfrage->code),
            ['a' => [$k => $a], '_t' => Crypt::encryptString((string) now()->subSeconds(20)->timestamp)]);

        $senden([])->assertSessionHasErrors('a.'.$k);
        $senden(['ja', 'nein', 'vielleicht'])->assertSessionHasNoErrors();
        $senden(['ja', 'ja', 'quatsch'])->assertSessionHasNoErrors();
        $senden(['vielleicht', 'ja', 'nein'])->assertSessionHasNoErrors();

        $antwort = $umfrage->antworten()->orderBy('id')->get()[1];
        $this->assertSame(['Di, 2.12., 19 Uhr' => 'ja', 'Do, 4.12., 19 Uhr' => 'ja'], $antwort->daten[$k]['wert']);
        $this->assertSame('Di, 2.12., 19 Uhr: Ja; Do, 4.12., 19 Uhr: Ja', Auswertung::wertText($antwort->daten[$k]['wert']));

        $ergebnis = collect(app(Auswertung::class)->zusammenfassung($umfrage)['fragen'])->firstWhere('frage.id', $frage->id);
        $this->assertSame([
            ['Di, 2.12., 19 Uhr', 2, 1, 0, 5, true],
            ['Do, 4.12., 19 Uhr', 2, 0, 1, 4, false],
            ['Mo, 8.12., 20 Uhr', 0, 1, 1, 1, false],
        ], array_map(fn ($t) => [$t['vorschlag'], $t['ja'], $t['vielleicht'], $t['nein'], $t['punkte'], $t['bester']], $ergebnis['termine']));

        $html = view('umfragen.partials.frage-ergebnis', ['e' => $ergebnis])->render();
        $this->assertStringContainsString('bester Termin', $html);
    }
}
