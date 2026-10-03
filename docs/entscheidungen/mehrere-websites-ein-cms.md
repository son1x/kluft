# Ein CMS für mehrere Websites: dpsg-trier.de, bolivienpartnerschaft.de und beraten-online.de

**Stand:** 3. Oktober 2026 · **Status:** entschieden, umgesetzt auf test.dpsg-trier.de (Domains werden bei der Migration umgestellt) · **Grundlage:** Pflichtenheft 4.2 (Multi-Domain), 6.2 (Bolivienpartnerschaft), 6.3 (Beraten-Online)

## Kurzfassung

Die **Bolivienpartnerschaft** und **BeratungsPower & Coaching** behalten ihre eigenen Adressen (`bolivienpartnerschaft.de`, `beraten-online.de`). Sie laufen aber nicht mehr als eigene WordPress-Installationen, sondern im **selben CMS wie dpsg-trier.de**.

Wer eine der Domains aufruft, landet direkt auf der Startseite des jeweiligen Bereichs, mit eigener Navigation, eigener Farbe und eigenem Logo. Gepflegt wird alles an einer Stelle.

**Warum:**
- Alle drei Angebote gehören zum selben Träger, dem DPSG Diözesanverband Trier.
- Sie sind inhaltlich klein.
- Sie brauchen dieselben Grundfunktionen.

Ein gemeinsames System spart Pflege, Sicherheitsrisiken und doppelte Rechtstexte, ohne dass die Angebote ihren eigenen Auftritt verlieren.

---

## 1. Ausgangslage

| | dpsg-trier.de | bolivienpartnerschaft.de | beraten-online.de |
|---|---|---|---|
| Was | Hauptseite des Diözesanverbands | Partnerschaft mit der Pfadfinderschaft in Bolivien (Fachbereich) | Beratung und Coaching für Ehrenamtliche (Serviceangebot) |
| Umfang | groß | Startseite, wenige Unterseiten, Spendenhinweis | 4 Inhaltsseiten, 2 Klappkarten |
| Bisher | WordPress, wird abgelöst | eigene Domain | eigenes WordPress (6.8, Theme von 2020, Datenschutzerklärung von 2020) |
| Verantwortlich | Diözesanverband | Diözesanverband (Fachbereich) | Diözesanverband (Ansprechperson Marc Basenach) |

Alle drei haben **dasselbe Impressum, denselben Träger und dieselben Ansprechpersonen im Diözesanbüro**.

## 2. Wie es funktioniert

- **Eine Installation, mehrere Domains:** Alle Domains zeigen auf dasselbe Webhosting (in Plesk als Alias, jeweils mit eigenem Zertifikat). Die Anwendung erkennt am Domainnamen, welche Startseite sie zeigt:
  - `bolivienpartnerschaft.de` → Startseite der Bolivienpartnerschaft
  - `beraten-online.de` → Startseite von BeratungsPower & Coaching
  - Es gibt keine Weiterleitung; die Adresse im Browser bleibt.
- **Bereiche („Sektionen“):** Jede Zusatz-Website ist ein Bereich mit
  - eigener Startseite,
  - eigener Unterseiten-Navigation,
  - eigener Akzentfarbe und eigenem Logo,
  - eigenen Ansprechpersonen,
  - eigenen Meldungen und eigenem RSS-Feed.
  - Das Grunddesign (DPSG-CD, Kopf, Fuß, Schriften) ist gemeinsam.
- **Im Hauptmenü von dpsg-trier.de** sind die Bereiche normal eingebunden: die Bolivienpartnerschaft unter „Fachbereiche“, BeratungsPower & Coaching als Serviceangebot unter „Diözesanverband“.
- **Backend:** Über den Listen „Seiten“ und „Beiträge“ gibt es Reiter je Website (**Alle | dpsg-trier.de | bolivienpartnerschaft.de | beraten-online.de**) und eine Spalte „Website“. Beim Anlegen wählt man den Bereich; die Domain steht dabei.
- **Rechte:** Wer nur einen Bereich pflegen soll (z. B. das Bolivien-Team oder der Beratungspool), bekommt die Rolle „Sektionsadmin“ für genau diesen Bereich und sieht und bearbeitet nur dessen Inhalte. Anmeldung wie alle über das M365-Konto.
- **Suchmaschinen:** Seiten unter einer Zusatzdomain verweisen per „canonical“ auf die Hauptadresse, z. B. `beraten-online.de/coaching` → `dpsg-trier.de/coaching`. So zählen sie nicht als doppelte Inhalte.
- **Alte Adressen:** Frühere Unterseiten (z. B. `beraten-online.de/coaching/`) funktionieren weiter oder leiten dauerhaft (301) auf die neue Seite um. Gedruckte Flyer und alte Links bleiben gültig.

## 3. Warum das sinnvoll ist

### Ein System statt drei

- **Sicherheit:** Jede WordPress-Installation braucht laufend Updates für Kern, Theme und Plugins. Veraltete Installationen sind das häufigste Einfallstor bei Vereins-Websites. Ein System heißt: einmal aktualisieren, einmal auf Sicherheitslücken prüfen (läuft automatisch wöchentlich).
- **Betrieb:** Backups, Monitoring, Statusseite, Mailversand und Zertifikate gibt es einmal, und sie gelten für alle drei Websites.
- **Kosten und Aufwand:** kein zweites Theme, keine zweite Plugin-Landschaft, kein zweites Hosting. Die Übernahme von beraten-online.de hat etwa einen halben Tag gedauert.

### Recht und Datenschutz an einer Stelle

- **Ein Impressum, eine Datenschutzerklärung, ein Cookie-Banner:** Auf beraten-online.de stand eine Datenschutzerklärung von 2020 und ein englischsprachiges Cookie-Banner. Solche veralteten Texte sind ein echtes Risiko (Abmahnung, Aufsicht nach KDG).
- **Ein Verzeichnis der Verarbeitungstätigkeiten, ein Auftragsverarbeitungsvertrag** mit dem Mail-Dienstleister, **ein Löschkonzept**.
- **Datenschutz-Anfragen** (Auskunft und Löschung) erfassen automatisch alle drei Websites.

### Alle Funktionen für alle Bereiche

Ohne Mehraufwand können auch die Zusatz-Websites nutzen:

- **Kontaktformular** ohne Speicherung der Nachrichten; Anfragen zu BeratungsPower & Coaching gehen an `beratung@dpsg-trier.de`, vertraulich und nur per Mail.
- **Veranstaltungen mit Online-Anmeldung**, z. B. für Ausbildungs- und Schulungstermine der Berater*innen (Pflichtenheft F-BER-03), inklusive „Meine Anmeldungen“, Rechnungen und Bescheinigungen.
- **Meldungen** mit RSS-Feed, Kalender, Newsletter-Hinweis, Begriffserklärungen.
- **Zwei-Klick-Einbettungen** für Videos und Karten.
- **Geprüfte Barrierefreiheit und Performance:** Die automatischen Prüfungen laufen für alle Seiten gleich.

### Eigener Auftritt bleibt

- **Die Domain bleibt:** Sie steht auf Klappkarten, Präsentationen und in alten Links. Wer `beraten-online.de` eintippt, landet wie gewohnt auf BeratungsPower & Coaching.
- **Eigene Startseite, Farbe, Logo und Navigation:** Der Bereich wirkt als eigenständiges Angebot, aber erkennbar als Teil der DPSG Trier. Das stärkt beide Seiten.

### Sichtbarkeit und Vernetzung

- Meldungen der Bereiche erscheinen auch unter „Aktuelles“ auf dpsg-trier.de; Besucher*innen der Hauptseite finden die Angebote über das Menü.
- Ansprechpersonen werden einmal gepflegt und erscheinen überall richtig.

## 4. Was dagegen spricht – und wie wir damit umgehen

| # | Einwand / Risiko | Bewertung | Umgang |
|---|---|---|---|
| 1 | **Weniger gestalterische Freiheit:** Alle Bereiche teilen das DPSG-Grunddesign | mittel | Eigene Farbe, eigenes Logo, eigene Navigation und Startseite sind möglich. Ein komplett eigenes Design ließe sich nachrüsten (Pflichtenheft F-BER-01), ist aber für wenige Seiten unverhältnismäßig |
| 2 | **Gemeinsamer Ausfall:** Fällt das System aus, sind alle drei Websites betroffen | mittel | Gleiches Hosting wie bisher; dafür Monitoring von innen und außen (alle 15 bzw. 30 Minuten), Statusseite, tägliche verschlüsselte Backups mit Wiederherstellungsprobe |
| 3 | **Abhängigkeit vom Diözesanverband:** Bolivien-Team und Beratungspool können nicht völlig unabhängig arbeiten, etwa eigene Plugins einbauen | gering | Eigene Bearbeitungsrechte (Sektionsadmin) für ihren Bereich; Funktionswünsche gehen an alle, statt nur einer Seite zu nutzen |
| 4 | **Wahrnehmung der Eigenständigkeit:** Die Partnerschaft (mit Partnern in Bolivien) oder das vertrauliche Beratungsangebot sollen vielleicht nicht „nur Unterseite des DV“ sein | gering bis mittel | Eigene Domain und Startseite; Kontaktanfragen an Beratung gehen an ein eigenes Postfach, nicht ans allgemeine Büro |
| 5 | **Suchmaschinen:** Inhalte sind unter mehreren Domains erreichbar; über „canonical“ zeigen Suchmaschinen eher `dpsg-trier.de/…` als `beraten-online.de` an | gering | Bewusste Entscheidung gegen doppelte Inhalte. Die Startseite unter der eigenen Domain bleibt erreichbar. Soll eine Zusatzdomain selbst in Suchergebnissen erscheinen, lässt sich der Verweis je Bereich umdrehen |
| 6 | **Unterseiten der Hauptseite** sind technisch auch unter den Zusatzdomains erreichbar (z. B. `beraten-online.de/kalender`) | gering | Durch den Verweis auf die Hauptadresse unschädlich; bei Bedarf kann man das pro Domain einschränken |
| 7 | **Je Domain eigener Aufwand beim Hosting:** DNS, Alias in Plesk, Zertifikat | gering | Einmalig bei der Migration; Zertifikate erneuert Plesk automatisch |
| 8 | **Sensible Daten in Zukunft:** Sollte BeratungsPower & Coaching einmal Ratsuchende und Berater*innen im System zuordnen (Pflichtenheft F-BER-04), lägen Beratungsdaten im selben System wie die öffentliche Website | hoch, aber noch nicht aktuell | Bewusst nicht umgesetzt. Vorher Datenschutz-Folgenabschätzung; dann ggf. ein getrenntes System oder eine getrennte Datenbank |
| 9 | **Absender der Mails:** Bestätigungen kommen von `@dpsg-trier.de`, nicht von der Zusatzdomain | gering | Passt zum gemeinsamen Träger; bei Bedarf eigene Absenderadresse je Bereich möglich (dann Domain bei Brevo authentifizieren) |

## 5. Wann wir neu entscheiden sollten

Ein eigenes System für einen Bereich wäre sinnvoll, wenn:
- der Bereich einen **anderen Träger** bekommt, z. B. einen eigenen Verein mit eigenem Impressum;
- er **grundlegend andere Funktionen** braucht, die für die übrigen Websites nicht passen;
- dort **besonders schützenswerte Daten** verarbeitet werden sollen (siehe Punkt 8);
- ein **komplett eigenes Design** gewünscht und finanziert ist.

Bis dahin ist ein gemeinsames CMS die einfachere, sicherere und günstigere Lösung.

## 6. Stand und nächste Schritte

| Schritt | Stand |
|---|---|
| Bolivienpartnerschaft als Bereich mit eigener Startseite, Domain-Erkennung | erledigt |
| BeratungsPower & Coaching als Serviceangebot unter „Diözesanverband“, Inhalte, Grafiken, Cartoons und Klappkarten übernommen | erledigt |
| Kontaktthema „Beratung & Coaching“ an `beratung@dpsg-trier.de` | eingerichtet; **Postfach muss noch angelegt werden** |
| Backend: Seiten und Beiträge je Website auffindbar | erledigt |
| Gruppenfoto der Berater*innen | offen, nur mit Einwilligung der Abgebildeten |
| DNS und Plesk-Alias für beide Domains, Zertifikate | bei der Migration |
| Alte WordPress-Installation von beraten-online.de abschalten | nach der DNS-Umstellung |
