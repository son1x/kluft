<?php

/*
| Chronik der Diözesanleitung (Seite „chronik“, partials/chronik.blade.php).
| Quelle: der erste statische Entwurf der Website (soweit sich die Liste
| rekonstruieren ließ). Hinweise und Ergänzungen: Diözesanbüro.
|
| amt:     vorsitz | kurat | stellv (Stellvertretung, ohne Zeitraum)
| titel:   Amtsbezeichnung wie in der Quelle
| von/bis: Jahre; ohne „von“ = Zeitraum unbekannt; „bis“ leer = bis heute oder offen
| text:    optionaler Anzeigetext statt der Jahre („ab 1950“)
| aktuell: im Amt (Markierung „aktuell“)
*/

return [
    'eintraege' => [
        // Vorsitz
        ['name' => 'Katrin Rinke', 'amt' => 'vorsitz', 'titel' => 'Vorsitzende', 'von' => 2016, 'bis' => 2022],
        ['name' => 'Sebastian Kraus', 'amt' => 'vorsitz', 'titel' => 'Vorsitzender', 'von' => 2017, 'bis' => 2020],
        ['name' => 'Andreas Schmidt', 'amt' => 'vorsitz', 'titel' => 'Vorsitzender', 'von' => 2008, 'bis' => 2011],
        ['name' => 'Nicola Sacca', 'amt' => 'vorsitz', 'titel' => 'Vorsitzender', 'von' => 2000, 'bis' => 2004],
        ['name' => 'Iris Köhlbach', 'amt' => 'vorsitz', 'titel' => 'Vorsitzende', 'von' => 1992, 'bis' => 1998],
        ['name' => 'Markus Krogull Kalb', 'amt' => 'vorsitz', 'titel' => 'Vorsitzender', 'von' => 1994, 'bis' => 2000],
        ['name' => 'Lory Müller', 'amt' => 'vorsitz', 'titel' => 'Vorsitzender', 'von' => 1989, 'bis' => 1994],
        ['name' => 'Albert Ottenbreit', 'amt' => 'vorsitz', 'titel' => 'Vorsitzender', 'von' => 1982, 'bis' => 1988],
        ['name' => 'Herbert Pott', 'amt' => 'vorsitz', 'titel' => 'Vorsitzender', 'von' => 1950, 'bis' => null, 'text' => 'ab 1950'],
        ['name' => 'Hans Brog', 'amt' => 'vorsitz', 'titel' => 'Vorsitzender', 'von' => 1947, 'bis' => 1950],
        ['name' => 'Dieter Berger', 'amt' => 'vorsitz', 'titel' => 'Vorsitzender', 'von' => null, 'bis' => null],
        ['name' => 'Prof. Dr. Wilfried Loth', 'amt' => 'vorsitz', 'titel' => 'Vorsitzender', 'von' => null, 'bis' => null],
        ['name' => 'Hans Joachim Fries', 'amt' => 'vorsitz', 'titel' => 'Vorsitzender', 'von' => null, 'bis' => null],

        // Kuratinnen und Kuraten
        ['name' => 'Peter Zillgen', 'amt' => 'kurat', 'titel' => 'Kurat', 'von' => 2016, 'bis' => null, 'aktuell' => true],
        ['name' => 'Martin Laskewicz', 'amt' => 'kurat', 'titel' => 'Kurat', 'von' => 2010, 'bis' => 2015],
        ['name' => 'Eric Condé', 'amt' => 'kurat', 'titel' => 'Kurat', 'von' => 2002, 'bis' => 2010],
        ['name' => 'Ralf Pius Krämer', 'amt' => 'kurat', 'titel' => 'Kurat', 'von' => 1996, 'bis' => 2002],
        ['name' => 'Ralf Schmitz', 'amt' => 'kurat', 'titel' => 'Kurat', 'von' => 1992, 'bis' => 1996],
        ['name' => 'Edgar Theissen', 'amt' => 'kurat', 'titel' => 'Kurat', 'von' => 1989, 'bis' => 1992],
        ['name' => 'Johannes Stein', 'amt' => 'kurat', 'titel' => 'Kurat', 'von' => 1983, 'bis' => 1989],
        ['name' => 'Hermann Engel', 'amt' => 'kurat', 'titel' => 'Kurat', 'von' => 1977, 'bis' => 1983],
        ['name' => 'Alo Welter', 'amt' => 'kurat', 'titel' => 'Kurat', 'von' => 1961, 'bis' => 1977],
        ['name' => 'Rudi Oster', 'amt' => 'kurat', 'titel' => 'Kurat', 'von' => null, 'bis' => null],

        // Stellvertretender Vorstand (Zeiträume nicht bekannt)
        ['name' => 'Ulrike Krupp', 'amt' => 'stellv', 'titel' => 'Stellvertretender Vorstand', 'von' => null, 'bis' => null],
        ['name' => 'Inge Wilhelm', 'amt' => 'stellv', 'titel' => 'Stellvertretender Vorstand', 'von' => null, 'bis' => null],
        ['name' => 'Norbert Martini', 'amt' => 'stellv', 'titel' => 'Stellvertretender Vorstand', 'von' => null, 'bis' => null],
        ['name' => 'Bernd Molzberger', 'amt' => 'stellv', 'titel' => 'Stellvertretender Vorstand', 'von' => null, 'bis' => null],
    ],
];
