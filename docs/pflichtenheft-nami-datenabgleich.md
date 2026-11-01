# Pflichtenheft: NaMi-Datenabgleich

**Stand:** 1. November 2026 · **Status:** gebaut (Stammes-Sitzung, Wunsch Roden); ND-09 offen · **Bezug:** Mitgliederabgleich (`docs/mitgliederabgleich.md`), Kasse (`docs/beitraege.md`), Zuschüsse (`docs/pflichtenheft-zuschuesse.md`)

NaMi aktuell zu halten ist mühsam: Umzüge, neue Mail-Adressen und Tippfehler
fallen oft erst auf, wenn Post zurückkommt. Kluft lässt Mitglieder bzw.
Erziehungsberechtigte ihre Daten selbst eintragen und zeigt eine
**Unterschiedsliste** zwischen diesen Angaben und NaMi. Kluft schreibt
nichts in NaMi; die Korrektur macht der Stamm dort selbst.

Anforderungen tragen IDs der Form `ND-<Nr>`.

## Entscheidungen (Roden, 01.11.2026)

1. Quelle: ein eigenes Formular „Meine Daten aktualisieren“ (Link zum Verschicken), dazu die Anmeldungen zu Veranstaltungen.
2. Felder: Anschrift, Geburtsdatum, Geschlecht, E-Mail, Telefon und die **E-Mail-Adresse der Erziehungsberechtigten** (in NaMi „E-Mail Vertretungsberechtigte*r“).
3. Nur eine Liste, kein Schreiben in NaMi.

## Anforderungen

- **ND-01** MUSS: Funktion „NaMi-Datenabgleich“ in `Features::KATALOG`, nur Stämme, ab Ausbaustufe 3, Standard aus. Braucht den NaMi-Zugang (Treiber `nami`) und die DPSG-Nummer des Stamms.
- **ND-02** MUSS: **Formular `/meine-daten`**: Vor- und Nachname des Mitglieds, Geburtsdatum, Geschlecht, Straße und Hausnummer, PLZ, Ort, Telefon, E-Mail des Mitglieds, E-Mail der Erziehungsberechtigten, „ich bin das Mitglied“ / „ich bin erziehungsberechtigt“, Einwilligung. Spamschutz wie das Kontaktformular. Bestätigung per Mail-Link; erst dann zählt die Meldung. Mehrere Kinder: Formular erneut ausfüllen.
- **ND-03** MUSS: Weitere Quelle sind bestätigte Anmeldungen der letzten 12 Monate mit Anschrift oder Geburtsdatum. Je Person zählt die neueste Angabe (gleicher Name und Geburtsdatum).
- **ND-04** MUSS: **Abgleich** im Backend auf Knopfdruck mit NaMi-Anmeldung wie beim Beitragsabruf (persönliches Konto oder gespeicherter Zugang). Über die Warteschlange:
  - Mitgliederliste des Stamms holen;
  - je Meldung das Mitglied suchen (Nachname, erster Vorname, Geburtsdatum; sonst nur der Name, wenn eindeutig);
  - für Treffer die NaMi-Details holen und Feld für Feld vergleichen.
- **ND-05** MUSS: **Vergleich ohne Kleinkram:** Groß- und Kleinschreibung, Leerzeichen, „Str.“ = „Straße“, ß = ss und Telefon nur nach Ziffern (+49 = 0) gelten nicht als Unterschied. Leere Angaben der Person zählen nicht als Unterschied, wohl aber ein leeres Feld in NaMi („in NaMi fehlt“).
- **ND-06** MUSS: **Unterschiedsliste:** je Person die abweichenden Felder mit „laut Meldung“ und „laut NaMi“, Quelle und Datum der Meldung, Mitgliedsnummer; dazu „in NaMi nicht gefunden“. Zeilen ohne Unterschied als „stimmt“. Je Zeile „in NaMi korrigiert“ abhaken (wer, wann). Als PDF und Excel.
- **ND-07** MUSS: **Rechte:** Verwaltung und Kasse (Mitgliederbeauftragte haben in Roden die Kasse).
- **ND-08** MUSS: **Datenschutz (KDG):**
  - Meldungen und Ergebnisse liegen verschlüsselt in der Datenbank.
  - Unbestätigte Meldungen werden nach 48 Stunden gelöscht, bestätigte nach 6 Monaten.
  - Ergebnisse eines Abgleichs werden 60 Tage nach dem Abgleich gelöscht.
  - NaMi-Daten werden nur für den Abgleich geholt und nur als Teil des Ergebnisses gespeichert.
  - Datenschutz-Anfragen (Auskunft, Löschen) erfassen die Meldungen.
  - Die Einwilligung nennt den Zweck: Abgleich mit der Mitgliederverwaltung der DPSG (NaMi) und Korrektur dort.
- **ND-09** SOLL: Erinnerung oder Versand des Links per Mail an alle Eltern (später; zunächst Link kopieren, z. B. für WhatsApp oder den Elternbrief).
