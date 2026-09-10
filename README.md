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
- `assets/logo-dv-trier.png` – **offizielles Logo** des Diözesanverbands
  (Flamme/Pfingstfeuer mit DPSG-Kreuz, Schriftzug „Diözesanverband Trier" und
  „dpsg"), übernommen von dpsg-trier.de (Theme-Asset, 300×223px, keine
  höhere Auflösung auf der Originalseite verfügbar)
- `assets/favicon.png` – aus dem echten Logo zugeschnittenes Icon (nur die
  Flamme, ohne Schriftzug)
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

1. **Bezirke-Übersicht** (`index.html`, `dioezesanverband.html#bezirke`) –
   alle 10 Bezirke sind namentlich gelistet (bestätigt über die
   Termine-Struktur von dpsg-trier.de sowie die Angabe „10 Bezirken" auf
   dessen „Wir über uns"-Seite), nur Saar-Hochwald ist mit echten Kontakt-
   /Homepage-Daten befüllt. Für die übrigen 9 Bezirke fehlen noch Stämme,
   Kontaktdaten und Homepage-Links.
2. **Stämme im Diözesanverband** – laut dpsg-trier.de gibt es „über 60
   Stämme und Siedlungen", mit einer eigenen Seite
   (dpsg-trier.de/downloads/staemme-im-dioezesanverband/). Diese Liste
   liegt dort als interaktive Karte (Plugin „WP Google Maps") vor, deren
   Markerdaten sich nicht öffentlich auslesen ließen (die REST-Schnittstelle
   liefert nur einen Platzhalter-Marker zurück). Eine einzelne, namentliche
   Stämme-Liste je Bezirk ist auf dieser Seite daher **noch nicht enthalten**
   – ein Datenbank-Export der Original-Homepage (z. B. der `wp_wpgmza_maps`-
   Tabelle) würde das direkt lösen.
3. **Diözesanleitung** (`dioezesanverband.html`) – mit den echten, öffentlich
   auf dpsg-trier.de genannten Personen und Rollen befüllt (Stand siehe
   Commit-Historie). E-Mail-Adressen, die dort nicht einzeln aufgeführt
   waren, verweisen auf die allgemeine Diözesanbüro-Adresse.
4. **Impressum** (`impressum.html`) – vertretungsberechtigte Person(en) der
   Diözesanleitung eintragen.
5. **Datenschutzerklärung** (`datenschutz.html`) – Hosting-Anbieter,
   Telefonnummer der/des Datenschutzbeauftragten und Details zum
   Georgsbrief-Versand ergänzen; im Zweifel rechtlich prüfen lassen.
6. **Aktuelles** (`aktuelles.html`, Startseite) – enthält alle 24 seit Januar
   2025 auf dpsg-trier.de/aktuell veröffentlichten Beiträge (Georgsbrief,
   Georgsbote, Einzelmeldungen), jeweils mit Kurzbeschreibung und
   „Weiterlesen"-Link zum Originalbeitrag. Muss redaktionell laufend gepflegt
   werden; der Eintrag **„Nachruf Monika Groß"** konnte inhaltlich nicht
   automatisiert ausgelesen werden (Bildformat) und sollte vor Veröffentlichung
   geprüft/vervollständigt werden.
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

## Deployment

Die Seite wird automatisch per **GitHub Actions** per SFTP hochgeladen
(`.github/workflows/deploy.yml`) – bei jedem Push auf den Branch
`claude/dpsg-trier-homepage-design-jwdgs4`, oder manuell über den
**Actions**-Tab (Workflow „Deploy auf Netcup-Webspace" → **Run workflow**).

Voraussetzung sind die Repository-Secrets `FTP_SERVER`, `FTP_USERNAME`,
`FTP_PASSWORD` und `FTP_SERVER_DIR` unter **Settings → Secrets and
variables → Actions** (siehe Kommentar im Workflow für Details – dieselbe
Namenskonvention wie im [Bezirk-Repo](https://github.com/son1x/dpsg-homepage)).
