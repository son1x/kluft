# DPSG Diözesanverband Trier – Homepage-Entwurf

Statischer Gestaltungsentwurf für die Homepage des DPSG Diözesanverbands Trier.
Reines HTML/CSS/JavaScript ohne Build-Schritt – kann direkt gehostet werden
(z. B. GitHub Pages, Netlify oder klassisches Webhosting per FTP/SFTP).

## Hintergrund

Dieser Entwurf übernimmt bewusst die **Farb- und Formensprache** der
Bezirks-Homepages im Diözesanverband (z. B. [DPSG Bezirk Saar-Hochwald](https://github.com/son1x/dpsg-homepage)),
damit Diözesanebene und Bezirke optisch als Familie auftreten. Inhaltlich
orientiert er sich an der Struktur und den echten Inhalten von
[dpsg-trier.de](https://www.dpsg-trier.de) (Navigation, Kontaktdaten,
aktuelle Meldungen, Georgsbrief).

## Struktur

- `index.html` – Startseite: Hero, Stufen, Diözesanverband in Zahlen,
  Aktuelles-Teaser, Georgsbrief-Anmeldung, Bezirke-Teaser, CTA
- `dioezesanverband.html` – Über den Diözesanverband: Struktur, alle Bezirke
  (`#bezirke`), Diözesanleitung
- `aktuelles.html` – Neuigkeiten, Georgsbrief/Georgsbote-Übersicht
- `kontakt.html` – Kontaktseite mit Formular und Diözesanbüro-Adresse
- `impressum.html`, `datenschutz.html` – Rechtliche Pflichtseiten (Platzhalter,
  siehe unten)
- `css/style.css` – gesamtes Styling, per `?v=JJJJMMTT`-Parameter eingebunden
  (Cache-Busting nach Änderungen – Datum bei inhaltlichen CSS-Änderungen auf
  allen Seiten aktualisieren)
- `js/main.js` – mobile Navigation, Baustellen-Hinweis, Fußzeilen-Jahr
- `assets/logo-dv-trier.svg` – **selbst gestaltetes Platzhalter-Logo**
  (Lilie + Schriftzug in den Verbandsfarben) – durch das offizielle Logo des
  Diözesanverbands ersetzen, sobald verfügbar
- `assets/favicon.png` – Favicon, übernommen aus dem Bezirks-Repo (Lilie auf
  cremefarbenem Kreis)
- `assets/mascots/` – offizielle DPSG-Stufenmaskottchen (Biber, Wölfi, Kobold)
  und das offizielle Handlogo der Pfadfinderstufe, Quelle: dpsg.de

## Design

Farben und Schriften sind identisch zu denen der Bezirks-Homepages im
Diözesanverband, die sich ihrerseits an der DPSG-Bundeshomepage orientieren:

| Verwendung | Wert |
|---|---|
| Hauptfarbe (Header, Footer, Überschriften) | Marineblau `#1a305b` |
| Akzent (Buttons, Badges, Labels) | „DPSG Rot" `#810a1a` |
| Alternativer Sektionshintergrund | Creme `#f2ead9` |
| Überschriften-Schrift | „Suez One" (Google Fonts) |
| Fließtext-Schrift | „PT Sans" (Google Fonts) |

Alle Farben sind als CSS-Variablen in `css/style.css` (`:root`) definiert.
Die fünf Stufenfarben (Biber, Wölflinge, Jungpfadfinder*innen,
Pfadfinder*innen, Rover) sind ebenfalls identisch zu den Bezirks-Homepages
übernommen.

## Vor der Veröffentlichung bitte prüfen/ergänzen

Dieser Entwurf wurde mit öffentlich recherchierbaren Basisdaten von
dpsg-trier.de befüllt. Folgende Punkte sind **Platzhalter** und sollten vom
Diözesanvorstand geprüft/ergänzt werden:

1. **Logo** – `assets/logo-dv-trier.svg` ist ein selbst gestaltetes
   Platzhalter-Logo, kein offizielles Verbandslogo. Durch das echte Logo des
   Diözesanverbands ersetzen.
2. **Bezirke-Übersicht** (`index.html`, `dioezesanverband.html#bezirke`) –
   nur der Bezirk Saar-Hochwald ist mit echten Daten befüllt (aus dessen
   eigener Homepage). Die weiteren Bezirke des Diözesanverbands (u. a. im
   rheinland-pfälzischen Teil des Bistums) müssen namentlich ergänzt werden,
   inkl. Gebiet, Kontakt und ggf. Link zur eigenen Bezirks-Homepage.
3. **Diözesanleitung** (`dioezesanverband.html`) – aktuell nur Rollen-Platzhalter
   ohne Namen, da keine gesicherten Namen recherchierbar waren. Mit echten
   Personen (Diözesanvorsitzende*r, Kurat*in, Referent*innen) befüllen –
   analog zur Anleitung "Leitungsteam pflegen" im Bezirks-Repo.
4. **Impressum** (`impressum.html`) – vertretungsberechtigte Person(en) der
   Diözesanleitung eintragen.
5. **Datenschutzerklärung** (`datenschutz.html`) – Hosting-Anbieter,
   Telefonnummer der/des Datenschutzbeauftragten und Details zum
   Georgsbrief-Versand ergänzen; im Zweifel rechtlich prüfen lassen.
6. **Aktuelles** (`aktuelles.html`, Startseite) – die Meldungen sind ein
   Abbild der zuletzt auf dpsg-trier.de/aktuell veröffentlichten Beiträge und
   müssen redaktionell laufend gepflegt werden.
7. **Georgsbrief-Anmeldung** – aktuell verlinkt auf die Aktuelles-Seite von
   dpsg-trier.de; bei Bedarf durch ein eigenes Anmeldeformular ersetzen.
8. **Fotos** – aktuell werden nur einfache SVG-Illustrationen, offizielle
   Stufen-Maskottchen und Initialen-Platzhalter verwendet; eigene Fotos
   können ergänzt werden (Bildrechte und Einverständnis beachten!).

Bestätigte, direkt von dpsg-trier.de übernommene Angaben: Anschrift des
Diözesanbüros (Weberbach 71, 54290 Trier), Telefon (0651 4 629 829 0) und
E-Mail (info@dpsg-trier.de).

## Lokal ansehen

Einfach `index.html` im Browser öffnen, oder z. B. mit:

```bash
python3 -m http.server 8000
```

und dann `http://localhost:8000` aufrufen.
