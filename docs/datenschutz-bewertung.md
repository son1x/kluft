# Datenschutz-Bewertung Kluft (DV Trier)

Stand 11.11.2026, nach den Datenschutz-Verbesserungen (sensible Felder,
Foto-Einwilligung, Rechte-Prüfung, Kontosperre) und der Rechte-Matrix.
Technische Einschätzung, **keine Rechtsberatung**. Grundlage:
`docs/datenschutz-ueberblick.md`.

Skala: 1 = ungenügend, 2 = mangelhaft, 3 = ausreichend, 4 = gut, 5 = sehr gut.
„Vorher“ = Stand der ersten Übersicht am Morgen des 11.11.2026.

## Gesamtbild

| | Vorher | Jetzt |
|---|---|---|
| **Gesamt** | **3,6** | **4,3** |
| Technik (was die Software leistet) | 4,0 | 4,6 |
| Organisation (was ihr noch tun müsst) | 3,0 | 3,5 |

Die Software deckt die wesentlichen Risiken jetzt selbst ab. Was zur 5 fehlt,
ist überwiegend Organisation: Rollen sauber vergeben, Prüfung abschließen,
Datenschutzerklärung und AVV ablegen, Gesundheitsfelder konsequent markieren.

## Bewertung je Bereich

| Bereich | Vorher | Jetzt | Was sich geändert hat | Was noch fehlt |
|---|---|---|---|---|
| **Zugriff und Rollen** | 3 | **4,5** | Rechte-Matrix mit markierten sensiblen Rechten; DL nur sparsame Liste; Rechte-Prüfung mit Jahreserinnerung; Hinweis auf Bereichsrollen ohne Ablauf; Sperre ungenutzter Konten | Rollen tatsächlich nach `docs/rollen.md` vergeben, erste Prüfung abschließen |
| **Gesundheitsdaten (Art. 9 / § 11 KDG)** | 2 | **4** | „Sensible Angabe“ je Feld: Hinweis, eigene Einwilligungsversion, nicht im Standard-Export, Löschung nach 14 Tagen, protokollierter Sonderexport | wirkt nur, wenn das Feld markiert wird; keine automatische Erkennung |
| **Fotos / Bildrechte** | 3 | **4,5** | Einwilligung je Person im Anmeldeformular (freiwillig, Ja/Nein), sichtbar in Listen, PDF, Fotos-Reiter, Auskunft; Vier-Augen-Freigabe | Abfrage je Veranstaltung einschalten; Widerrufe von Hand umsetzen |
| **Speicherdauer und Löschung** | 4 | **4,5** | zusätzlich frühe Löschung sensibler Angaben; Hinweis zur Zuschuss-Aufbewahrung | Zuschussunterlagen außerhalb von Kluft geregelt ablegen |
| **Exporte und Ausdrucke** | 3,5 | **4** | Hinweis vor dem Download; sensible Felder nur auf Wunsch; gesonderter Protokolleintrag; „Keine Fotos“ in der Liste | Dateien auf privaten Geräten bleiben ein Restrisiko |
| **Verschlüsselung und Technik** | 4,5 | **4,5** | unverändert: Zusatzangaben, Nachrichten, IBAN u. a. verschlüsselt; CSP, Backups verschlüsselt | Name, E-Mail, Geburtsjahr bleiben unverschlüsselt (für Suche nötig) |
| **Betroffenenrechte** | 4,5 | **4,5** | Auskunft enthält jetzt die Foto-Einwilligung | – |
| **Externe Dienste** | 3 | **3,5** | Anleitung für eigenen ntfy-Server; AVV-Checkliste | Verträge prüfen und ablegen; ntfy-Server aufsetzen |
| **Testsystem** | 2,5 | **4** (geplant) | Plan: heutiges Testsystem wird Live, neues Testsystem ohne echte Daten | Umzug durchführen |
| **Vor Ort (Tablet/Handy, Unterschriften)** | – | **4** | Neu (docs/vor-ort.md): gleiche Rechte wie die Teilnehmendenliste; nichts dauerhaft auf dem Gerät; Ereignisse verschlüsselt, sensible Werte nur „geändert“; Unterschriften verschlüsselt mit Prüfsumme, 14 Tage nach dem Festschreiben gelöscht; alles protokolliert | Datenschutzerklärung um die digitale Unterschrift ergänzen; Tablet mit Gerätesperre; bei Offline (Stufe 3) neu bewerten |
| **Transparenz** | 3 | **4** | Textbausteine je Funktion; Hinweise im Formular | Datenschutzerklärung tatsächlich anpassen |

## Verbleibende Risiken (absteigend)

1. **Organisatorische Lücken**: Rollen zu großzügig, Prüfung nie
   abgeschlossen, Gesundheitsfeld nicht markiert. Die Software erinnert
   (Was steht an, Hinweise), kann es aber nicht erzwingen.
2. **Verträge (AVV)** mit Hoster, Mailversand, Microsoft, Brevo, ggf. Meta
   noch nicht belegt.
3. **Daten auf privaten Geräten** nach Export bzw. Ausdruck.
4. **Breiter Vollzugriff** von Verwaltung, Bildungsreferent*innen und Admins –
   fachlich gewollt; durch Prüfung und Protokoll begrenzt.
5. **Backups** enthalten gelöschte Daten noch bis zu 14 Tage
   (üblich, in der Erklärung erwähnen).

## Nächste Schritte bis zur 5

1. Rollen vergeben, „gültig bis“ für alle DL, **Rechte-Prüfung abschließen**.
2. Bestehende Gesundheitsfelder als **sensibel** markieren; bei
   Veranstaltungen mit Fotos die **Foto-Einwilligung** einschalten.
3. **Datenschutzerklärung** mit den Textbausteinen abgleichen; von der oder
   dem Datenschutzbeauftragten freigeben lassen.
4. **AVV-Checkliste** abarbeiten.
5. **Umzug Test → Live**, neues Testsystem ohne echte Daten.
6. **Eigener ntfy-Server** hinter Caddy.
