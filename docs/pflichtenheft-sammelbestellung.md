# Pflichtenheft: Sammelbestellung beim Rüsthaus („Schattenshop“)

**Stand:** 19. Oktober 2026 · **Status:** Entwurf zur Abstimmung mit dem Stamm Roden · **Bezug:** Kluft (`docs/pflichtenheft.md`, `docs/beitraege.md`, `docs/nachrichten.md`)

Mitglieder bzw. ihre Eltern bestellen Kluft, Halstuch, Aufnäher und andere
Ausrüstung über die Stammes-Website beim Stamm. Die zuständige Person
(in Roden: Michele Franke, Rüsthaus-Bestellungen) sammelt die Wünsche in
Bestellrunden, bestellt gesammelt beim Rüsthaus, verteilt die Ware und
behält den Überblick, wer was bekommen und bezahlt hat.

Anforderungen tragen IDs der Form `SB-<Nr>`. **MUSS** = Pflicht, **SOLL** =
Pflicht außer mit begründeter Ausnahme, **KANN** = optional.

---

## 1. Ziel und Abgrenzung

### 1.1 Ziel

1. **Weg mit Zetteln, WhatsApp und Excel:** Bestellungen kommen strukturiert an (Artikel, Größe, Anzahl, für wen, Kontakt).
2. **Eine Sammelbestellung je Runde:** Michele sieht auf einen Blick, was beim Rüsthaus zu bestellen ist, nach Artikel und Größe summiert.
3. **Ausgabe und Bezahlung nachvollziehbar:** Wer hat was bekommen, wer muss noch zahlen. Die Kasse (Hendrik Schuler) sieht offene Beträge.
4. **Wenig Pflegeaufwand:** Der Katalog umfasst nur das, was der Stamm wirklich anbietet. Kein Nachbau des Rüsthaus-Shops.

### 1.2 Was es nicht ist

- **Kein Online-Shop mit Bezahlung:** keine Zahlungsanbieter, keine Kreditkarten, kein PayPal. Bezahlt wird per Überweisung oder bar (SB-50).
- **Keine Schnittstelle zum Rüsthaus:** Das Rüsthaus bietet keine öffentliche Bestell-API. Michele bestellt dort wie bisher selbst; Kluft liefert die Liste dazu (SB-41).
- **Kein Lager:** Restbestände des Stamms KANN eine spätere Ausbaustufe abbilden (Kapitel 10).

---

## 2. Begriffe

| Begriff | Bedeutung |
|---|---|
| **Artikel** | Etwas, das der Stamm anbietet, z. B. „Kluft-Hemd Langarm“, mit Varianten (Größen) und Preis. |
| **Bestellrunde** | Zeitraum, in dem bestellt werden kann, mit Bestellschluss. Danach bestellt der Stamm gesammelt beim Rüsthaus. |
| **Bestellung** | Was eine Person in einer Runde bestellt: eine oder mehrere Positionen. |
| **Position** | Artikel + Variante + Anzahl, optional für ein bestimmtes Kind („für wen“). |
| **Sammelliste** | Alle Positionen einer Runde, nach Artikel und Variante summiert: das, was beim Rüsthaus bestellt wird. |
| **Zuschuss** | Anteil, den der Stamm übernimmt, z. B. Halstuch für neue Mitglieder kostenlos. |

---

## 3. Rollen

| Rolle | Wer (Roden) | Darf |
|---|---|---|
| **Besteller*in** (öffentlich, ohne Konto) | Mitglieder, Eltern, Leitende | Katalog ansehen, bestellen, eigene Bestellung über den Link in der Mail ansehen, ändern oder stornieren, solange die Runde offen ist. |
| **Material** (neue Rolle) | Michele Franke | Katalog pflegen, Runden öffnen und schließen, Bestellungen bearbeiten, Sammelliste und Ausgabe, Bezahlung abhaken. |
| **Kasse** (vorhanden) | Hendrik Schuler | Offene und bezahlte Beträge sehen, Bezahlung abhaken; nicht den Katalog ändern. |
| **Admin** | Stammesvorstand | alles, Funktion ein- und ausschalten. |

- **SB-01** MUSS: Die Rolle „Material“ wird wie „Kasse“ unter „Benutzer“ vergeben und gilt nur auf Stammes-Websites.
- **SB-02** MUSS: Ohne Rolle „Material“, „Kasse“ oder Admin ist im Backend nichts davon sichtbar.

---

## 4. Ablauf einer Bestellrunde

1. **Katalog prüfen:** Michele prüft Artikel und Preise (Rüsthaus-Preise ändern sich).
2. **Runde öffnen:** z. B. „Herbst 2026“, Bestellschluss 15.11. Optional Mail an die Leitenden mit dem Link.
3. **Bestellen:** Mitglieder bzw. Eltern bestellen über die Website und bestätigen per Mail-Link.
4. **Bestellschluss:** Die Runde schließt automatisch. Michele sieht die Sammelliste und bestellt beim Rüsthaus.
5. **Lieferung:** Michele markiert die Runde als „geliefert“. Die Besteller*innen bekommen eine Mail: Ware ist da, Abholung z. B. in der Gruppenstunde, Betrag und Bankverbindung.
6. **Ausgabe:** Bei der Übergabe hakt die Leitung bzw. Michele die Position als „ausgegeben“ ab.
7. **Bezahlung:** Kasse bzw. Michele hakt „bezahlt“ ab. Offene Beträge bleiben sichtbar.
8. **Abschluss:** Die Runde ist erledigt, wenn alles ausgegeben und bezahlt ist. Danach läuft die Löschfrist (SB-71).

---

## 5. Funktionale Anforderungen

### 5.1 Katalog

- **SB-10** MUSS: Artikel mit Name, kurzer Beschreibung, Rüsthaus-Artikelnummer, Link zur Rüsthaus-Seite, Preis je Variante, aktiv/inaktiv, Reihenfolge.
- **SB-11** MUSS: Varianten, meist Größen (z. B. 128, 140, S, M, L), mit eigenem Preis, falls abweichend.
- **SB-12** SOLL: Kategorien, z. B. Kluft, Halstuch und Knoten, Aufnäher und Abzeichen, Sonstiges.
- **SB-13** SOLL: Eigenes Foto je Artikel aus der Mediathek. Fotos aus dem Rüsthaus-Shop werden **nicht** kopiert (Urheberrecht); stattdessen der Link (SB-10).
- **SB-14** KANN: Hinweis je Artikel, z. B. „Stufenhalstuch nur nach Absprache mit der Leitung“ oder „Größentabelle beim Rüsthaus“.
- **SB-15** KANN: Artikel nur für bestimmte Stufen anzeigen (z. B. Stufenaufnäher).
- **SB-16** MUSS: Preisänderungen gelten nur für neue Bestellungen. Bestehende Positionen behalten ihren Preis (Preis wird bei der Bestellung festgehalten), außer Michele passt ihn bewusst an (SB-33).

### 5.2 Bestellrunden

- **SB-20** MUSS: Runde mit Name, Beginn, Bestellschluss, Status (geplant, offen, geschlossen, bestellt, geliefert, erledigt).
- **SB-21** MUSS: Höchstens eine Runde ist gleichzeitig offen. Ist keine offen, zeigt die Seite den Katalog mit dem Hinweis, wann die nächste Runde startet (Text pflegbar).
- **SB-22** SOLL: Automatisches Schließen zum Bestellschluss (Cronjob/Scheduler) und Mail an „Material“ mit der Sammelliste.
- **SB-23** KANN: Mindestmengen oder „nur bei genug Bestellungen“ je Artikel (z. B. Sonderanfertigungen).

### 5.3 Bestellen (öffentlich)

- **SB-30** MUSS: Seite „Ausrüstung bestellen“ (Adresse z. B. `/bestellen`), im Menü abschaltbar wie jede Seite. Einleitungstext pflegbar.
- **SB-31** MUSS: Formular mit Name, E-Mail, optional Telefon. Positionen: Artikel, Variante, Anzahl und „für wen“ (Vorname des Kindes, Stufe). Dazu Bemerkung, Abholung (Gruppenstunde der Stufe, nach Absprache) und Bezahlart (Überweisung, bar).
- **SB-32** MUSS: Bestätigung per Mail-Link (Double-Opt-in wie bei Veranstaltungsanmeldungen). Unbestätigte Bestellungen verfallen nach 48 Stunden und zählen nicht in die Sammelliste.
- **SB-33** MUSS: Über den Link in der Bestätigungsmail kann die Person ihre Bestellung bis zum Bestellschluss ansehen, ändern und stornieren. Danach nur noch ansehen. Änderungen nach Bestellschluss macht Michele im Backend; die Person bekommt eine Mail.
- **SB-34** MUSS: Einwilligungstext (Datenverarbeitung, Löschfrist) und bei Bestellungen für Kinder die Bestätigung, sorgeberechtigt zu sein oder im Auftrag zu bestellen. Texte wie bei Veranstaltungen (`event_consent`).
- **SB-35** MUSS: Summe der Bestellung mit Hinweis „vorläufig, Endpreis nach Lieferung“, falls das Rüsthaus die Preise ändert.
- **SB-36** SOLL: Spamschutz wie beim Kontaktformular (Honeypot, Zeitprüfung, Drosselung).
- **SB-37** KANN: Nur für Mitglieder: Abgleich mit NaMi über die vorhandene Mitgliederprüfung (`docs/mitgliederabgleich.md`), bevor eine Bestellung angenommen wird. Standard: aus (Eltern kennen die Mitgliedsnummer oft nicht).

### 5.4 Backend für „Material“

- **SB-40** MUSS: Liste der Bestellungen einer Runde mit Filter nach Status (unbestätigt, bestätigt, storniert), Stufe und Artikel. Die Bestellung lässt sich öffnen und bearbeiten, ebenso eine Bestellung von Hand eintragen (z. B. für jemanden ohne E-Mail).
- **SB-41** MUSS: Sammelliste: je Artikel und Variante die Summe, mit Rüsthaus-Artikelnummer. Export als CSV/Excel und als Druckansicht. Das ist die Vorlage für den Rüsthaus-Warenkorb.
- **SB-42** MUSS: Ausgabeliste je Stufe bzw. Abholort: Wer bekommt was? Druckbar fürs Mitnehmen in die Gruppenstunde.
- **SB-43** MUSS: Je Position „ausgegeben“ abhaken, je Bestellung „bezahlt“ (Betrag, Datum, Art). Teilweise Lieferung möglich: Position „nachgeliefert“ oder in die nächste Runde verschieben.
- **SB-44** SOLL: Rundenmails an alle Besteller*innen der Runde, z. B. „Ware ist da“ (Vorlage pflegbar), mit Einzelbetrag und Verwendungszweck.
- **SB-45** SOLL: Kosten der Rüsthaus-Bestellung eintragen (Rechnungsbetrag, Versand), damit die Differenz zur Summe der Bestellungen sichtbar wird.
- **SB-46** KANN: Versandkosten anteilig umlegen oder vom Stamm tragen (Einstellung je Runde).

### 5.5 Bezahlung und Kasse

- **SB-50** MUSS: Bezahlung außerhalb von Kluft: Überweisung auf das Stammeskonto mit eindeutigem Verwendungszweck (z. B. `RH-2026-2-0123 Name`) oder bar bei Ausgabe. Bankverbindung als Einstellung.
- **SB-51** MUSS: Offene Beträge je Runde und gesamt, sichtbar für „Material“ und „Kasse“.
- **SB-52** SOLL: Zuschüsse: Je Artikel ein Stammesanteil, z. B. Halstuch zum Eintritt 100 %. Ob der Zuschuss gilt, entscheidet Michele je Position (Häkchen). Ausgewiesen wird der Zuschuss als Summe je Runde für die Kasse.
- **SB-53** KANN: Erinnerungsmail bei offenem Betrag nach X Tagen (einstellbar, Standard aus).
- **SB-54** KANN: Kontoauszug-Abgleich (CSV-Import, Verwendungszweck erkennen). Nur, wenn es bei den Mitgliedsbeiträgen auch kommt (`docs/beitraege.md`, „Später“).

### 5.6 Benachrichtigungen

- **SB-60** MUSS: Mails an Besteller*innen: Bestätigungslink, „Bestellung bestätigt“ (mit Übersicht und Änderungslink), Änderung durch den Stamm, Storno, „Ware ist da“.
- **SB-61** MUSS: Mail an „Material“ bei jeder bestätigten Bestellung (abschaltbar; dann nur Tageszusammenfassung) und beim Bestellschluss.
- **SB-62** MUSS: Absender ist die Website (`MAIL_FROM_*`), Antwort-an ist die zuständige Adresse. In Roden ist das eine Einstellung, z. B. michele@dpsg-roden.de oder eine Funktionsadresse.

---

## 6. Einbindung in Kluft

- **SB-80** MUSS: Funktionsschalter `sammelbestellung` in `App\Support\Features::KATALOG` mit `'ebene' => 'Stamm'`, Bereich „Kasse“ bzw. „Material“, Standard **aus**. Abgeschaltet: keine Route, kein Menüpunkt, kein Backend-Bereich. DV und Bezirk sehen die Funktion nicht.
- **SB-81** SOLL: Ab Ausbaustufe 4 (wie Mitgliedsbeiträge und Veranstaltungen).
- **SB-82** MUSS: Eigene Tabellen (`shop_artikel`, `shop_varianten`, `shop_runden`, `shop_bestellungen`, `shop_positionen`); keine Änderungen an Tabellen anderer Bereiche. Migrationen mit aktuellem Datum und Uhrzeit (`docs/roden.md`, Aufteilung).
- **SB-83** SOLL: Wiederverwenden statt neu bauen: Double-Opt-in, Einwilligung und Löschfristen von den Veranstaltungsanmeldungen, Spamschutz vom Kontaktformular, Rollen und Rechte wie bei „Kasse“, Mailversand über die vorhandene Warteschlange.
- **SB-84** MUSS: Funktioniert ohne Cronjob nur eingeschränkt (kein automatisches Schließen, keine Mails aus der Warteschlange). Der Statuscheck `/api/status` zeigt das bereits.
- **SB-85** MUSS: Tests für Bestellen, Bestätigen, Ändern, Bestellschluss, Sammelliste, Rechte und den Schalter (aus = 404), für alle drei Instanzen grün.

---

## 7. Datenschutz (KDG)

- **SB-70** MUSS: Gespeichert wird nur: Name, E-Mail, optional Telefon, Vorname und Stufe des Kindes, Bestellpositionen, Bezahlstatus. Keine Adressen, keine Geburtsdaten, keine Größenangaben über den bestellten Artikel hinaus.
- **SB-71** MUSS: Löschfrist: Personenbezogene Daten einer Runde werden **6 Monate nach Abschluss** gelöscht bzw. anonymisiert (Einstellung). Für die Kasse bleiben nur Summen. Unbestätigte Bestellungen werden nach 48 Stunden gelöscht.
- **SB-72** MUSS: Hinweis in der Datenschutzerklärung (Textbaustein wird mitgeliefert und beim Einschalten angezeigt, wie beim Nachrichten-Eingang).
- **SB-73** MUSS: Diagnose-Ausgaben und Exporte für die Technik ohne Personendaten. Der Export der Sammelliste (SB-41) enthält keine Namen; die Ausgabeliste (SB-42) schon und ist nur für „Material“ und Admin.
- **SB-74** MUSS: Alle Änderungen im Backend landen im Änderungsprotokoll (Audit-Log), wie bei den Mitgliedsbeiträgen.

---

## 8. Abnahme

Abgenommen ist die Funktion, wenn in Roden auf einer Testrunde:

1. Michele drei Artikel mit Größen anlegt und eine Runde öffnet.
2. Zwei Testbestellungen über die Website (eine für ein Kind) bestätigt, eine geändert und eine storniert werden.
3. Nach Bestellschluss die Sammelliste stimmt und als Excel herunterladbar ist.
4. „Ware ist da“ verschickt, ausgegeben und bezahlt abgehakt wird und Hendrik die offenen Beträge sieht.
5. Abgeschaltet: `/bestellen` liefert 404, im Backend ist nichts mehr zu sehen; auf dv-trier und saar-hochwald gibt es die Funktion nicht.
6. Nach (testweise verkürzter) Löschfrist sind die Personendaten weg und die Summen noch da.

---

## 9. Offene Fragen an den Stamm

1. **Wer darf bestellen?** Nur Mitglieder und Eltern, oder auch Freund*innen und Förderverein? Prüfen gegen NaMi (SB-37) ja oder nein?
2. **Bezahlen:** vorab (Bestellung erst nach Zahlungseingang) oder nach Ausgabe? Vorab senkt das Risiko, auf Ware sitzenzubleiben, ist aber mehr Aufwand.
3. **Zuschüsse:** Was übernimmt der Stamm (z. B. Halstuch und Aufnäher bei Aufnahme)? Auch Zuschüsse nach Bedarf, die diskret laufen müssen?
4. **Katalog:** Welche Artikel konkret (Kluft-Hemden, Halstücher Stamm/Stufe, Knoten, Aufnäher, Bücher)? Gibt es ein eigenes Stammeshalstuch bzw. eigene Aufnäher, die nicht vom Rüsthaus kommen?
5. **Rhythmus:** feste Runden (z. B. Frühjahr/Herbst, vor dem Sommerlager) oder laufend mit monatlichem Bestellschluss?
6. **Rüsthaus:** Bestellt Michele über ein Stammeskonto beim Rüsthaus, gibt es Mengenrabatt oder Versandkostenfreiheit ab einem Betrag? Lieferadresse ist das Pfadfinderheim oder Michele privat? (Steht nicht in Kluft, nur zur Planung.)
7. **Zuständige Adresse** für Antworten (SB-62): michele@dpsg-roden.de oder eine Funktionsadresse wie material@dpsg-roden.de?
8. **Rollen:** Darf die Stufenleitung bei der Ausgabe abhaken (SB-43), oder nur Michele?
9. **Name auf der Website:** „Ausrüstung bestellen“, „Kluft-Bestellung“, „Rüsthaus-Sammelbestellung“?

---

## 10. Umsetzung in Phasen (Vorschlag, nicht bindend)

| Phase | Inhalt | Ergebnis |
|---|---|---|
| **1** | Katalog, eine Runde, Bestellformular mit Double-Opt-in, Backend-Liste, Sammelliste mit Export, Rolle „Material“, Schalter | Michele kann die erste Runde fahren. |
| **2** | Ausgabe- und Bezahlstatus, Kasse-Ansicht, „Ware ist da“-Mail, Löschfrist | Ablauf vollständig, Kasse eingebunden. |
| **3** | Zuschüsse, Erinnerungen, Versandkosten-Umlage, Stufenfilter im Katalog | Komfort. |
| später | Lagerbestand des Stamms, Kontoauszug-Abgleich, Übernahme in den Einrichtungsassistenten für andere Stämme | nach Bedarf |

Für andere Stämme gilt die Funktion genauso. Sie ist standardmäßig aus und
wird je Stamm unter „Verwaltung → Funktionen“ eingeschaltet.
