# Rollen und Rechte

Vergeben unter Benutzer → Person → „Rollen“ (Verwaltung und Admins; Stufen-
und Sektionsadmins sowie Bildungsreferent*innen nur Autor:innen). Jede
Zuweisung kann ein „gültig bis“ haben. Was jemand hat, zeigt Mein Konto →
„Meine Rollen und Rechte“.

## Übersicht

| Rolle | Darf |
| --- | --- |
| Autor:in (Bereich) | eigene Beiträge schreiben und zur Prüfung geben |
| Stufenadmin / Sektionsadmin | Seiten und Beiträge des Bereichs pflegen und freigeben, Autor:innen des Bereichs freischalten; Veranstaltungen des Bereichs **je nach Website** (siehe unten) |
| Veranstaltungen bearbeiten – Bereich | Veranstaltungen des Bereichs anlegen und bearbeiten, Teilnehmende verwalten (nur wo die Leitungen sonst nur lesen) |
| Vorstand | Vorstandsseiten pflegen, Beiträge freigeben, Veranstaltungen ohne Teilnehmerdaten sehen, Prävention; bekommt Vorstands-Mails (z. B. Prävention) |
| Bildungsreferent*in (DV, Bezirk) | alle Seiten, Beiträge, Veranstaltungen samt Teilnehmenden, Mediathek, Personen, Downloads, Georgsbote, Nachrichten, Umfragen, Adressbuch, Materialverleih, Zuschussanträge; Autor:innen freischalten |
| Verwaltung | wie Bildungsreferent*in, dazu Benutzer und alle Rollen (außer Admin), Datenschutz-Anfragen, im DV/Bezirk Zahlungen und Auslagen, Prävention, Mail-Warteschlange, Weiterleitungen, Fußzeile, Zuschussgeber |
| Admin (Technik) | wie Verwaltung, dazu Funktionen, Backups, Updates, Wartungsmodus, Protokoll, Instagram, Stufen |
| Kasse, Sammelbestellung, WhatsApp, Materialwart*in | jeweils nur ihr Bereich |

Bewusst **nicht** bei Bildungsreferent*innen: Zahlungen/Auslagen,
Prävention, Datenschutz-Anfragen, Rollen außer Autor:in, Technik.

## Veranstaltungen: lesen oder bearbeiten

`config dpsg.rechte.leitung_veranstaltungen` je Website:

- **bearbeiten** (Standard, z. B. Stämme): Stufen- und Sektionsadmins legen
  Veranstaltungen ihres Bereichs an und verwalten die Teilnehmenden.
- **lesen** (DV Trier): Stufen- und Sektionsadmins sehen Veranstaltungen
  ihres Bereichs und eine **sparsame Teilnehmendenliste**, können aber nichts
  anlegen, ändern, bestätigen, exportieren oder Mails senden. Neue Anmeldungen
  melden Push-Nachrichten trotzdem (nur Titel und Platzzahlen). Wer im Bereich
  doch schreiben soll, bekommt zusätzlich „Veranstaltungen bearbeiten –
  Bereich“ und sieht dann alles.

### Sparsame Teilnehmendenliste (nur lesen)

Für Ehrenamtliche, die Anmeldungen nur im Blick behalten sollen. Gezeigt wird,
was zum Planen nötig ist:

| sichtbar | nicht sichtbar |
| --- | --- |
| Name, Stamm/Gruppierung, gewählte Tage, Status (bestätigt/Warteliste), bestätigt am, Zählung je Tag | E-Mail, Geburtsjahr, alle weiteren Formularangaben (z. B. Ernährung, Gesundheit, Telefon, Anschrift), Sorgeberechtigte, Dokumente, Beitrag/Zahlung, Mitgliedsstatus, Bescheinigungen, Zuschussdaten, Detailfenster; unbestätigte und stornierte Anmeldungen |

Die Schreib- und Detailaktionen gibt es in dieser Ansicht gar nicht (nicht nur
ausgeblendet). Bildungsreferent*innen (datenschutzgeschult), Verwaltung und
„Veranstaltungen bearbeiten“ sehen die volle Liste.

Gesperrt ist im Lesemodus nicht nur die Anzeige: Schreibaktionen werden auch
bei manipulierten Anfragen abgelehnt.

## Empfehlung DV Trier

| Personenkreis | Rollen |
| --- | --- |
| DL-Mitglieder | Stufen- bzw. Sektionsadmin ihres Bereichs (Texte schreiben, Veranstaltungen und Anmeldungen ansehen) |
| Bildungsreferent*innen | Bildungsreferent*in |
| Geschäftsführende*r Bildungsreferent*in | Admin |
| Verwaltungskraft | Verwaltung |
| Vorstand mit IT-Aufgaben | Vorstand + Admin |
| Vorstand ohne eigene Pflege | Autor:in (dann ohne Vorstands-Mails) |
