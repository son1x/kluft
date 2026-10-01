<?php

return [

    /*
    | Stufen in pädagogischer Reihenfolge. Farbklassen stehen bewusst NICHT
    | hier, sondern als Literale in den Blade-Views, weil Tailwind nur
    | resources/ nach Klassennamen durchsucht.
    */
    'stages' => [
        'biber' => ['label' => 'Biber', 'age' => '4 bis 7 Jahre', 'mascot' => 'images/stufen/biber.png'],
        'woelflinge' => ['label' => 'Wölflinge', 'age' => '6 bis 10 Jahre', 'mascot' => 'images/stufen/woelflinge.png'],
        'jupfi' => ['label' => 'Jungpfadfinder*innen', 'age' => '10 bis 13 Jahre', 'mascot' => 'images/stufen/jupfi.png'],
        'pfadi' => ['label' => 'Pfadfinder*innen', 'age' => '13 bis 15 Jahre', 'mascot' => 'images/stufen/pfadi.png'],
        'rover' => ['label' => 'Rover', 'age' => '15 bis 20 Jahre', 'mascot' => 'images/stufen/rover.svg'],
    ],

    'contact' => [
        'name' => 'DPSG Diözesanverband Trier',
        'office' => 'Diözesanbüro',
        'street' => 'Weberbach 71',
        'city' => '54290 Trier',
        'phone' => '0651 4 629 829 0',
        'phone_href' => '+4965146298290',
        'email' => 'info@dpsg-trier.de',
    ],

    /*
    | Selbstgehostete Karte (F-MAP-04). Bei neuem Build-Datum ändert sich
    | der Dateiname, sodass der Deploy die neue Datei hochlädt.
    */
    'map' => [
        'build' => '20260930',
        'pmtiles' => 'maps/data/trier-20260930-z13.pmtiles',
        // West, Süd, Ost, Nord – Ausdehnung des Bistums Trier mit Rand
        'bbox' => [6.0, 49.05, 8.15, 50.85],
        'center' => [7.05, 49.85],
    ],

];
