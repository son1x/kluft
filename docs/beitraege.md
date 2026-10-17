# Mitgliedsbeiträge (Kasse)

Übersicht für Stämme: Wer hat den Mitgliedsbeitrag für das Halbjahr bezahlt,
wer nicht, und passt das zur Rechnung des Bundesverbands? Die meisten Stämme
rechnen halbjährlich mit dem Bundesverband ab; Kluft rechnet deshalb in
Halbjahren (1. Halbjahr = Januar bis Juni, 2. Halbjahr = Juli bis Dezember).

## Einschalten

Die Kasse gibt es nur auf Stammes-Websites (Ebene „Stamm“). Bei
Diözesanverband und Bezirk erscheinen weder die Funktion noch die Rolle
„Kasse“ – Mitgliedsbeiträge rechnet der Stamm ab.

1. **Verwaltung → Funktionen → Mitgliedsbeiträge** einschalten. Bei Stämmen
   aus dem Einrichtungsassistenten ist die Funktion schon an.
2. Voraussetzung ist NaMi als Mitgliederverwaltung (`MITGLIEDER_DRIVER=nami`,
   im Assistenten „NaMi-Abgleich“).
3. Unter **Benutzer** der Person, die die Kasse führt, die Rolle
   **Kasse (Mitgliedsbeiträge)** geben. Admins sehen die Kasse ebenfalls,
   Vorstand und Verwaltung nicht automatisch.
4. Die DPSG-Nummer des Stamms muss bekannt sein (aus dem Assistenten oder
   bei der Gruppierung „Stamm“).

## Ablauf je Halbjahr

1. **Kasse → Mitgliedsbeiträge → Beitragssätze**: je Beitragsart, wie NaMi
   sie führt (voller Beitrag, familienermäßigt, ermäßigt, jeweils mit und
   ohne Stiftungseuro, dazu „Übernahme“)
   zwei Beträge für ein Halbjahr eintragen: den **Bundesbeitrag**, den der
   Bundesverband in Rechnung stellt (laut Rechnung bzw. Beitragsordnung), und
   den **Stammesanteil**, den der Stamm beschließt. Das Soll eines Mitglieds
   ist die Summe; der Bundesanteil wird je Beitrag mitgespeichert.
2. **Aus NaMi abrufen**: Halbjahr wählen, mit dem eigenen NaMi-Konto anmelden
   (oder dem im Profil gespeicherten Zugang). Kluft holt im Hintergrund die
   Mitglieder des Stamms mit Beitragsart, Ein- und Austritt und legt für alle,
   die im Halbjahr Mitglied waren, einen Beitrag mit dem Soll ihrer
   Beitragsart an. Schnuppermitglieder und Nicht-Mitglieder werden nicht
   übernommen. Bei vielen Mitgliedern dauert das einige Minuten; der Stand
   steht über der Liste.
3. **Zahlungen abhaken**: Je Zeile „Zahlung“ (Betrag, Datum, Zahlart, Notiz;
   auch Teilzahlung oder „erlassen“) oder mehrere auswählen und „Als bezahlt
   markieren“, etwa nach dem Lastschrifteinzug. Ein Fehler lässt sich mit
   „Zurücksetzen“ beheben.
4. **Offene als CSV** für Erinnerungen oder die Vorstandssitzung.
5. **Kasse → Bundesverbandsrechnung**: Rechnung des Halbjahres eintragen
   (Anzahl Mitglieder, Betrag, Datum, wann bezahlt). Daneben stehen die Zahl
   der Beiträge in Kluft, die Summe der Bundesbeiträge (sollte dem
   Rechnungsbetrag entsprechen), das Soll gesamt und was eingegangen ist.
   Weichen Anzahl oder Betrag ab, steht „abweichend“ dabei. Auch erlassene
   Beiträge zählen beim Bundesbeitrag mit – den schuldet der Stamm trotzdem.

Erneut abrufen ist jederzeit möglich: Neue Mitglieder kommen dazu, offene
Beiträge folgen einer geänderten Beitragsart oder einem geänderten Satz,
bezahlte bleiben, wie sie sind. Wer nicht mehr in NaMi steht, wird nicht
gelöscht, sondern als „nicht mehr in NaMi“ markiert – seine Zahlungen
bleiben erhalten. Geänderte Beitragssätze passen offene Beiträge ab dem
laufenden Halbjahr sofort an; ältere Halbjahre bleiben bei ihren Sätzen.

## NaMi-Testabfrage

**Verwaltung → NaMi-Testabfrage** (für Admins und die Kasse) fragt NaMi so ab
wie der Beitragsabruf – Anmeldung, Gruppierungsbaum, Mitgliederliste, zwei
Beispiel-Details, Beitragsarten – und zeigt nur die Form der Antworten:
Namen, Nummern, Adressen und Daten als Muster („Aaaa“, „9999-99-99“),
Merkmale wie Mitgliedstyp nur als Anzahlen ab zehn Personen. Das Ergebnis
lässt sich kopieren und an die Technik geben, z. B. für die Umstellung auf
NaMi 3. Es wird nicht gespeichert; die Abfrage steht im Protokoll.

## Daten und Datenschutz

- Gespeichert wird nur, was die Kasse braucht: Name, Mitgliedsnummer,
  Beitragsart, Ein- und Austritt, NaMi-ID und je Halbjahr Soll, Zahlung und
  Notiz. Keine Adressen, Geburtsdaten oder Bankverbindungen.
- Details werden nur für neue oder in NaMi geänderte Personen abgefragt.
- Das NaMi-Passwort wird nicht gespeichert, außer die Person legt ihren
  Zugang selbst verschlüsselt im Profil ab. In die Warteschlange geht nur
  das Sitzungs-Cookie, verschlüsselt.
- Abruf, Zahlungen, Sätze und Export stehen im Protokoll.
- Beitragslisten sind Buchungsbelege des Stamms; wie lange sie aufbewahrt
  werden, entscheidet der Vorstand. Kluft löscht sie nicht automatisch.

## Später

Ratenzahlung bei Lagern und Veranstaltungen sowie Kosten, die sich mehrere
Stellen teilen (z. B. Teilnehmende und Bezirk), stehen in der Roadmap unter
„Stämme“.
