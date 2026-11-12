# Vor Ort: Begleiter für Veranstaltungen – Bereich in Kluft statt eigener App

**Stand:** 13. November 2026 · **Status:** entschieden, Stufe 1 gebaut · **Bezug:** docs/vor-ort.md

## Ausgangslage

Bei der Durchführung von Veranstaltungen fehlte ein Werkzeug fürs Team vor Ort: Wer ist da? Wer hat bezahlt? Wer isst vegetarisch? Wer schläft in welchem Zimmer? Bisher: ausgedruckte Listen, nachträgliches Abtippen, Fehler vor allem bei den Verpflegungsangaben.

Wünsche (November 2026):

- Veranstaltung auswählen und deren Daten betreuen.
- Sehen, wer bezahlt hat und wer nicht.
- Unterschriften der Teilnehmenden digital einsammeln (Zuschuss-Teilnahmeliste), eine je Veranstaltung.
- Zusatzinfos wie Platz, Zimmer und Zimmermitbewohnende.

## Rahmenbedingungen (Rückmeldungen)

| Frage | Antwort |
|---|---|
| Gerät | Das Büro stellt ein **Tablet**; Handy nur als Ausweichgerät → responsiv, Tablet zuerst |
| Netz am Veranstaltungsort | meist ja, aber **ein wichtiger zentraler Platz ist ohne Internet** → Offline nötig |
| Digitale Unterschriften | werden von den Zuschussgebern **anerkannt** und sind ein oft gewünschtes Feature |

## Entscheidung

1. **Kein eigenes App-Projekt**, sondern ein für Tablet und Handy gebauter Bereich **`/vor-ort` in Kluft**, später als Web-App installierbar (PWA). Gleiche Anmeldung, gleiche Rechte (`manageParticipants`), gleiche Daten; kein App-Store, keine doppelte Pflege.
2. **Ereignisse statt Direktänderungen** von Anfang an: Jede Änderung ist ein Ereignis mit eigener Kennung, das Kluft genau einmal verarbeitet. Damit wird Offline später ohne Konfliktauflösung möglich (Haken und Zahlungen widersprechen sich nicht; bei Angaben gilt die zuletzt verarbeitete).
3. **Reihenfolge**: Stufe 1 online → Stufe 2 Unterschriften → Stufe 3 installierbar und offline → Stufe 4 Datenschutz offline.
4. **Zusatzinfos fürs Team** (Zimmer, Zelt, Platz …) als frei benennbare Felder je Veranstaltung; Zusammengehörige ergeben sich aus gleichen Werten.
5. **Kassieren** dürfen alle aus dem Team mit `manageParticipants` – sie nehmen das Geld vor Ort entgegen. Zahlungsart wählbar: Bar, Karte (SumUp), PayPal (je Instanz einstellbar); Kluft vermerkt sie nur, eine Anbindung an SumUp/PayPal ist später möglich.
6. **Unterschrift**: eine je Person und **Veranstaltung** (nicht je Tag); Umsetzung mit signature_pad (Stufe 2).

## Verworfen

- **Native App** (iOS/Android): App-Store, zweite Codebasis, eigene Anmeldung – zu aufwendig für den Nutzen.
- **Offline mit freier Bearbeitung und Konfliktauflösung**: unnötig teuer; Ereignisse reichen.
- **Zusatzinfos als Teil des Anmeldeformulars**: Zimmer und Plätze vergibt das Team, nicht die Teilnehmenden.

## Offen

- Unterschrift: Wer unterschreibt bei Minderjährigen? Vorgegebener Bestätigungstext? Aufbewahrung über die Löschfrist der Anmeldung hinaus (Verwendungsnachweis)?
