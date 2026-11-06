# Designsprache DPSG Diözesanverband Trier

**Stand:** 6. November 2026 · **Quelle:** Website des DV Trier (Kluft), Werte aus `resources/css/app.css`, geprüft gegen das CD der DPSG-Bundesebene (Leitfaden April 2026, Pflichtenheft Abschnitt 7.2).

Diese Datei ist zum Weitergeben an andere Projekte gedacht, z. B. eine eigene Jitsi-Oberfläche. Sie beschreibt Farben, Schriften, Logo und die typische Gestaltung, damit andere Angebote des DV wie die Website aussehen.

---

## 1. Logo

| Datei | Adresse | Größe |
|---|---|---|
| Logo DV Trier (PNG) | https://test.dpsg-trier.de/images/logo-dv-trier.png | 300 × 223 px |
| Logo DV Trier (WebP) | https://test.dpsg-trier.de/images/logo-dv-trier.webp | 300 × 223 px |

- Ab dem Livegang (21./22.11.2026) gilt dieselbe Datei unter `https://www.dpsg-trier.de/images/logo-dv-trier.png`. Für dauerhafte Einbindungen besser eine eigene Kopie verwenden als auf die Website zu verlinken.
- Im Repository: `public/images/logo-dv-trier.png` und `.webp`.
- Das Logo ist die Wort-Bild-Marke der DPSG mit dem Zusatz „Diözesanverband Trier“. Es wird **nur als Grafik** eingebunden, nie nachgebaut oder umgefärbt. Es steht immer auf **weißem Grund**; auf der Website liegt es in einer weißen Karte mit abgerundeten unteren Ecken, die oben aus der blauen Kopfzeile „herunterhängt“.
- Nutzung nur nicht-kommerziell; das Angebot muss als Angebot des DV erkennbar sein (CD-Leitfaden, Abschnitt 5).

## 2. Farben

### Kernfarben

| Name | Hex | Verwendung | Schrift darauf |
|---|---|---|---|
| DPSG-Blau | `#003056` | Primärfarbe: Kopfzeile, Buttons, Überschriften-Akzente | weiß |
| DPSG-Rot | `#810a1a` | Akzent: Links, Hover, Unterstriche, Hinweise | weiß |
| Hellbeige | `#ecdfcb` | Flächen, Fußzeile, Infoboxen | dunkel |
| Mittelbeige | `#c7bdad` | Linien, Rahmen, Trenner | dunkel |
| Magenta | `#e6007e` | sparsamer Akzent | weiß, nur große/fette Schrift |
| Gelb | `#ffed00` | sparsamer Akzent, z. B. kurze Unterstreichung | dunkel |
| Cyan | `#009fe3` | sparsamer Akzent | dunkel |

### Neutralfarben der Website

| Name | Hex | Verwendung |
|---|---|---|
| Text | `#1c1f26` | Fließtext |
| Text sekundär | `#5c6670` | Datum, Hinweise, Bildunterschriften |
| Hintergrund | `#ffffff` | Seiten, Karten |
| Fläche hell | `#e6ebf0` | abgesetzte Bereiche, Eingabefelder |

### Stufenfarben

| Stufe | Hex | Schrift darauf |
|---|---|---|
| Biber | `#815848` (vorläufig, im Leitfaden nicht definiert) | weiß |
| Wölflinge | `#ff6400` | dunkel |
| Jungpfadfinder*innen | `#2f53a7` | weiß |
| Pfadfinder*innen | `#00823c` | weiß |
| Rover | `#cc1f2f` | weiß |
| Leiter*innen | `#b1b9ad` | dunkel |

### Fachbereiche

| Fachbereich | Hex |
|---|---|
| Ökologie | `#118938` |
| Globale Gerechtigkeit | `#b6106f` |
| Inklusion | `#fdc400` (dunkle Schrift) |
| Internationales | `#662483` |
| Spiritualität | `#00739f` |
| Ausbildung | `#003056` |
| Politische Bildung | `#7b2d84` |
| Prävention | `#00646e` |

### Kontrastregeln (WCAG 2.1 AA, 4,5 : 1)

- Wölflinge-Orange, Cyan, Gelb und Leiter-Grau **nie als Schriftfarbe auf Weiß**, nur als Fläche, Rahmen oder Icon mit dunkler Schrift darauf.
- Magenta als Schrift nur groß oder fett.
- Blau und Rot sind auf Weiß als Schrift gut lesbar (13,5 bzw. 10,5 : 1).

## 3. Schriften

| Rolle | Website | Ersatz/Hintergrund |
|---|---|---|
| Fließtext, Menüs, Buttons | **Carlito** 400/700 | metrisch gleich wie Calibri (Hausschrift laut Leitfaden) |
| Überschriften | **Arvo** 400/700 (Serifenschrift) | freie Alternative zu TheSerif, im Leitfaden genannt |

- Beide frei (SIL OFL), auf der Website selbst gehostet über fontsource (`@fontsource/carlito`, `@fontsource/arvo`), **keine Google Fonts per CDN** (Datenschutz).
- CSS-Stapel: `font-family: 'Carlito', Calibri, Arial, sans-serif;` und `font-family: 'Arvo', Georgia, serif;`
- Überschriften fett (700), Fließtext normal, Zeilenhöhe großzügig (~1,6).

## 4. Gestaltung

- **Kopfzeile:** durchgehend DPSG-Blau, weiße Schrift. Das Logo hängt in einer weißen Karte links oben über die Kante.
- **Hauptmenü:** weiße Leiste mit Schatten unter der blauen Kopfzeile, Einträge fett, aktive Einträge in DPSG-Rot.
- **Kopfband einer Seite** (Hero): Blau oder die Farbe von Stufe bzw. Fachbereich, darauf ein feines Punktraster in Weiß (ca. 14 % Deckkraft) und große Arvo-Überschrift in Weiß; darunter ein kurzer gelber Balken (`#ffed00`, ca. 10 × 0,4 rem, abgerundet) als Akzent.
- **Buttons:**
  - Primär: Fläche DPSG-Blau, weiße fette Schrift, **eckig** (kein Radius), Hover wechselt auf DPSG-Rot.
  - Sekundär: Rahmen und Schrift in DPSG-Rot auf Weiß, Hover gefüllt.
- **Karten/Kacheln:** weiß, leichter Schatten, oft mit farbigem Rand oben (4 px in Stufen-/Fachbereichsfarbe); beim Hover heben sie sich leicht an.
- **Links im Text:** DPSG-Rot, unterstrichen.
- **Fußzeile:** Hellbeige mit dunkler Schrift, darunter eine schmale blaue Leiste mit Impressum und Datenschutz.
- **Formen:** überwiegend eckig bzw. nur leicht gerundet; runde Formen nur für Personenbilder, Initialen-Kreise und kleine Abzeichen („Pills“).
- **Stimmung:** ruhig, klar, viel Weißraum; Farbe gezielt über Kopfband, Kartenränder und Akzente, nicht flächig überall.
- **Gestaltungselemente laut CD:** Lilie als Outline (30 % Schwarz) und Wegzeichen dürfen als dezente Layout-Elemente eingesetzt werden.

## 5. Für eine Jitsi-Oberfläche (Vorschlag)

| Jitsi-Element | Wert |
|---|---|
| Wasserzeichen / Logo | `logo-dv-trier.png` (weiß hinterlegt lassen) |
| Kopf- und Werkzeugleiste | DPSG-Blau `#003056`, Icons weiß |
| Primäre Buttons („Beitreten“) | DPSG-Blau, Hover DPSG-Rot `#810a1a`, eckig |
| Akzent (aktive Sprecher*in, Hervorhebungen) | DPSG-Rot oder Gelb `#ffed00` als Rahmen |
| Startseite / Warteraum-Hintergrund | Weiß oder Hellbeige `#ecdfcb` |
| Schrift | Carlito (Text), Arvo (Überschriften), selbst gehostet |
| Fehler / Warnung | DPSG-Rot; nicht Orange oder Gelb als Schrift |

## 6. Ansprechpartner

Bei Zweifeln zu Logo, Typografie oder Farben: Referat Öffentlichkeitsarbeit der DPSG-Bundesebene (online@dpsg.de). Für den DV: Diözesanbüro, info@dpsg-trier.de.
