# WhatsApp-Newsletter: kostenlos oder bezahlt?

**Stand:** 4. Oktober 2026 · **Status:** Entscheidung offen · **Bezug:** docs/whatsapp_newsletter.md (Cloud-API-Modul, gebaut, noch nicht verbunden)

## Kurzfassung

| | **A: WhatsApp-Kanal** (kostenlos) | **B: Cloud API** (bezahlt, schon gebaut) |
|---|---|---|
| Laufende Kosten bei 50 Empfänger:innen | 0 € | ca. **13 € im Monat** (2 Aussendungen) bis **26 €** (4 Aussendungen) |
| Laufende Kosten bei 100 Empfänger:innen | 0 € | ca. **26 € im Monat** (2 Aussendungen) bis **52 €** (4 Aussendungen) |
| Versand | von Hand in den Kanal posten | aus dem CMS, mit Freigabe |
| Datenschutz | sehr gut: der Verband kennt **keine** Nummern | gut: Nummern verschlüsselt im CMS, Einwilligung nachweisbar |
| Erreichbarkeit | Kanal ist bei Folgenden standardmäßig **stummgeschaltet** | Nachricht kommt wie ein normaler Chat mit Benachrichtigung |

**Empfehlung:** Bei 50 bis 100 Empfänger:innen und höchstens vier Nachrichten im Monat mit dem **WhatsApp-Kanal** starten. Das Cloud-API-Modul bleibt im CMS und kann jederzeit eingeschaltet werden, wenn wir persönliche Nachrichten, Freigaben im CMS oder garantierte Benachrichtigungen brauchen.

Nicht in Frage kommen **WhatsApp Web automatisiert oder inoffizielle CLI-Werkzeuge** (siehe Abschnitt 5).

---

## 1. Die beiden Wege

### A: WhatsApp-Kanal
- **Einrichtung:** Einmalig in der WhatsApp-App auf einem Diensthandy (oder WhatsApp Business) einen Kanal „DPSG Diözesanverband Trier“ anlegen. Mehrere Admins sind möglich, posten geht auch über WhatsApp Web am Rechner.
- **Für Interessierte:** Link oder QR-Code öffnet den Kanal, ein Tipp auf „Abonnieren“, fertig. Abmelden mit „Nicht mehr folgen“.
- **Was der Verband sieht:** die Zahl der Abonnent:innen und Reaktionen, aber **keine Nummern und keine Namen**. Die Abonnent:innen sehen sich gegenseitig nicht.
- **Versand:** Text, Bild oder Link in den Kanal posten. Das CMS kann den Text fertig vorbereiten („Für WhatsApp-Kanal kopieren“); danach ist es Einfügen und Senden.

### B: WhatsApp Cloud API (das gebaute Modul)
- **Einrichtung:** Meta-Business-Konto mit Unternehmensverifizierung, Büro-Festnetznummer per Sprachanruf verifizieren, Vorlagen genehmigen lassen (Setup-Anleitung in docs/whatsapp_newsletter.md).
- **Für Interessierte:** QR-Code oder Link schickt START, Abmelden mit STOP.
- **Was der Verband speichert:** verschlüsselte Nummer und Einwilligungsnachweis.
- **Versand:** im CMS schreiben, zweite Person gibt frei, Versand läuft automatisch.

## 2. Kostenrechnung

### Annahmen
- Meta berechnet jede **zugestellte Marketing-Nachricht** einzeln. Für Deutschland rechnen wir mit **0,10 bis 0,15 €** je Nachricht, Mittelwert **0,13 €**. Die aktuelle Preisliste von Meta vor der Entscheidung prüfen; Meta rechnet in US-Dollar ab, der Euro-Betrag schwankt mit dem Kurs.
- **Anmelden, Abmelden und automatische Antworten kosten nichts**: Die Person schreibt zuerst (START/STOP), Antworten innerhalb von 24 Stunden sind bei Meta kostenlos.
- Nur der optionale **Formular-Weg** würde zusätzlich je neuer Anmeldung eine kostenpflichtige Bestätigungsnachricht auslösen (Service-Kategorie, deutlich günstiger als Marketing). Er ist ausgeschaltet und hier nicht eingerechnet.
- Szenarien: **2 Aussendungen im Monat** (normaler Betrieb) und **4 Aussendungen im Monat** (z. B. in Anmeldephasen).

### Ergebnis Cloud API (B)

| Empfänger:innen | Aussendungen/Monat | Nachrichten/Monat | Kosten/Monat (0,10–0,15 €) | **Mittel (0,13 €)** | Kosten/Jahr (Mittel) |
|---:|---:|---:|---:|---:|---:|
| 50 | 2 | 100 | 10–15 € | **13 €** | **156 €** |
| 50 | 4 | 200 | 20–30 € | **26 €** | **312 €** |
| 100 | 2 | 200 | 20–30 € | **26 €** | **312 €** |
| 100 | 4 | 400 | 40–60 € | **52 €** | **624 €** |

Zum Vergleich: eine einzelne Aussendung an 100 Empfänger:innen kostet etwa **13 €**, an 50 etwa **6,50 €**.

### Ergebnis WhatsApp-Kanal (A)

| Empfänger:innen | Aussendungen/Monat | Kosten/Monat | Kosten/Jahr |
|---:|---:|---:|---:|
| 50 | 2 oder 4 | 0 € | 0 € |
| 100 | 2 oder 4 | 0 € | 0 € |

Einmalig ggf. ein Diensthandy oder eine Prepaid-SIM, falls kein Gerät mit WhatsApp im Büro vorhanden ist. Der Kanal ist auch nicht auf 100 Abonnent:innen begrenzt.

### Zeitaufwand (Ehrenamt bzw. Büro)

| | Kanal | Cloud API |
|---|---|---|
| Einrichtung | ca. 15 Minuten | ca. 2–4 Stunden plus Wartezeit bei Meta (Verifizierung, Vorlagen) |
| Je Aussendung | ca. 2–3 Minuten (Text aus dem CMS kopieren, posten) | ca. 2–3 Minuten (im CMS schreiben) plus Freigabe durch eine zweite Person |
| Laufend | keiner | Vorlagen pflegen, Statusmeldungen im Blick behalten |

## 3. Abwägung jenseits der Kosten

| Kriterium | Kanal | Cloud API | Vorteil |
|---|---|---|---|
| Datenschutz (KDG) | keine Nummern beim Verband, kein Einwilligungsnachweis nötig | Nummern (verschlüsselt), Einwilligung, Löschfristen, Auskunft | **Kanal** |
| Freigabe durch DSB | voraussichtlich einfach | Datenschutz-Folgenabschätzung, Drittland USA, Auftragsverarbeitung | **Kanal** |
| Wird die Nachricht bemerkt? | Kanäle sind stummgeschaltet, Updates landen im Reiter „Aktuelles“ | kommt als Chat mit Benachrichtigung | **Cloud API** |
| Persönliche Nachrichten (z. B. Erinnerung an eigene Anmeldung) | nicht möglich | später möglich | **Cloud API** |
| Zielgruppen (z. B. nur Rover) | nur mit mehreren Kanälen | später als Themen-Abos möglich | **Cloud API** |
| Freigabe im CMS, Protokoll | nein (Admins posten direkt) | ja, Vier-Augen-Prinzip, Audit-Log | **Cloud API** |
| Abhängigkeit von Meta | ja | ja, zusätzlich Verifizierung und Preisliste | **Kanal** |
| Nummer im Profil sichtbar | Kanal zeigt keine Nummer | Büronummer ist der Absender | – |
| Rückmeldungen | Emoji-Reaktionen, keine Antworten | Antworten möglich (bei uns automatische Hinweisantwort) | – |
| Auffindbarkeit | Kanal ist im WhatsApp-Verzeichnis suchbar | nur über Website, Flyer, QR-Code | **Kanal** |

## 4. Wann lohnt sich der bezahlte Weg?

Die Cloud API lohnt sich, wenn mindestens einer dieser Punkte wichtig wird:
- Nachrichten **müssen** wahrgenommen werden (z. B. kurzfristige Absagen bei Veranstaltungen).
- Wir wollen **persönliche** Nachrichten schicken (Anmeldebestätigung, Erinnerung, „Platz frei auf der Warteliste“).
- Wir brauchen **getrennte Verteiler** je Stufe oder Fachbereich.
- Der Versand soll **nur nach Freigabe** und protokolliert aus dem CMS laufen.

Die Kosten sind dabei überschaubar: 50 Empfänger:innen mit zwei Aussendungen im Monat kosten etwa so viel wie ein Streaming-Abo. Bei 100 Empfänger:innen und vier Aussendungen sind es gut 600 € im Jahr. Das sollte dann bewusst im Haushalt stehen.

## 5. Warum nicht WhatsApp Web automatisiert oder ein CLI-Werkzeug?

Werkzeuge wie whatsapp-web.js, Baileys oder whatsmeow steuern ein normales WhatsApp-Konto fern. Das scheidet aus:
- **Nutzungsbedingungen:** Automatisierter Versand über normale Konten ist bei WhatsApp verboten und wird erkannt. Die Folge ist eine **Sperre der Nummer**, hier der Büronummer.
- **Webspace:** Die Werkzeuge brauchen einen dauerhaft laufenden Node- oder Go-Prozess mit angemeldetem Handy. Das geht auf dem Webspace nicht; wir bräuchten einen eigenen Server.
- **Datenschutz:** Sie arbeiten mit dem ganzen Konto inklusive Adressbuch und aller Chats. Das ist mit dem KDG nicht vereinbar und würde vom DSB nicht freigegeben.
- **Zuverlässigkeit:** Jedes WhatsApp-Update kann sie lahmlegen; Nachrichten fallen dann unbemerkt aus.

## 6. Empfehlung und nächste Schritte

1. **Jetzt:** WhatsApp-Kanal anlegen (Diensthandy oder WhatsApp Business im Büro), mindestens zwei Admins eintragen.
2. **Im CMS:** Die Seite `/whatsapp` zeigt dann den Kanal (Link und QR-Code) statt der START-Anmeldung, und an Beiträgen und Veranstaltungen gibt es „Für WhatsApp-Kanal kopieren“. Das ist eine kleine Erweiterung; das Cloud-API-Modul bleibt ausgeschaltet im System.
3. **Datenschutzerklärung:** kurzer Hinweis auf den Kanal (Betreiber Meta, Verband sieht keine Nummern).
4. **Nach 6 Monaten prüfen:** Wie viele folgen? Reicht der Kanal, oder brauchen wir persönliche Nachrichten oder Zielgruppen? Dann über die Cloud API entscheiden; die Kosten lassen sich mit der Tabelle in Abschnitt 2 abschätzen.
