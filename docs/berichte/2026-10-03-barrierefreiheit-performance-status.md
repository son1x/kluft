# Bericht: Barrierefreiheit, Performance (Lighthouse) und Systemstatus

**Website:** test.dpsg-trier.de · **Stand:** 3. Oktober 2026, 15:54 Uhr · **Grundlage:** Pflichtenheft 7.3 (Barrierefreiheit), 10.2 (Betrieb) und 10.3 (Performance)

## Zusammenfassung

| Bereich | Ergebnis |
|---|---|
| Barrierefreiheit (axe, WCAG 2.2 AA) | **0 Verstöße** auf 12 Seiten, Desktop und Handy |
| Lighthouse Barrierefreiheit | **100** auf allen 5 gemessenen Seiten |
| Lighthouse Best Practices / SEO | **100 / 100** auf allen 5 Seiten |
| Lighthouse Performance (Handy) | **95–100** auf vier Seiten, **70–77** auf der Kartenseite |
| Systemstatus | **Hinweis**: alles läuft, nur die Backups sind noch nicht eingeschaltet |

Gefunden und behoben wurden 4 Barrierefreiheits-Probleme und 4 Performance-Bremsen (Details unten). Alle drei Prüfungen laufen jetzt dauerhaft automatisch.

---

## 1. Barrierefreiheit

### Vorgehen

- **Werkzeug:** axe-core (über Playwright). Geprüft wurden alle Regeln für WCAG 2.0/2.1/2.2, Stufe A und AA.
- **Seiten (12):** Startseite, Aktuelles, Veranstaltungen, Kalender, Bezirke & Stämme, Stufe Wölflinge, Diözesanleitung, Kontakt, Meine Anmeldungen, Georgsbote, Bolivienpartnerschaft, Systemstatus.
  - Bei der ersten Prüfung kamen zwei Einzelseiten dazu: der DiVers-Beitrag und die DiVers-Veranstaltung.
- **Ansichten:** Desktop (1280 px) und Handy (390 px), Consent-Banner bereits beantwortet.
- **Wiederholung:** Workflow „Barrierefreiheit und Performance“, jeden Montag und auf Knopfdruck. Der Lauf wird rot, sobald ein Verstoß auftaucht.

### Gefunden und behoben

| # | Problem | Wo | Schwere | Behebung |
|---|---|---|---|---|
| 1 | Link nur durch Farbe erkennbar (Kontrast zum umgebenden Text 1,8 : 1, nötig 3 : 1) | Brotkrumen auf Unterseiten, z. B. Diözesanleitung | ernst | Link ist jetzt unterstrichen |
| 2 | Links ohne erkennbaren Text | Stufen-Leiste auf Stufenseiten, nur Handy | ernst | Stufenname bleibt für Screenreader lesbar, wird auf dem Handy nur optisch ausgeblendet |
| 3 | Überschriften springen von h1 auf h3 | Veranstaltungsseiten mit Beschreibung | mittel (Lighthouse) | Verborgene Überschrift „Beschreibung“ (h2) für Screenreader |
| 4 | Zu wenig Kontrast (weiße Schrift auf Gelb) | Statusleiste der Systemstatus-Seite bei „Hinweis“ | ernst | Dunkle Schrift auf Gelb; Grün und Rot dunkler. Gefunden vom neuen Workflow im ersten Lauf |

### Ergebnis

- **Letzter Lauf** (3.10.2026, 15:47 Uhr): **keine Verstöße**.

### Grenzen

- Automatische Tests finden erfahrungsgemäß nur rund ein Drittel bis die Hälfte möglicher Barrieren.
- **Noch offen:** eine Prüfung durch Menschen, also Bedienung nur mit Tastatur sowie mit Screenreader (NVDA oder VoiceOver).
- Dabei sollten vor allem diese Stellen geprüft werden:
  - Anmeldeformular
  - Kalender
  - Karte
  - Menü auf dem Handy
- Redaktionelle Inhalte (Alternativtexte, Überschriften-Reihenfolge in Beiträgen) liegen in der Verantwortung der Redaktion.

---

## 2. Performance (Lighthouse)

### Vorgehen

- **Werkzeug:** Lighthouse 12, Handy-Profil (simuliertes langsames 4G, gedrosselte CPU), gegen die Live-Testseite.
- **Wiederholung:** derselbe Workflow, jeden Montag.

### Ergebnisse (Live-Seite)

| Seite | Performance | Barrierefreiheit | Best Practices | SEO |
|---|---|---|---|---|
| Startseite `/` | 94–100 | 100 | 100 | 100 |
| Aktuelles `/aktuelles` | 99–100 | 100 | 100 | 100 |
| Veranstaltungen `/veranstaltungen` | 95 | 100 | 100 | 100 |
| Bezirke & Stämme `/bezirke-staemme` | 70–77 | 100 | 100 | 100 |
| Stufe Wölflinge `/stufe-woelflinge` | 83–95 | 100 | 100 | 100 |

Die Spannen stammen aus zwei Läufen am selben Tag. Performance-Werte schwanken je nach Auslastung des Servers um einige Punkte.

### Maßnahmen

| Maßnahme | Wirkung |
|---|---|
| Logo und Stufen-Maskottchen als WebP, PNG bleibt als Rückfall | Wölflinge 172 → 46 KB, Biber 143 → 23 KB, Logo 70 → 17 KB (bis −85 %) |
| Maskottchen auf der Startseite erst laden, wenn sie ins Bild kommen | weniger Daten beim ersten Aufruf |
| Fehlende Meta-Beschreibung ergänzt (Standardtext für alle Seiten ohne eigene) | SEO auf „Aktuelles“ 92 → 100 |
| Browser-Cache-Regeln (Build-Dateien 1 Jahr, Bilder 1 Woche) in `.htaccess`, in Apache geprüft | greift erst nach Einstellung in Plesk (siehe offene Punkte) |

Schon vorher vorhanden:
- Komprimierung (gzip/brotli) auf dem Server
- WebP-Varianten in mehreren Größen für alle Bilder der Mediathek
- Lazy-Loading

Lokal ohne Komprimierung stieg die Startseite durch die Maßnahmen von 73 auf 90.

### Kartenseite (Bezirke & Stämme)

- **Ursache:** Die Kartenbibliothek MapLibre ist groß (rund 790 KB, komprimiert etwa ein Viertel davon) und muss vor der Karte geladen werden.
- **Kein externer Dienst:** Der Wert ist für eine selbst gehostete, datenschutzfreundliche Karte üblich; es gibt keine Anfragen an Google & Co.
- **Mögliche Verbesserung:** Die Karte erst laden, wenn sie ins Bild scrollt, oder nach einem Klick. Das ist ein Kompromiss bei der Bedienung und gehört deshalb zu den offenen Punkten.

---

## 3. Systemstatus und Monitoring

### Aktueller Stand (live, 3.10.2026, 15:54 Uhr)

Gesamt: **Hinweis** – alles erreichbar, ein Punkt offen.

| Bereich | Zustand | Meldung |
|---|---|---|
| Datenbank | 🟢 In Ordnung | Erreichbar |
| Geplante Aufgaben (Cronjob) | 🟢 In Ordnung | Läuft (letztes Lebenszeichen vor < 1 Min.) |
| E-Mail-Versand | 🟢 In Ordnung | Keine wartenden oder fehlgeschlagenen Mails; Testmail über das Kontaktformular ist angekommen |
| Backups | 🟡 Hinweis | Noch nicht eingeschaltet (Backup-Passwort fehlt) |
| Kalender-Abgleich (Nextcloud) | 🟢 In Ordnung | 1 Kalender aktuell |
| Instagram-Anbindung | ⚪ Nicht eingerichtet | Es fehlt das Instagram-Konto |

**„Nicht eingerichtet“** ist ein eigener, grauer Zustand. Bisher wurde ein nicht eingerichtetes Instagram als „in Ordnung“ angezeigt. Das ist korrigiert, und der graue Zustand zählt weder als in Ordnung noch als Störung.

### Wo und wie

| Was | Adresse / Ort |
|---|---|
| Statusseite für Menschen | https://test.dpsg-trier.de/status (Footer → „Systemstatus“) |
| Maschinenlesbar für Überwachungsdienste | https://test.dpsg-trier.de/api/status (HTTP 200, bei Störung 503) |
| Alarm vom Server | alle 15 Minuten Prüfung; bei Störung Mail an alle Admins, je Störung höchstens alle 6 Stunden, nicht über die Warteschlange |
| Überwachung von außen | GitHub-Workflow „Monitoring“ alle 30 Minuten (Startseite und Status); bei Ausfall wird der Lauf rot und GitHub schickt eine Mail. Das deckt auch einen Ausfall des Servers oder Cronjobs ab. Erster Lauf: grün |
| Fehlgeschlagene Mails im Detail | Backend → „E-Mail-Versand“ |
| Backup-Stand im Detail | GitHub → Actions → „Server-Prüfung“ → `backup:status` |

---

## 4. Offene Punkte

| # | Punkt | Wer | Priorität |
|---|---|---|---|
| 1 | **Backups einschalten** (`BACKUP_PASSWORD` setzen, optional Nextcloud-Kopie). Anleitung steht oben auf /roadmap | Projektleitung | hoch |
| 2 | Prüfung durch Menschen mit Tastatur und Screenreader (Formulare, Kalender, Karte, Menü auf dem Handy) | Projektteam, ggf. Betroffene einbeziehen | mittel |
| 3 | Browser-Cache in Plesk für nginx einstellen (statische Dateien liefert nginx direkt, die `.htaccess`-Regeln greifen dort nicht) | Hosting/Projektleitung | niedrig |
| 4 | Kartenseite: Karte erst beim Hineinscrollen oder auf Klick laden (Performance 70–77 → voraussichtlich > 90) | Entwicklung, nach Entscheidung | niedrig |
| 5 | Instagram-Konto einrichten (dann wird der Status grün) | Projektleitung | nach Bedarf |

## 5. Prüfung wiederholen

- **Barrierefreiheit und Lighthouse:** GitHub → Actions → „Barrierefreiheit und Performance“ → „Run workflow“. Ergebnis als Tabelle in der Zusammenfassung des Laufs.
- **Monitoring:** GitHub → Actions → „Monitoring“ → „Run workflow“.
- **Lokal:** `SITE=https://test.dpsg-trier.de node scripts/web-checks.mjs` (benötigt `playwright`, `@axe-core/playwright`, `lighthouse`, `chrome-launcher`).
