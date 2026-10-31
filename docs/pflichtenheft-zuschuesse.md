# Pflichtenheft: Zuschussanträge für Veranstaltungen

**Stand:** 31. Oktober 2026 · **Status:** Entwurf, 2. Fassung (Antworten Roden eingearbeitet) · **Bezug:** Veranstaltungen und Anmeldungen (`docs/pflichtenheft.md` 6.7), Teilnahmebeiträge (`docs/zahlungen.md`)

Für Freizeiten, Schulungen und Bildungsmaßnahmen beantragen Stämme, Bezirke
und der DV Zuschüsse beim örtlichen **Jugendamt** (Kreis) und beim
**Landesjugendamt** des Saarlandes. Vorlage ist der gemeinsame Vordruck
„Antrag und Nachweis zur Förderung der Kinder- und Jugendarbeit bei Land,
Kreisen und Regionalverband“ (Stand 14.12.2018, Formulare A–G). Heute werden
Teilnahmeliste und Antrag von Hand aus der Anmeldeliste abgeschrieben.
Kluft soll beides aus den Anmeldungen erzeugen.

Anforderungen tragen IDs der Form `ZU-<Nr>`. **MUSS** = Pflicht, **SOLL** =
Pflicht außer mit begründeter Ausnahme, **KANN** = optional.

---

## 1. Der Vordruck

| Formular | Inhalt | Was Kluft beitragen kann |
|---|---|---|
| **A** Antrag und Nachweis | Antragsteller, Bank, Kontakt; Art (Schulung, Bildung, Freizeit, sonstige); Ort, Beginn, Ende; **Zusammensetzung** nach Kreis (HOM, MZG, NK, SB, SLS, WND, Sonstige) × Kinder/Jugendliche, Betreuungspersonen, Referent*innen; Kosten- und Finanzierungsplan | fast alles vorausfüllen; Kosten von Hand |
| **B** Teilnahmeliste | Nr., Vor- und Zuname, PLZ/Wohnort/Straße, Geburtsdatum, Funktion (TN, B, R, L), Unterschrift; am Ende Zahl der Teilnehmer/Teilnehmerinnen und Betreuer/Betreuerinnen | vollständig, im Querformat; Unterschrift auf Papier |
| **C** Aufenthaltsbestätigung | nur für NK, MZG und Saarpfalz-Kreis; Stempel der Gemeinde am Lagerort | vorausfüllen |
| **D** Bericht Freizeit | Leitung und Betreuer*innen mit Qualifikation (Juleica, langjährige Tätigkeit), Programmablauf als Anlage, „programmgemäß durchgeführt“ | vorausfüllen (Namen, Qualifikation, Termin) |
| **E** Bericht Schulung/Bildung | Thema, Leitung, Inhalt/Methodik, Referent*innen, Verlauf, Bewertung | vorausfüllen (Kopf, Namen) |
| **F** Liste Führungszeugnisse | verbleibt beim Träger | später, siehe 6 |
| **G** Dokumentation BKiSchG | verbleibt beim Träger, Vorfälle | nur Kopf vorausfüllen, Rest bewusst auf Papier |

**Technischer Befund:** Der Vordruck ist ein ausfüllbares PDF (388
Felder). Es ist aber mit Adobe-Rechteschutz verschlüsselt (PDF 1.6, XRef-Streams).
Kluft befüllt das Original deshalb nicht und hebt den Schutz nicht auf.
Kluft erzeugt die Formulare stattdessen selbst als PDF (dompdf, wie die
Teilnahmebescheinigung), mit denselben Feldern und Texten. Die Teilnahmeliste darf laut
Roden ein eigenes Layout haben. Formular A darf ein Nachbau sein: Die Ämter arbeiten
ohnehin auf Papier (Roden, 31.10.2026).

## 2. Ablauf

1. **Veranstaltung anlegen:** „Zuschuss beantragen“ einschalten, Art der Maßnahme wählen. Damit fragt das Anmeldeformular die nötigen Angaben ab (ZU-10).
2. **Anmeldungen** laufen wie bisher. Leitende und Referent*innen trägt man im Backend nach (es gibt „Teilnehmende von Hand eintragen“), mit Funktion.
3. **Nach der Veranstaltung:** Anwesenheit abhaken (wer war wirklich da). Dann im Backend „Zuschussunterlagen“ erzeugen: Formular A, Teilnahmeliste (B), je nach Art D oder E, bei NK/MZG/Saarpfalz C.
4. **Ausdrucken, unterschreiben lassen, einreichen.** Die unterschriebene Teilnahmeliste bewahrt der Träger 5 Jahre auf (Formular A: „Die Belege sind 5 Jahre aufzubewahren“).

## 3. Angaben je Person

- **ZU-01** MUSS: Für die Teilnahmeliste braucht jede Person:
  - Vor- und Nachname (gibt es);
  - Straße, PLZ, Wohnort (PLZ und Wohnort gibt es als Feld, Straße fehlt);
  - **Geburtsdatum** (heute nur Geburtsjahr);
  - **Funktion:** Teilnehmer*in (TN), Betreuer*in (B), Referent*in (R), Leiter*in (L).
- **ZU-02** MUSS: **Geschlecht** für die Zählung auf Formular B („Zahl der Teilnehmer / Teilnehmerinnen / Betreuer / Betreuerinnen“): weiblich, männlich, divers. „Divers“ wird als **eigene Kategorie** gezählt (zusätzliche Zeile „Zahl der Teilnehmenden/Betreuenden divers“).
- **ZU-03** MUSS: **Kreis** für die Zusammensetzung in Formular A, abgeleitet aus der PLZ (Saarland-Tabelle PLZ → HOM, MZG, NK, SB, SLS, WND; sonst „Sonstige“). Im Backend korrigierbar, weil eine PLZ zwei Kreise berühren kann.
- **ZU-04** SOLL: Für Leitung und Betreuer*innen: **Qualifikation** für Formular D/E: Juleica ja/nein, langjährige Tätigkeit, sonstige mit Text. An der Veranstaltung reicht ja/nein.
- **ZU-05** MUSS: **Anwesenheit:** Standard ist „anwesend“ (ein Haken je Person). Bei mehrtägigen Veranstaltungen SOLL sie sich je Tag abhaken lassen (vorbelegt aus der Tage-Auswahl der Anmeldung). Nur Anwesende kommen in Liste und Zählung.
- **ZU-06** SOLL: **Stammdaten der Leitenden:** Bei den Personen (Leitende, `people`) lassen sich nur im Backend und verschlüsselt hinterlegen: Anschrift, Geburtsdatum, Geschlecht, Juleica ja/nein mit Nummer und gültig bis. Nichts davon erscheint auf der Website. Beim Nachtragen der Leitung zu einer Veranstaltung wählt man die Person aus, und die Angaben werden übernommen. Kluft erinnert die Person bzw. den Vorstand 2 Monate vor Ablauf der Juleica (KANN). Gelöscht werden die Angaben, wenn die Person ausscheidet oder es verlangt.

## 4. Funktionen

- **ZU-10** MUSS: An der Veranstaltung: Schalter „Zuschuss beantragen“ und Art (Mitarbeiterschulung, Bildung, Freizeit, sonstige). Ist er an, stellt Kluft die Felder aus ZU-01 bis ZU-03 im Anmeldeformular automatisch auf Pflicht; sonst werden sie nicht abgefragt (Datensparsamkeit). Die Einwilligung nennt dann den Zweck „Zuschussantrag beim Jugend- und Landesjugendamt“.
- **ZU-11** MUSS: **Teilnahmeliste (B)** als PDF im Querformat mit allen Spalten des Vordrucks, Kopf (Antragsteller, Ort, Beginn, Ende), Zählung am Ende und Unterschriftsfeld. Sortierung: Leitung, Betreuer*innen, Referent*innen, Teilnehmende, je nach Name. Eine Liste, die für alle Zuschussgeber taugt (Wunsch Roden).
- **ZU-12** MUSS: **Formular A** als PDF: Antragsteller aus den Stammdaten (Name, Anschrift), Bank aus „Zahlungen“ (Bankverbindung), Rückfragen an die angemeldete Person, Art, Ort, PLZ, Beginn/Ende mit Uhrzeit, Zusammensetzung automatisch gezählt. Kosten- und Finanzierungsplan von Hand im Backend; **Teilnehmerbeiträge** schlägt Kluft aus den eingegangenen Teilnahmebeiträgen vor („Zahlungen“). Summen und „ungedeckte Kosten“ rechnet Kluft.
- **ZU-13** SOLL: **Formular D bzw. E** als PDF mit Kopf, Leitung und Betreuer*innen samt Qualifikation; Programm und Bericht als Textfelder im Backend.
- **ZU-14** KANN: **Formular C** vorausgefüllt (Gruppe, Leitung, Personenzahl, Zeitraum, Ort, Unterkunft), nur wenn Teilnehmende aus NK, MZG oder dem Saarpfalz-Kreis dabei sind.
- **ZU-15** SOLL: **Übersicht vorab**: Zählung je Kreis und Funktion, fehlende Angaben je Person (z. B. „Geburtsdatum fehlt bei 3 Personen“), damit man vor dem Druck nachfassen kann.
- **ZU-16** KANN: Export der Teilnahmeliste als Excel für eigene Zwecke.
- **ZU-17** SOLL: **Weitere Zuschussgeber** als eigene Vorlagen: in Roden die **Kreisstadt Saarlouis** und das **Bistum Trier** (BDKJ). Die Teilnahmeliste aus ZU-11 dient allen; je Zuschussgeber kommt nur der Antrag dazu, sobald die Formulare vorliegen. An der Veranstaltung wählt man einen oder mehrere Zuschussgeber.

## 5. Rollen und Einbindung

- **ZU-20** MUSS: Funktionsschalter `zuschuesse` in `Features::KATALOG`, alle Ebenen (der Vordruck gilt für Stamm, Bezirk und DV im Saarland), Standard aus.
- **ZU-21** MUSS: Unterlagen erzeugen darf, wer die Teilnehmendenliste der Veranstaltung sehen darf (wie Export heute).
- **ZU-22** MUSS: Gemeinsamer Kern (Veranstaltungen gehören der Haupt-Sitzung): Bau nur nach Abstimmung über `docs/abstimmung.md`, Felder als Erweiterung von `config('dpsg.event_fields')` und `registrations.data` (verschlüsselt), keine Umbenennungen.

## 6. Führungszeugnisse (später)

Formular F und die Präventionsordnung verlangen, dass der Träger die
erweiterten Führungszeugnisse der Betreuer*innen einsieht. Kluft könnte je
Leitenden „eingesehen am“ und „gültig bis“ speichern (keine Inhalte, kein
„Eintrag ja/nein“) und Formular F daraus füllen. Das ist ein eigenes Thema
(Personen, nicht Veranstaltungen) und kommt nur nach eigener Entscheidung.

## 7. Datenschutz (KDG)

- **ZU-30** MUSS: Anschrift, Geburtsdatum und Geschlecht nur, wenn die Veranstaltung „Zuschuss beantragen“ hat; Zweck in der Einwilligung genannt.
- **ZU-31** MUSS: Diese Angaben werden mit der Anmeldung gelöscht (bestehende Löschfrist der Veranstaltung). Weil die Belege 5 Jahre aufzubewahren sind, bleibt die **ausgedruckte, unterschriebene** Liste beim Träger; in Kluft bleiben nach der Frist nur die Zahlen des Antrags (ohne Namen).
- **ZU-32** MUSS: Die erzeugten PDFs werden nicht gespeichert, nur beim Abruf erzeugt; jeder Abruf steht im Audit-Log.
- **ZU-33** MUSS: Datenschutz-Auskunft und -Löschung (`PrivacyRequests`) erfassen die neuen Felder (sie liegen in `registrations.data`).

## 8. Entscheidungen und offene Fragen

**Entschieden (Roden, 31.10.2026):**
1. Formular A als Nachbau ist in Ordnung (die Ämter arbeiten auf Papier).
2. „Divers“ zählt als eigene Kategorie.
3. Anwesenheit idealerweise tageweise, meist reicht „anwesend“: Standard ein Haken, tageweise wahlweise.
4. Weitere Zuschussgeber: Kreisstadt Saarlouis und Bistum Trier.
5. An der Veranstaltung Juleica nur ja/nein; Nummer und Datum in den Stammdaten der Leitenden (ZU-06).

**Offen:**
- Formulare der Kreisstadt Saarlouis und des Bistums (BDKJ) fehlen noch.

## 9. Umsetzung in Phasen (Vorschlag)

| Phase | Inhalt |
|---|---|
| **1** | Zuschuss-Schalter je Veranstaltung, neue Felder (Straße, Geburtsdatum, Geschlecht, Funktion), Kreis aus PLZ, Anwesenheit (gesamt und je Tag), Teilnahmeliste B als PDF, Übersicht mit fehlenden Angaben |
| **2** | Formular A mit Zählung und Kosten-/Finanzierungsplan, Formular D/E, Stammdaten der Leitenden mit Juleica (ZU-06) |
| **3** | Anträge Kreisstadt Saarlouis und Bistum (ZU-17), Formular C, Excel-Export, ggf. Führungszeugnisse (Kapitel 6) |
