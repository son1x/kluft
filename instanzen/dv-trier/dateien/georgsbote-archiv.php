<?php

/*
| Archivliste Georgsbote und Georgsbrief (Stand 07.11.2026), aus
| Inhaltsverzeichnis, Titelseite und Zwischenüberschriften abgeleitet.
| Kluft übernimmt Titel und Inhalt beim Import und mit „Infos aus der
| Archivliste übernehmen“ (App\Support\GeorgsboteArchiv); im Backend
| Geändertes wird nicht überschrieben. Namen von Interviewten und
| Zeitzeug*innen bewusst weggelassen (erst nach Prüfung im Backend ergänzen).
|
| bote:  [Jahrgang, Ausgabe im Jahrgang, Titel, Inhalt, Heft/Monat laut Vorlage]
| brief: [Jahr, Monat, Inhalt]
*/

return [
    'bote' => [
        [2017, 1, 'Die erste Ausgabe', 'Erste Ausgabe. Editorial, DiVers 2017, Aktuelles, Rundblick, Leuchtfeuer, Vorstellung von Bezirk Saar-Hochwald und AK Ich-Du-Wir, Veranstaltungen, Interview mit dem Diözesanvorstand, Bistumssynode und ihre Umsetzung.', 'Juni 2017, Heft 1'],
        [2017, 2, 'Ihr habt die Wahl', 'Schwerpunkt Wahlen („Ihr habt die Wahl“, #wählenpluseins), Handys recyceln, Maifeld-Scouts, Bezirk Saar-Hochwald, Stamm Perl, Termine, Bezirkslager Rhein-Wied und Rhein-Hunsrück-Nahe, Neues aus dem Diözesanbüro, Bürgerfest, Stammesjubiläen.', 'September 2017, Heft 2'],
        [2017, 3, 'Advent', 'Adventsausgabe: Vorschau DiVers 2018 und Beschlüsse, Interview, Vorstand und Zuständigkeiten, Georgsmedaille, Termine, Friedenslicht.', 'Dezember 2017, Heft 3'],
        [2018, 1, 'Ostern', 'Ostern und Impuls, Interview, Termine, Friedenslicht, Aktionen, BBR 18, Neues aus Trier, i-Scout, Synode, Ich-Du-Wir, Wölflinge.', 'März 2018, Heft 4'],
        [2018, 2, 'Bolivienpartnerschaft', 'BBR 2018, Geschichte der Bolivienpartnerschaft, Chronik, Stamm Andernach, Interviews, Klimapokalypse, Lahn-Party, JOTA-JOTI, DiWöLa.', 'November 2018, Heft 5'],
        [2019, 1, 'Europawahl und Mitbestimmung', 'Titelthema Europawahlen und andere Wege der Mitbestimmung, Scouts for SDGs, Plastikmüll, DIY, Bolivienpartnerschaft, Modulwoche, Freiwilligendienst, Planung DiLa 2020, Logo.', 'Mai 2019, Heft 6'],
        [2019, 2, 'Rückblick Diözesanversammlung', 'Rückblick Diözesanversammlung 2019, Stellungnahme der Diözesanleitung zur politischen Lage, Interviews, Weihnachtskarte, Jahresaktion 2020, Scoutgames 2020, Termine.', 'Dezember 2019, Heft 7'],
        [2020, 1, 'Zeitzeugen', 'Zeitzeugen-Projekt mit Interviews, 25. Jahrestag des Tauziehunglücks, BeratungsPower, Bolivianer in Güls, „Kohle für Klimaschutz“ (5.000 € der Stiftung), Jahresaktion 2020, Scoutgames, WöBiLix, Impuls, #BLM.', 'Juni 2020, Heft 8'],
        [2020, 2, 'Weihnachten', 'Weihnachtsgruß und Spendenaufruf, Kohle für Klimaschutz, Bericht von der Diözesanversammlung 2020, Vorstellung der Diözesanleitung, Interview, Firmung Heilig Rock, Impuls Licht, Stufenseiten, Jahresaktion 2021.', 'Dezember 2020, Heft 9'],
        [2021, 1, 'Ausbildung und Online-Angebote', 'Leitartikel und Impuls, Ausbildung, Bezirk Sieg, Zeitzeugen-Interview, Rover, Geschichte der Wölflinge, Online-Angebote, Heilig-Rock-Jugendtag, „Wir gegen Rassismus“, Solidarität bewegt, Fit fürs Lager, DiLa-Planung.', 'April 2021, Heft 10'],
        [2021, 2, 'Weihnachten', 'Weihnachtskarte, Vorwort und Impuls, Ausbildung, Bericht der Diözesanversammlung, Fachtag Ich-Du-Wir, Zeitzeugen-Interview, Georgsmedaille und -Pin, DiLa 2023, Aufholen nach Corona, Jufi-Aktion, Wöbin Hood, Firmung als Pfadfinder*in, Klimaschutz-Förderung.', 'Dezember 2021, Heft 11'],
        [2022, 1, 'Danke – vonNaturausbunt', 'Weihnachtsausgabe mit Dank an alle Engagierten (Jahresaktion „vonNaturausbunt“), Beschlüsse der Diözesanversammlung, Ausbildung, Internationales, Scoutergy (Diözesanlager Fronleichnam 2023), AG Vielfalt, Friedenslicht, Zeitzeugin, Stellenausschreibung Bildungsreferent*in, Öffentlichkeitsarbeit, Sternsinger, Save the Date 28.04.–01.05.2023.', 'Dezember 2022, Heft 12'],
        [2023, 1, 'Kindergrundsicherung und Fake News', 'Fakten zur Kindergrundsicherung, Konflikte, Hass und Fake News mit Diskussionsimpulsen, Herbstideen (u. a. Winterhaus für Igel), Backaktion, Ausbildung, Friedenslicht. Das Vorwort kündigt eine neue Bildungsreferentin ab 01.01.2024 an.', 'November 2023, Heft 13'],
        [2023, 2, 'Auf der Suche nach Frieden', 'Weihnachtsgruß zum Friedenslicht-Motto „Auf der Suche nach Frieden“, Corona-Förderungen, Bericht Diözesanversammlung, Einsatz gegen Rassismus, Weihnachtsbericht, Ausbildung, Interview, Jahresaktion 2024, Zeitzeug*innen, Stiftung, Sternsinger.', 'Dezember 2023, im Heft „Ausgabe 12“'],
        [2024, 1, 'Europa', 'Schwerpunkt Europa: Leitartikel „Europa stellt sich neu auf“, Infoboxen zu Europa- und Kommunalwahlen und zur EU, politisch aktiv ab U18, Ideen für die Europawahl, Gruppenstunden für Wölflinge/Jufis und Pfadis/Rover, Kurat*innen 1x1, Steckbrief.', 'Mai 2024, Heft 13 laut Heft'],
        [2024, 2, 'Weihnachten', 'Weihnachtsgruß der Diözesanleitung, Bolivien, Interview, Buchempfehlung, Bericht der Diözesanversammlung, Weihnachtsbericht der Diözesanvorsitzenden, Anne-Frank-Generation, Ausbildung, Stiftung, Sternsinger.', 'Dezember 2024, Heft 14'],
        [2025, 1, 'Wo ist hier der Weg zum Glück?', 'Vorwort und Impuls des Diözesankurats („Wo ist hier der Weg zum Glück?“), Ausbildungsangebote (Jufi-WBK, Juleica meets Scouting), 93. Bundesversammlung und Stimmendelegation bei vakanten Vorstandsämtern, Interview mit einem ehemaligen Diözesanvorsitzenden, Leitartikel Teilhabe/Bildung/Armutsbekämpfung, Vorsitzende*r gesucht, Infoseiten (Notfallnummer, Sommerpause, Umzug des Diözesanbüros, Stufenkonferenz der Jufis), Anne-Frank-Projekt.', 'Juni 2025, ohne Heftnummer'],
    ],
    'brief' => [
        [2019, 2, 'Erster Georgsbrief als Newsletter ergänzend zum Georgsboten. Modulwoche 22.–28.04.2019 ausgebucht (Warteliste), Multiplikatoren gesucht, „Diesen Sommer wird es blau!“, Fragen zur Pfarrei der Zukunft.'],
        [2019, 3, 'DIY fürs Lager (3.–5. Mai 2019, Brex), Game of Rover (13.–15. September 2019), Neues aus dem Diözesanbüro.'],
        [2019, 4, 'DPSG-Ausbildung, Teamertreffen 10./11. Mai 2019 in Bad Ems, Neues aus dem Diözesanbüro, Coaching in der DPSG Trier, Ostergruß.'],
        [2019, 5, 'Stufenkonferenzen am 27.10.2019 (die Pfadfinderstufe fehlte in der Terminliste im Georgsboten), In eigener Sache.'],
        [2019, 6, 'Save the Date Stufenkonferenzen, In eigener Sache, Sommergruß. Im Juli kein Georgsbrief.'],
        [2019, 8, 'Planänderung, Save the Date Stufenkonferenzen 26.–27.10.2019 (Marienburg/Bullay), Einladung zur Fachkonferenz Ich-Du-Wir.'],
        [2019, 9, 'Stufenkonferenzen auf der Marienburg/Bullay mit Anmeldeformular (Teilnahmegebühr 25 €).'],
        [2019, 10, 'Änderungen bei der Pfadfinderstufe (Stufenkonferenz zeitgleich am 26. Oktober), Dank an das Team des LJR Saar.'],
        [2019, 11, 'Neue Rubrik „Impuls“ (Was gibt uns Halt?), Ausbildung, neu: DLvO, Hinweis kurz vor der Diözesanversammlung.'],
        [2019, 12, 'Impuls „Mensch, du hast Zukunft!“, Rückblick Diözesanversammlung 2019, T-Shirts mit DV-Logo (fair gehandelt), Modulwochenendkurs, Weihnachtsgruß.'],
        [2020, 1, 'Impuls des Diözesankurats, Nachruf, Woodbadge-Module (Kompaktprogramm an drei Wochenenden), Stufen, Prävention.'],
        [2020, 2, 'Impuls eines Bezirkskurats, Nachruf, Woodbadgeausbildung, Stufen, Prävention.'],
        [2020, 3, 'Corona-Ausgabe: Zusammenhalt, Gebet des Bischofs, Segen des Papstes am 27.03., Woodbadge, Fit fürs Lager 2020, „gute Tat“ in Zeiten der Pandemie, Ideen für vor Ort.'],
        [2020, 4, 'Ideen und Links für Gemeinschaft in der Krise, WöBiLix 2020 im Saarland (Online-Anmeldung), Fit fürs Lager 2020 wegen Corona abgesagt.'],
        [2020, 5, 'Gedenktext eines Bezirkskurats für die Verstorbenen, Ausbildung in diesen Zeiten, WöBiLix 2020, neue Corona-Rubrik auf der Homepage, In eigener Sache.'],
        [2020, 8, 'Nach den Sommerferien, Gruppenstunden unter Auflagen, Silvester-WBK der Pfadfinderstufe, Coaching und BeratungsPower, Save the Date, Aktion der Bolivien-Sammlung.'],
        [2020, 9, 'Ausblick Ausbildung 2021 (Woodbadge-Module auf der Marienburg), Silvester-WBK, digitale Veranstaltungen, Corona-Rubrik.'],
        [2020, 10, 'Rückblick auf fast drei Jahre als Diözesanvorstand, WöBiLix 2020 („Auf dem Weg zum Zaubertrank“), Ausbildung 2021, Ausbildung bewegt.'],
        [2020, 11, 'Würdigung des scheidenden Diözesanvorsitzenden, Vorstellung des neuen Vorstands, Hinweis auf die Diözesanversammlung, Ausbildung 2021, Corona-Rubrik.'],
        [2021, 1, 'Impuls („Wer nimmt mich an der Hand?“), Ausbildung 2021, Termine, Fit fürs Lager.'],
        [2021, 2, 'Impuls eines Bezirkskurats, „Nachhaltig! Wen sonst?“, Ausbildung 2021.'],
        [2021, 3, 'Vorwort „Wenn alles normal wäre, …“, Impuls, Rover and out, Ausbildung 2021, Hintergründe zu Finanzen, Haftpflicht und Versicherung.'],
        [2021, 5, 'Impuls, Wöbin Hood (Wö-Aktionstag in Trier im Herbst), Woodbadge-Kurs Wö-Stufe, Wö-Bundesunternehmen 2023, Beitrag der AG Ausbildung.'],
        [2021, 7, 'Ferientag in Rheinland-Pfalz und dem Saarland, Neustart der Ausbildung, Prävention, Modulwoche im Frühjahr 2022, Neustart-Schulungen und Impulstreffen.'],
        [2021, 9, 'Green iScout fällt aus, Ferientag, Ausbildung-Neustart mit Änderungen an der Modulwochenendreihe, Modulwoche im Frühjahr 2022.'],
        [2021, 10, 'Teamer*innen für Wö-WBK gesucht, Weiterentwicklung der Mitbestimmung, Umfrage auf Bundesebene, Pfadistufen-Stammtisch, DAK-Mitglieder gesucht, Ferientag.'],
        [2021, 11, 'Impuls („Grenzen, die Corona gekonnt aus unserem Blickfeld verdrängt hat …“), Wö-WBK-Teamer*innen, Umfrage, Stammtisch, DAK-Mitglieder gesucht.'],
        [2022, 1, 'Impuls zum Jahresbeginn, Zeitzeug*innen-Interview zur DPSG in Wittlich, Lebensbereiche von Jugendlichen heute.'],
        [2022, 2, 'Modulwochenenden finden statt, Seminar „Internationale Begegnungen leiten“ mit Hinweisen auf Fördergelder.'],
        [2022, 3, 'Hilfsmöglichkeiten angesichts des Krieges, angepasstes Diözesanlogo, Jugendsammelwoche, Absage der Modulwoche 2022.'],
        [2022, 4, 'Passions-Impuls, DL-Treffen und 24-Stunden-Gebet, nachgeholter Neujahrsempfang.'],
        [2022, 5, 'Verabschiedung des langjährigen Bildungsreferenten, Vorstellung der Nachfolgerin, Beitrag zu G9 und Entlastung für Schüler*innen.'],
        [2022, 6, 'Wöbin Hood, spirituelles Angebot, Seminar Bild- und Tonbearbeitung für die Öffentlichkeitsarbeit, Scoutergy (Diözesanlager 2023) sucht Helfer*innen.'],
        [2022, 7, 'Bistums-Notfallnummer für die kirchlichen Jugendverbände, Empfehlungen zum Umgang mit Corona, Sommergruß.'],
        [2022, 9, 'Rover-Aktion („Rover kommen zum Zug“), Förderprogramm „Vereine nach Corona“, Zeitzeugin zum Bundeskongress EXODUS und zum Beginn der Bolivienpartnerschaft.'],
        [2022, 10, 'Kloster Himmerod wird nicht Tagungsort, Schließung von Jugendhaus und Tagungshaus Sonnental, Förderprogramm „Vereine nach Corona“, Zeitzeugin.'],
        [2023, 7, 'Das Wichtigste aus dem Georgsbrief jetzt auch als Podcast, Bistumsnotfallnummer, Modulwochenendreihe im Herbst, Partnerschaftswochenende, Feedback zu Scoutergy.'],
    ],
];
