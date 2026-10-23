# Pflichtenheft: Umfragen

**Stand:** 23. Oktober 2026 · **Status:** 2. Fassung, Entscheidungen in Abschnitt 8, Phase 1 umgesetzt (`docs/umfragen.md`) · **Bezug:** Kluft (`docs/pflichtenheft.md`), Meilenstein „Umfragen“ in der Roadmap

Umfragen werden im Backend angelegt, kopiert und aus Vorlagen erstellt. Teilnehmen kann man über einen Link auf der Website. Die Auswertung zeigt Diagramme. Bisher laufen solche Umfragen über Nextcloud Forms (cloud.dpsg-trier.de).

Anforderungen tragen IDs der Form `UM-<Nr>`. **MUSS** = Pflicht, **SOLL** = Pflicht außer mit begründeter Ausnahme, **KANN** = optional.

---

## 1. Ausgangslage: vier Beispiele aus Nextcloud Forms

Gesichtet am 23.10.2026, nur der Aufbau, keine Antworten.

| Umfrage | Zweck | Fragen | Typen | Besonderheiten |
|---|---|---:|---|---|
| Rover-Korsika-Fahrt 2026 | Feedback nach einer Fahrt | 9 | Skala 1–5, kurzer Text | Laut Einleitung anonym; je Skala eine offene Frage dazu |
| Feedback DiVers 2025 | Feedback nach der Diözesanversammlung | 12 | Auswahl „sehr gut … gar nicht gut“, langer Text | Auswahl mit „nicht teilgenommen“; je Programmpunkt Bewertung plus Freitext |
| Leiter*innenumfrage 2026 | Befragung aller Leitenden im DV | 29 | Skala 1–5, Mehrfachauswahl, Ja/Nein, kurzer Text | Nach fast jeder Frage „Hier hast du Platz für eigene Gedanken …“; Folgefrage nur sinnvoll bei bestimmter Antwort (Lagerort nur, wer ein Lager möchte); Gewinnspiel mit Name und E-Mail in derselben Umfrage |
| Infos für Prisma | Nachträgliche Infos von Teilnehmenden einer Veranstaltung | 9 | Einfachauswahl, kurzer und langer Text | Personenbezogen (Name, E-Mail, T-Shirt-Größe, Pfadi-Name); Folgefrage nur bei „DV-Bus“ |

**Was daraus folgt:**

1. **Fragetypen:** Es reichen wenige, siehe Abschnitt 3. Datum, Datei-Upload und Raster kommen in keiner der Umfragen vor.
2. **Bewertung plus Kommentar** ist das häufigste Muster. Statt jedes Mal eine eigene Freitextfrage anzulegen, bekommt eine Frage ein optionales Kommentarfeld. Das halbiert die Leiter*innenumfrage von 29 auf etwa 16 Fragen.
3. **Bewertungen kommen in zwei Formen vor:** als Skala 1–5 und als Auswahl „sehr gut / gut / nicht so gut / gar nicht gut“. Beide sollen sich als Diagramm und mit Mittelwert auswerten lassen. Die Auswahl braucht dafür eine Reihenfolge und optional „trifft nicht zu“, das nicht in den Mittelwert eingeht.
4. **Bedingte Fragen:** Folgefragen erscheinen nur bei einer bestimmten Antwort (DV-Bus → Sammelort; Lager ja → Ort).
5. **Anonymität und Gewinnspiel:** Steht ein Gewinnspiel mit Name und E-Mail in derselben Umfrage, ist sie nicht mehr anonym. Die Kontaktdaten müssen getrennt von den Antworten gespeichert werden.
6. **Nachfrage an Teilnehmende:** „Infos für Prisma“ ist eigentlich eine Nachfrage an die Angemeldeten einer Veranstaltung. Sie gehört an die Veranstaltung, mit Link an alle Teilnehmenden und ohne erneute Eingabe von Name und E-Mail. Dasselbe gilt für Feedback nach einer Veranstaltung.

## 2. Ziel und Abgrenzung

- **Ziel:** Umfragen ohne externes Werkzeug im eigenen Backend, mit Vorlagen, Kopie und Auswertung, anonym als Standard (KDG).
- **Nicht Ziel:**
  - kein Abstimmungstool für Wahlen (geheime Wahl mit Stimmberechtigung);
  - keine Terminfindung im Stil von Doodle (vorerst);
  - keine Quiz-Funktion.

## 3. Fragen

| ID | Anforderung | Prio |
|---|---|:-:|
| UM-01 | Fragetypen: **Einfachauswahl**, **Mehrfachauswahl** (optional mit Mindest- und Höchstzahl), **Skala** (z. B. 1–5 mit Beschriftung der Enden), **Ja/Nein**, **kurzer Text**, **langer Text** | MUSS |
| UM-02 | Je Frage: Pflicht ja/nein, Hilfetext, bei Auswahl „Sonstiges: ___“ zulassen | MUSS |
| UM-03 | Optionales **Kommentarfeld** an jeder Frage („Platz für eigene Gedanken“) | MUSS |
| UM-04 | **Bewertungsauswahl** mit Reihenfolge (z. B. sehr gut … gar nicht gut) und „trifft nicht zu“, auswertbar wie eine Skala | SOLL |
| UM-05 | **Bedingte Fragen:** eine Frage nur zeigen, wenn bei einer früheren Frage bestimmte Antworten gewählt sind | SOLL |
| UM-06 | Abschnitte mit Überschrift und Text (z. B. „Angebote des DV“), Reihenfolge per Ziehen | SOLL |
| UM-07 | Einleitungstext und Dankestext (nach dem Absenden), mit Formatierung | MUSS |

## 4. Organisation im Backend

| ID | Anforderung | Prio |
|---|---|:-:|
| UM-10 | Umfragen anlegen, bearbeiten, **kopieren** (mit Fragen, ohne Antworten), archivieren und löschen | MUSS |
| UM-11 | **Vorlagen:** Umfrage als Vorlage speichern, neue Umfrage aus Vorlage. Mitgeliefert: „Feedback zur Veranstaltung“, „Feedback zur Fahrt/zum Lager“, „Leiter*innenbefragung“ (nach den Beispielen oben) | MUSS |
| UM-12 | Status: Entwurf → offen (optional mit Zeitraum von–bis) → geschlossen; Vorschau vor dem Öffnen | MUSS |
| UM-13 | Fragen sind gesperrt, sobald Antworten vorliegen; nur Texte bleiben änderbar | MUSS |
| UM-14 | Rechte: anlegen dürfen Verwaltung, Veranstaltungsleitung (für ihre Veranstaltung) und Stufen- bzw. Sektionsadmins (für ihren Bereich). Ergebnisse sieht, wer die Umfrage verantwortet, dazu Verwaltung und Admins | MUSS |
| UM-15 | Liste mit Filter (offen/geschlossen, Bereich), Teilnahmezahl je Umfrage | MUSS |

## 5. Teilnahme

| ID | Anforderung | Prio |
|---|---|:-:|
| UM-20 | Teilnahme über einen Link (`/umfrage/<code>`), barrierefrei, mobil, ohne JavaScript-Zwang (bedingte Fragen mit Alpine, ohne JavaScript alle Fragen sichtbar) | MUSS |
| UM-21 | Zugriff: öffentlich per Link (Standard) oder nur mit **Zugangscode**. Später nur für angemeldete Teilnehmende einer Veranstaltung (UM-40) | MUSS |
| UM-22 | Mehrfachteilnahme verhindern **ohne Personenbezug** (Zufallsmerkmal im Browser, kein Login, keine IP). Bei offenen Umfragen bewusst kein harter Schutz | SOLL |
| UM-23 | Spamschutz wie beim Kontaktformular (Honeypot, Zeitfalle, Begrenzung) | MUSS |
| UM-24 | Optional: Antworten nachträglich ändern über einen geheimen Link, der nach dem Absenden angezeigt wird | KANN |

## 6. Auswertung

| ID | Anforderung | Prio |
|---|---|:-:|
| UM-30 | Je Frage ein **Diagramm**: Balken für Auswahl und Skala, Anzahl und Prozent; Skala und Bewertungsauswahl mit Mittelwert und Verteilung | MUSS |
| UM-31 | Freitexte und Kommentare als Liste, durchsuchbar | MUSS |
| UM-32 | Teilnahme im Zeitverlauf, Gesamtzahl, Abbruch nicht erfasst | SOLL |
| UM-33 | **Export** als Excel (eine Zeile je Antwort) und Druck-/PDF-Ansicht mit Diagrammen für Gremien und Berichte | MUSS |
| UM-34 | Filter in der Auswertung nach einer Antwort (z. B. nur „möchte ein Lager“) | KANN |
| UM-35 | Diagramme lokal erzeugt, ohne externe Dienste (CSP-konform, mit Vite gebündelt) | MUSS |

## 7. Datenschutz (KDG)

| ID | Anforderung | Prio |
|---|---|:-:|
| UM-50 | **Standard anonym:** keine Namen, E-Mail- oder IP-Adressen und keine Uhrzeit mit Sekunden an den Antworten; Zeitpunkt nur auf den Tag genau | MUSS |
| UM-51 | **Gewinnspiel/Kontakt getrennt:** Kontaktdaten in einer eigenen Tabelle, nicht mit den Antworten verknüpfbar. Löschung nach der Auslosung (Frist einstellbar) | MUSS |
| UM-52 | Personenbezogene Umfragen (Namensfeld, Nachfrage an Teilnehmende) nur mit Hinweis vor dem Absenden und Löschfrist; in die Datenschutzauskunft aufnehmen | MUSS |
| UM-53 | Ergebnisse unter 5 Antworten je Auswahl in der Auswertung zusammenfassen (keine Rückschlüsse auf Einzelne), außer bei personenbezogenen Umfragen | SOLL |
| UM-54 | Antworten werden mit der Umfrage gelöscht; Standardfrist 12 Monate nach Schließen, einstellbar | MUSS |

## 8. Entscheidungen (23.10.2026)

1. **Umfang:** Beides sofort: anonyme Umfragen und die Nachfrage an Teilnehmende einer Veranstaltung (UM-40 vorgezogen). Gebraucht werden eine zusammenfassende Auswertung aller Antworten und das Lesen aller Antworten einer einzelnen Person.
2. **Bedingte Fragen (UM-05):** sofort.
3. **Anlegen:** alle ab Diözesanleitung (Stufen- und Fachbereichsleitungen, Vorstand, Verwaltung, Admins).
4. **Gewinnspiel:** als eigener Baustein (UM-51).
5. **Nextcloud:** Alte Umfragen und Ergebnisse bleiben dort; die Beispiele gibt es als mitgelieferte Vorlagen.
6. **Export:** PDF im Format DIN A4 und Excel.

UM-53 (kleine Gruppen zusammenfassen) entfällt, weil Einzelantworten ausdrücklich lesbar sein sollen.

## 9. Später

| ID | Anforderung | Prio |
|---|---|:-:|
| UM-40 | **Umfrage an eine Veranstaltung hängen:** Link per Mail an alle bestätigten Teilnehmenden (z. B. Feedback am Tag nach dem Ende), Nachfrage ohne erneute Eingabe von Name und E-Mail | KANN |
| UM-41 | Erinnerung an noch nicht Teilnehmende (nur bei UM-40) | KANN |
| UM-42 | Vergleich zweier Durchgänge (z. B. Leiter*innenumfrage 2026 und 2028) | KANN |
