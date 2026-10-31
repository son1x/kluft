# Diözesanlager: Archiv vergangener Lager

**Stand:** 31. Oktober 2026 · **Bezug:** Meilenstein „Diözesanlager“ in der Roadmap

Unter **/dioezesanlager** (Menü „Diözesanverband“) stehen alle Diözesanlager, das neueste zuerst. Jedes Lager ist ein eigener Bereich mit eigener Startseite, Unterseiten, Beiträgen und **eigenem Aussehen** im Inhaltsbereich. Kopf, Hauptmenü und Fuß bleiben im DPSG-Design. Zurückliegende Lager tragen den Hinweis „Archiv“.

Erstes Lager im Archiv: **Scoutergy, FroLeiLa 2023** (aus der WordPress-Sicherung von scoutergy.de, Repo `son1x/wp-scoutergy`).

---

## 1. Checkliste: Was wir je Lager brauchen

Das Material liegt meist verstreut vor (alte Website, Georgsbote, Fotos, Programmhefte, Lagerzeitungen). Je Lager einen Ordner anlegen und so viel wie möglich davon sammeln. Fehlt etwas, ist das kein Hindernis: Für die Lagerseite reichen schon die Eckdaten und das Logo.

### Pflicht (sonst keine Lagerseite)

- [ ] **Name und Motto** des Lagers (z. B. „Scoutergy – Energie ohne Ende“)
- [ ] **Jahr, Zeitraum** (von–bis, ggf. „über Fronleichnam“/„Pfingsten“)
- [ ] **Ort** (Lagerplatz, Ortschaft)
- [ ] **Logo** in möglichst großer Auflösung (PNG mit transparentem Hintergrund, SVG oder PDF), dazu ein Satz, was darauf zu sehen ist (für den Alternativtext)

### Für das Aussehen

- [ ] **Lagerfarben:** Farbe der Lager-T-Shirts oder -Halstücher, Farben aus dem Logo
- [ ] Foto von **T-Shirt, Aufnäher, Halstuch** oder Banner (zeigt Farben und Stil)
- [ ] **Schrift** aus Plakat oder Programmheft, falls bekannt (sonst wählen wir eine passende freie Schrift)
- [ ] Alte **Website** (Adresse, Sicherung oder Screenshots über web.archive.org)
- [ ] **Maskottchen** oder Figur (Bild und Name)

### Inhalte

- [ ] **Kurzer Rückblick** (3–6 Sätze): Was war das Lager, wer war dabei, was war besonders?
- [ ] **Teilnehmendenzahl**, Zahl der Stämme und Bezirke (wenn bekannt)
- [ ] **Programm:** Tagesplan, Programmpunkte, Stufenprogramme, Abendprogramm
- [ ] **Geschichte oder Rahmenhandlung** (z. B. „Königreich Grüntal“), Lagerlied mit Text
- [ ] **Lagerzeitung, Georgsbote-Artikel**, Berichte in der Presse (mit Datum)
- [ ] **Häufige Fragen** oder Infobriefe an die Stämme (zeigen, wie das Lager organisiert war)
- [ ] **Team:** nur die Aufteilung (Lagerleitung, Küche, Programm …), **keine Namen** ohne Einwilligung
- [ ] **Video** (Teaser, Aftermovie) als Datei, nicht nur als YouTube-Link

### Fotos (mit Bedacht)

- [ ] Fotos **ohne erkennbare Personen** bevorzugen: Lagerplatz, Zeltstadt, Bauten, Banner, Drohnenbilder, Gegenstände
- [ ] Bei Gruppenfotos mit erkennbaren Personen: Gab es damals eine Einwilligung zur Veröffentlichung? Wenn unklar, **weglassen**
- [ ] **Bildrechte:** wer hat fotografiert, darf der DV das Bild zeigen? (Name für die Bildunterschrift)

### Nicht übernehmen

- Anmeldeformulare, Teilnehmendenlisten, Kontaktdaten, Lagerausweise
- Impressum und Datenschutzerklärung der alten Seite (es gelten die des DV)
- E-Mail-Adressen der alten Lager-Domain (gibt es meist nicht mehr)
- Platzhaltertexte aus Website-Vorlagen, interne Planungsseiten

**Abgabe:** Ordner (z. B. Nextcloud oder ein Repo wie `wp-scoutergy`) mit einer kurzen Liste: Datei – was ist darauf – wer hat die Rechte.

---

## 2. So kommt ein Lager in Kluft

1. **Bereich anlegen** in `instanzen/dv-trier/config.php` unter `sections`:

   ```php
   'lagername' => [
       'label' => 'Lagername',
       'kind' => 'Diözesanlager',     // macht ihn zum Lager
       'landing' => 'lagername',       // Slug der Startseite
       'theme' => 'lagername',         // CSS-Klasse .lager-lagername
       'archiv' => true,               // Hinweis „Archiv“ (weglassen bei kommenden Lagern)
       'jahr' => 2019,
       'titel' => 'Pfingstlager des DV Trier 2019',
       'zeitraum' => '7.–10. Juni 2019',
       'ort' => 'Zeltplatz …',
       'motto' => '…',
       'laufband' => ['…', '…'],       // optional, Band unten auf der Startseite
       'logo' => 'instanz/images/lagername/logo',   // ohne Endung, .webp und .png
       'logo_alt' => '…',
       'logo_size' => [480, 665],
       'video' => 'instanz/images/lagername/video.mp4',          // optional
       'video_poster' => 'instanz/images/lagername/poster.jpg',  // optional
       'domains' => [],                // eigene Domain, siehe Abschnitt 4
   ],
   ```

   Logo und Video liegen in `instanzen/dv-trier/public/images/<lager>/`. Videos vorher verkleinern (720p, z. B. `ffmpeg -i in.mp4 -vf scale=-2:720 -c:v libx264 -crf 27 -preset slow -c:a aac -b:a 96k -movflags +faststart out.mp4`).

2. **Aussehen:** in `resources/css/app.css` einen Block `.lager-<theme>` mit den Werten anlegen (Schrift, Text- und Überschriftenfarbe, Akzent und Schrift darauf mit mindestens 4,5 : 1 Kontrast, Rahmen, Schatten, Radius, Hintergrund `--lager-wolken`). Vorlage ist `.lager-scoutergy`. Neue Schriften über fontsource einbinden, nie über Google Fonts.

3. **Inhalte:** Seiten (`section` = Bereich) und Beiträge (`section`, `scope` = `section`, altes Datum als `published_at`) im Backend anlegen oder, bei vielen Inhalten, als Inhalts-Migration in `instanzen/dv-trier/migrations` (Vorbild: `2026_10_31_100000_dioezesanlager_scoutergy.php` mit `dateien/scoutergy/inhalt.json`, erzeugt von `dateien/scoutergy/konverter.py`).

4. **Prüfen:** Startseite, Unterseiten, Beitrag, /dioezesanlager, Handyansicht; keine Namen, Adressen oder Fotos ohne Einwilligung.

Die Lagerseite zeigt automatisch: Kopfbereich mit Eckdaten und Logo, Lager-Navigation, Archivhinweis, Video, Startseitentext, Unterseiten als Karten, Beiträge („Aus der Lagerzeit“) und das Laufband.

---

## 3. Scoutergy 2023: was übernommen wurde

| Alt (scoutergy.de) | Neu | Hinweis |
|---|---|---|
| Startseite (Hero, Teaser-Video) | `/scoutergy` | Video auf 720p verkleinert (14 MB statt 174 MB); englische Platzhaltertexte der Vorlage entfallen |
| `/programm/` | `/scoutergy-programm` | Tagesplan; Slider ersetzt durch die zehn Programmkacheln als Galerie; Name des Kuraten entfernt |
| `/faq/` (34 Einträge) | `/scoutergy-faq` | In drei Gruppen ohne Dopplungen; Kontaktformular-Code, Mailadressen und Link zur Helferlein-Anmeldung entfernt, eine kaputte Antwort (Anreise) sprachlich repariert |
| `/team/` | `/scoutergy-team` | Nur die Aufgaben, ohne Namen und Mailadressen |
| Beitrag „Danke!“ (20.11.2021) | `/aktuelles/scoutergy-go-fuer-das-dioezesanlager` | |
| „Der Georgsbote ist raus!“ (18.12.2021) | `/aktuelles/scoutergy-georgsbote` | |
| „Wo führt das FroLeiLa uns hin?“ (1.1.2022) | `/aktuelles/scoutergy-teaser` | Video |
| „29 – Rollenspiel“ (8.5.2023) | `/aktuelles/scoutergy-rollenspiel` | mit Charakterblatt (PDF) |
| „Workshopzeit“ (8.5.2023) | `/aktuelles/scoutergy-workshopzeit` | |
| „Rover Hajk Glas“ (7.6.2023) | `/aktuelles/scoutergy-rover-hajk-glas` | |

**Nicht übernommen:** Impressum, Datenschutzerklärung, „Anmeldung als Helferlein“, Beispielseite „Willkommen“, Entwürfe, 51 private Programm-Gerüste, Programmpunkt-Beiträge, die nur aus der Kurzinfo-Tabelle bestanden (Stufenzeiten, BobbyCar, Kapelle, Abendessen, Campfire, Morgenrunde), das Foto der Arena (viele erkennbare Personen, vor dem Lager hochgeladen).

---

## 4. Später: Domain scoutergy.de

1. DNS von scoutergy.de und www.scoutergy.de auf den Kluft-Webspace zeigen lassen (wie bolivienpartnerschaft.de) und das Zertifikat einrichten.
2. In der Konfiguration `'domains' => ['scoutergy.de', 'www.scoutergy.de']` eintragen und deployen. Dann zeigt scoutergy.de die Lagerseite direkt, mit Verweis (canonical) auf www.dpsg-trier.de/scoutergy.
3. Alte Adressen weiterleiten (301):

   | Alte Adresse | Ziel |
   |---|---|
   | `/programm/` | `/scoutergy-programm` |
   | `/faq/` | `/scoutergy-faq` |
   | `/team/` | `/scoutergy-team` |
   | `/anmeldung-als-helferlein/`, `/impressum/`, `/datenschutzerklaerung/` | `/scoutergy` bzw. Impressum/Datenschutz des DV |
   | `/allgemein/hallo-welt/` | `/aktuelles/scoutergy-go-fuer-das-dioezesanlager` |
   | `/allgemein/der-georgsbote-ist-raus/` | `/aktuelles/scoutergy-georgsbote` |
   | `/allgemein/wo-fuehrt-das-froleila-uns-hin/` | `/aktuelles/scoutergy-teaser` |
   | `/programm/29-rollenspiel/` | `/aktuelles/scoutergy-rollenspiel` |
   | `/programm/69-workshopzeit/` | `/aktuelles/scoutergy-workshopzeit` |
   | `/programm/rover-hajk-glas/` | `/aktuelles/scoutergy-rover-hajk-glas` |
   | `/wp-content/uploads/2022/01/Teaser-Scoutergy.mp4` | `/instanz/images/scoutergy/teaser-720.mp4` |
   | alles andere | `/scoutergy` |

   Die Weiterleitungen werden beim Verbinden der Domain eingerichtet (Permalinks der alten Seite: `/%category%/%postname%/`).
