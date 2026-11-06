# Adressbuch

**Stand:** 7. November 2026 · Funktion „Adressbuch“ (`adressbuch`, ab Ausbaustufe 3, Standard aus, beim DV an; abschaltbar unter Verwaltung → Funktionen)

Ein gemeinsames Adressbuch im Backend unter **Verband & Kontakt**:

- **Adressbuch:** Kontakte mit Name, E-Mail und Organisation/Funktion, auch Externe ohne Konto. Für alle mit Zugriff gemeinsam.
- **Verteiler:** Listen von Kontakten, z. B. „DAK Wölflinge“ oder „Bezirksvorstände“.
  - **Gemeinsam:** Alle mit Zugriff sehen und nutzen den Verteiler; bearbeiten darf ihn, wer ihn angelegt hat, und die Verwaltung.
  - **Nur für mich:** Nur die Person, die ihn angelegt hat (und die Verwaltung), sieht ihn.

## Bedienung

| Was | Wo |
|---|---|
| Kontakt anlegen | Adressbuch → „Neuer Kontakt“, oder direkt im Verteiler über „+“ |
| Viele Adressen auf einmal | Verteiler → „Adressen einfügen“: eine Adresse je Zeile oder mit Semikolon getrennt, z. B. aus Outlook (`Name <mail>`, auch `Nachname, Vorname <mail>`) oder einer Excel-Spalte. Bekannte Adressen werden nicht doppelt angelegt. |
| Adressen ins Mailprogramm | Verteiler → „Adressen kopieren“: alle Adressen mit Semikolon getrennt, ins Feld **Bcc** einfügen |
| Videokonferenz-Einladungen | Bei Moderation und Teilnehmenden zusätzlich „aus Verteilern“ wählen; einzelne Adressen werden beim Tippen vorgeschlagen |

## Rechte

- **Zugriff:** Stufen- und Fachbereichsleitungen (`stufenadmin`, `sektionsadmin`), Vorstand, Verwaltung und Admins, wie bei Umfragen und Videokonferenzen.
- **Kontakte** können alle mit Zugriff anlegen, ändern und löschen.
- **Verteiler:** siehe oben.

## Datenschutz

- Kontakte von Externen sind personenbezogene Daten. Nur Name, Adresse und eine Funktion eintragen, keine sensiblen Angaben in die Notiz.
- Die **Datenschutzauskunft** (Verwaltung → Datenschutz-Anfragen) findet Kontakte zur Adresse, exportiert sie mit den Verteilern und löscht sie samt Zugehörigkeit zu den Verteilern.
- Für die Datenschutzerklärung: „Für Einladungen führen wir ein internes Adressbuch mit Name, E-Mail-Adresse und Funktion; Auskunft und Löschung jederzeit.“

## Technik

- **Tabellen:** `adressbuch_kontakte` (E-Mail eindeutig, klein geschrieben), `adressbuch_verteiler` (`privat`, `user_id`), `adressbuch_kontakt_verteiler`.
- **Logik:** `App\Support\Adressbuch`, mit Zugriff, sichtbaren Verteilern, Zerlegen von Freitext (`zerlegen()`) und Übernahme in einen Verteiler.
- **Später denkbar:** Verteiler auch bei anderen Mails (z. B. Umfrage-Einladungen an freie Adressen), Abgleich mit Personen der Website.
