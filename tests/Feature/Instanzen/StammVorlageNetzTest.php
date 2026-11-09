<?php

namespace Tests\Feature\Instanzen;

use App\Support\Einrichtung\StammVorlage;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * „DPSG im Netz“ eines Stamms: nächsthöhere Ebene als parent, mit Bezirk
 * zusätzlich der Diözesanverband.
 */
class StammVorlageNetzTest extends TestCase
{
    private function site(array $angaben): array
    {
        return StammVorlage::config(['stamm' => 'Don Bosco', 'ort' => 'Beispielstadt', 'email' => 'vorstand@example.org', 'domain' => 'example.org', ...$angaben])['site'];
    }

    #[TestDox('Mit Bezirk: Bezirk als übergeordnete Ebene, Diözesanverband zusätzlich; ohne Bezirk nur der Diözesanverband')]
    public function test_netz(): void
    {
        $mitBezirk = $this->site(['bezirk' => 'Saar-Hochwald', 'bezirk_url' => 'https://www.dpsg-saar-hochwald.de', 'dioezese' => 'Trier']);
        $this->assertSame('DPSG Bezirk Saar-Hochwald', $mitBezirk['parent']['label']);
        $this->assertSame([['label' => 'DPSG Diözesanverband Trier', 'url' => 'https://www.dpsg-trier.de']], $mitBezirk['links']);

        $ohneBezirk = $this->site(['dioezese' => 'Trier']);
        $this->assertSame('DPSG Diözesanverband Trier', $ohneBezirk['parent']['label']);
        $this->assertSame([], $ohneBezirk['links']);

        $andereDioezese = $this->site(['bezirk' => 'Nord', 'bezirk_url' => 'https://example.org/nord', 'dioezese' => 'Köln', 'dioezese_url' => 'https://example.org/koeln']);
        $this->assertSame([['label' => 'DPSG Diözesanverband Köln', 'url' => 'https://example.org/koeln']], $andereDioezese['links']);
    }
}
