<?php

namespace App\Support;

/**
 * Bilderreihen als Galerie: Stehen in einem Text mindestens drei Bilder
 * direkt hintereinander, jedes allein in seinem Absatz, werden sie
 * nebeneinander und kleiner gezeigt; ein Klick öffnet das Bild in voller
 * Größe. So schieben lange Bilderreihen (z. B. die Cartoons bei
 * BeratungsPower) den Text darunter nicht aus dem Blick. Einzelne Bilder
 * bleiben, wie sie sind.
 */
class Galerie
{
    public const MIN_BILDER = 3;

    public static function render(string $html): string
    {
        $bild = '<p>\s*<img\b[^>]*>\s*</p>';

        return (string) preg_replace_callback(
            '#(?:'.$bild.'\s*){'.self::MIN_BILDER.',}#i',
            function (array $treffer): string {
                preg_match_all('#<img\b[^>]*>#i', $treffer[0], $bilder);
                $eintraege = array_map(function (string $img): string {
                    $src = preg_match('#\ssrc="([^"]+)"#i', $img, $m) ? $m[1] : null;
                    $img = preg_match('#\sloading=#i', $img) ? $img : preg_replace('#^<img\b#i', '<img loading="lazy"', $img);

                    return $src === null
                        ? '<li>'.$img.'</li>'
                        : '<li><a href="'.$src.'" title="Bild in voller Größe öffnen">'.$img.'</a></li>';
                }, $bilder[0]);

                return '<ul class="galerie not-prose">'.implode('', $eintraege).'</ul>'."\n";
            },
            $html,
        );
    }
}
