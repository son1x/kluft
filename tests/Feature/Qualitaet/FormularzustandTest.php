<?php

namespace Tests\Feature\Qualitaet;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Schutz vor dem Slug-Fehler (#67): Ein Formularfeld darf per Code keinen
 * Stringable-Wert bekommen (str(…), Str::of(…)). Livewire behält das Objekt im
 * Formularzustand, und Prüfregeln wie alpha_dash, email oder regex lehnen
 * dann auch einen richtig aussehenden Wert ab. Erlaubt ist es, wenn daraus
 * vorher Text wird (->toString(), ->value(), (string)).
 */
class FormularzustandTest extends TestCase
{
    /** Muster, die ein Stringable erzeugen. */
    private const OBJEKT = '/\bstr\(|Str::of\(/';

    /** Muster, die daraus wieder Text machen. */
    private const TEXT = '/->toString\(\)|->value\(\)|\(string\)/';

    #[TestDox('Kein $set(…) und keine Formular-Vorbelegung mit str()/Str::of() ohne Umwandlung in Text')]
    public function test_kein_stringable_im_formularzustand(): void
    {
        $fehler = [];
        foreach ((new Finder)->files()->in(dirname(__DIR__, 3).'/app')->name('*.php') as $datei) {
            $code = $datei->getContents();
            foreach ($this->aufrufe($code, ['$set(', '->fill(', '->default(', '->state(']) as [$zeile, $argumente]) {
                if (preg_match(self::OBJEKT, $argumente) && ! preg_match(self::TEXT, $argumente)) {
                    $fehler[] = $datei->getRelativePathname().':'.$zeile.'  '.trim(preg_replace('/\s+/', ' ', mb_substr($argumente, 0, 120)));
                }
            }
        }

        $this->assertSame([], $fehler, "Formularfeld bekommt ein Stringable statt Text – mit ->toString() oder (string) umwandeln:\n".implode("\n", $fehler));
    }

    #[TestDox('Die Prüfung selbst erkennt das Muster (Selbsttest)')]
    public function test_erkennt_muster(): void
    {
        $code = "<?php\n\$set('slug', str(\$state)->slug());\n\$set('a', str(\$x)->slug()->toString());\n\$set('b', (string) Str::of(\$x));\n";
        $treffer = array_filter($this->aufrufe($code, ['$set(']), fn (array $a) => preg_match(self::OBJEKT, $a[1]) && ! preg_match(self::TEXT, $a[1]));
        $this->assertCount(1, $treffer);
        $this->assertSame(2, array_values($treffer)[0][0]);
    }

    /**
     * Argumente aller Aufrufe der Form „<anfang>…)“ mit Zeilennummer, auch
     * über mehrere Zeilen und mit verschachtelten Klammern.
     *
     * @param  list<string>  $anfaenge
     * @return list<array{0: int, 1: string}>
     */
    private function aufrufe(string $code, array $anfaenge): array
    {
        $gefunden = [];
        foreach ($anfaenge as $anfang) {
            $pos = 0;
            while (($pos = strpos($code, $anfang, $pos)) !== false) {
                $start = $pos + strlen($anfang);
                $tiefe = 1;
                $i = $start;
                $laenge = strlen($code);
                while ($i < $laenge && $tiefe > 0) {
                    $tiefe += match ($code[$i]) {
                        '(' => 1, ')' => -1, default => 0
                    };
                    $i++;
                }
                $gefunden[] = [substr_count($code, "\n", 0, $pos) + 1, substr($code, $start, $i - $start - 1)];
                $pos = $start;
            }
        }

        return $gefunden;
    }
}
