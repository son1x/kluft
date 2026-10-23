# Pflichtenheft: Sammelbestellung beim Rüsthaus („Schattenshop“)

**Stand:** 23. Oktober 2026 · **Status:** 3. Fassung, Phase 1 umgesetzt (siehe Abschnitt 11) · **Bezug:** Kluft (`docs/pflichtenheft.md`, `docs/beitraege.md`, `docs/mitgliederabgleich.md`)

Mitglieder des Stamms bzw. ihre Erziehungsberechtigten bestellen Kluft,
Halstuch, Aufnäher und andere Artikel aus dem Rüsthaus über die
Stammes-Website und bezahlen vorab auf das Stammeskonto. Die
Sammelbestellerin (in Roden: Michele Franke, Rüsthaus-Bestellungen) gibt
Artikel frei, öffnet und schließt die Bestellrunden von Hand und bestellt
zum angekündigten Bestelldatum gesammelt beim Rüsthaus. Danach wird die
Ware gegen Unterschrift oder Abhaken ausgegeben.

Anforderungen tragen IDs der Form `SB-<Nr>`. **MUSS** = Pflicht, **SOLL** =
Pflicht außer mit begründeter Ausnahme, **KANN** = optional.

---

## 1. Ziel, Abgrenzung, Entscheidungen

### 1.1 Ziel

1. **Weg mit Zetteln, WhatsApp und Excel:** Bestellungen kommen strukturiert an (Rüsthaus-Artikelnummer, Größe, Anzahl, für welches Mitglied).
2. **Vorkasse:** Beim Rüsthaus wird nur bestellt, was bis zum Bestelldatum bezahlt ist.
3. **Ein lokaler Shop mit Bild und Beschreibung** für die freigegebenen Artikel, ohne dass Mitglieder sich im Rüsthaus-Shop zurechtfinden müssen.
4. **Eine Sammelbestellung je Runde:** nach Artikelnummer und Größe summiert.
5. **Überblick für die Kasse:** Zahlungseingänge auf dem Stammeskonto, offene Beträge, Rüsthaus-Rechnung.

### 1.2 Entscheidungen des Stamms (19.10.2026)

| Frage | Entscheidung |
|---|---|
| Wer darf bestellen? | Nur Mitglieder des Stamms bzw. ihre Erziehungsberechtigten. |
| Bezahlung | Vorab, auf das **Stammeskonto**, spätestens bis zum **Bestelldatum** der Runde. |
| Zuschüsse | Keine. |
| Provision | Zieht das Rüsthaus **direkt auf der Rechnung** ab. Mitglieder zahlen den Rüsthaus-Preis; die Differenz bleibt beim Stamm. |
| Versand | Trägt der Stamm (Vorteil für die Mitglieder). |
| Katalog | Artikel werden im Backend per Rüsthaus-Artikelnummer **freigegeben**; Bild und Beschreibung werden lokal zwischengespeichert. Grundausstattung: Klufthemd bzw. -bluse, Halstuch, Basisaufnäher. Dazu freie Bestellung per Artikelnummer, außer **gesperrte Artikel** (z. B. Gruppenzelte). |
| Rüsthaus-Daten | Übernahme von Bild und Beschreibung für DPSG-Zwecke ist laut Stamm erlaubt. Eine kurze schriftliche Bestätigung des Rüsthaus wird empfohlen und abgelegt. |
| Bestellrunden | Von Hand geöffnet und geschlossen, mit angekündigtem Bestelldatum. |
| Ausgabe | Ausgabeliste mit Unterschriftsspalte (Papier) oder Abhaken durch Sammelbestellerin bzw. Verwaltung. |
| Zahlungsdienstleister | Noch offen, Bewertung in Kapitel 6. |

### 1.3 Was es nicht ist

- **Keine Bestellung beim Rüsthaus aus Kluft heraus:** Das Rüsthaus hat keine öffentliche Bestell-Schnittstelle. Die Sammelbestellerin bestellt dort selbst; Kluft liefert die fertige Liste (SB-41).
- **Kein Spiegel des ganzen Rüsthaus-Shops:** Lokal liegen nur freigegebene Artikel und Artikel, die jemand frei bestellt hat.
- **Kein Lager:** Restbestände des Stamms KANN eine spätere Ausbaustufe abbilden (Kapitel 11).

---

## 2. Begriffe

| Begriff | Bedeutung |
|---|---|
| **Freigegebener Artikel** | Rüsthaus-Artikel, den die Sammelbestellerin im Backend freigegeben hat. Erscheint im lokalen Shop mit zwischengespeichertem Bild, Beschreibung, Größen und Preis. |
| **Freier Artikel** | Beliebiger Rüsthaus-Artikel, den jemand per Artikelnummer oder Link bestellt, sofern er nicht gesperrt ist. Zählt erst, wenn die Sammelbestellerin ihn bestätigt hat. |
| **Gesperrter Artikel** | Darf nicht bestellt werden, z. B. Gruppenzelte. Sperre per Artikelnummer, Nummernbereich oder Rüsthaus-Kategorie. |
| **Variante** | Größe oder Ausführung, beim Rüsthaus mit eigener Artikelnummer (z. B. `0100003.30` = Halbarm-Klufthemd, Kragenweite 30). |
| **Bestellrunde** | Zeitraum zwischen manuellem Öffnen und Schließen, mit angekündigtem **Bestelldatum** (= Zahlungsfrist). |
| **Bestellung** | Was eine Person in einer Runde für ein oder mehrere Mitglieder bestellt: eine oder mehrere Positionen. |
| **Sammelliste** | Alle bezahlten Positionen einer Runde, nach Artikelnummer summiert: das, was beim Rüsthaus bestellt wird. |
| **Rüsthaus-Cache** | Lokale Kopie von Bild, Beschreibung, Varianten und Preis eines Rüsthaus-Artikels mit Abrufdatum. |

---

## 3. Rollen

| Rolle | Wer (Roden) | Darf |
|---|---|---|
| **Besteller*in** (öffentlich, ohne Konto) | Mitglieder, Erziehungsberechtigte | Bestellen, eigene Bestellung über den Link in der Mail ansehen, ändern oder stornieren, solange die Runde offen ist. |
| **Sammelbestellung** (neue Rolle) | Michele Franke | Artikel freigeben und sperren, Cache aktualisieren, Runden öffnen und schließen, Bestellungen prüfen, freie Artikel bestätigen, Sammel- und Ausgabeliste, Ausgabe abhaken. |
| **Kasse** (vorhanden) | Hendrik Schuler | Zahlungseingänge abhaken, offene Beträge, Erstattungen, Rüsthaus-Rechnung; nicht den Katalog ändern. |
| **Verwaltung** (vorhanden) | nach Vergabe | Ausgabe abhaken (SB-43), Bestellungen ansehen. |
| **Admin** | Stammesvorstand | alles, Funktion ein- und ausschalten. |

- **SB-01** MUSS: Die Rolle „Sammelbestellung“ wird wie „Kasse“ unter „Benutzer“ vergeben und gilt nur auf Stammes-Websites. Rollen gehören zum gemeinsamen Kern; die Rolle wird mit der Haupt-Sitzung abgestimmt (`docs/abstimmung.md`).
- **SB-02** MUSS: Ohne eine der Rollen oben ist im Backend nichts davon sichtbar.

---

## 4. Ablauf einer Bestellrunde

1. **Öffnen (von Hand):** Die Sammelbestellerin öffnet eine Runde, z. B. „Herbst 2026“, mit **Bestelldatum**, z. B. 15.11. Bis dahin muss bezahlt sein. Auf Wunsch geht eine Mail an die Leitenden mit dem Link.
2. **Bestellen:** Mitglieder bzw. Erziehungsberechtigte wählen aus dem lokalen Shop oder bestellen frei per Artikelnummer und bestätigen per Mail-Link.
3. **Zahlungsaufforderung:**
   - **Freigegebene Artikel:** Die Zahlungsaufforderung kommt **sofort** mit der Bestätigung. Sie enthält Betrag, Verwendungszweck, GiroCode und die Frist (Bestelldatum).
   - **Freie Artikel:** Die Sammelbestellerin prüft und bestätigt Artikel, Größe und Preis. Danach kommt die Zahlungsaufforderung (bzw. eine ergänzte für die ganze Bestellung).
4. **Prüfen (laufend bis zum Bestelldatum):**
   - **Mitgliedschaft:** per NaMi-Abgleich (SB-37). Ist die Person kein Mitglied, wird die Bestellung storniert, Bezahltes wird erstattet.
5. **Zahlungseingang:** Die Kasse hakt Überweisungen auf dem Stammeskonto ab, bzw. der Eingang wird automatisch erkannt (Kapitel 6). Ein Tag vor dem Bestelldatum geht eine Erinnerung an alle mit offenem Betrag.
6. **Schließen (von Hand, am Bestelldatum):** Danach sind keine Bestellungen und Änderungen mehr möglich. Unbezahlte Positionen werden mit Mail storniert (die Sammelbestellerin kann einzelne Bestellungen noch halten, z. B. bei angekündigter Zahlung).
7. **Rüsthaus-Bestellung:** Die Sammelliste enthält nur bezahlte Positionen. Die Sammelbestellerin bestellt beim Rüsthaus und markiert die Runde als „bestellt“.
8. **Lieferung:** Rüsthaus-Rechnung eintragen (Betrag nach Provisionsabzug, Versand). Die Runde wird als „geliefert“ markiert. Die Besteller*innen bekommen eine Mail: „Ware ist da, Abholung …“.
9. **Ausgabe:** Entweder auf der gedruckten Ausgabeliste unterschreiben lassen und danach abhaken, oder direkt abhaken (Sammelbestellerin bzw. Verwaltung).
10. **Abschluss:** Wenn alles ausgegeben ist, ist die Runde erledigt. Danach läuft die Löschfrist (SB-71).

Statusfolge einer Runde: `offen` → `geschlossen` → `bestellt` → `geliefert` → `erledigt`.

---

## 5. Funktionale Anforderungen

### 5.1 Freigabe, Sperren und Rüsthaus-Cache

- **SB-10** MUSS: **Artikel freigeben:** Die Sammelbestellerin gibt eine Rüsthaus-Artikelnummer oder einen Link ein. Kluft ruft die Produktseite ab und legt im Cache ab:
  - Name, Beschreibung (als reiner Text bzw. bereinigtes HTML);
  - Hauptbild, verkleinert, lokal in der Mediathek, Vermerk „Bild: Rüsthaus“;
  - Varianten mit Artikelnummer, Bezeichnung (z. B. Kragenweite) und Preis;
  - Link zur Produktseite und Abrufdatum.

  Danach prüft und ergänzt sie (eigene Bezeichnung, Kategorie im lokalen Shop, Reihenfolge, einzelne Varianten aus- oder einblenden) und gibt frei.
- **SB-11** MUSS: Grundausstattung als Startinhalt für Roden, freigegeben beim Einschalten: Klufthemd, Kluftbluse, Halstuch, Basisaufnäher. Die genauen Artikelnummern legt der Stamm fest (Kapitel 10).
- **SB-12** MUSS: **Cache aktualisieren:** einzeln oder „alle freigegebenen aktualisieren“ auf Knopfdruck, z. B. beim Öffnen einer Runde. Geänderte Preise und weggefallene Varianten werden vor dem Übernehmen angezeigt. Kein automatisches Abrufen im Hintergrund.
- **SB-13** MUSS: Ist ein Artikel beim Rüsthaus nicht mehr zu finden, bleibt der Cache stehen. Der Artikel ist markiert („beim Rüsthaus nicht gefunden“) und wird nicht mehr angeboten, bis die Sammelbestellerin entscheidet.
- **SB-14** MUSS: **Freie Artikel** im Bestellformular: Die Person gibt eine Artikelnummer oder einen Rüsthaus-Link ein. Kluft zeigt Name, Bild, Preis und Größen aus dem Cache oder per Einzelabruf. Klappt der Abruf nicht, gibt die Person Bezeichnung und Größe selbst ein. Freie Positionen sind mit „wird von der Sammelbestellerin bestätigt“ gekennzeichnet. Bestätigt sie, kann sie den Artikel zugleich dauerhaft freigeben.
- **SB-15** MUSS: **Sperren:** Sperrliste aus Artikelnummern, Nummernbereichen bzw. Präfixen und Rüsthaus-Kategorien (z. B. „Zelte“, „Jurten & Kohten“). Gesperrte Artikel lassen sich weder frei bestellen noch freigeben. Die Person sieht einen pflegbaren Hinweis, z. B. „Gruppenmaterial bestellt der Stamm, sprich deine Leitung an“. Für Roden sind beim Einschalten die Kategorien für Zelte gesperrt.
- **SB-16** MUSS: **Grenzen des Abrufs:**
  - nur Einzelabrufe auf Anlass (Freigabe, Aktualisieren, freie Eingabe), kein Durchsuchen des Shops;
  - höchstens eine Anfrage pro Sekunde; Ergebnis für freie Eingaben 24 Stunden zwischengespeichert;
  - eigene Kennung im User-Agent (`Kluft/… (+https://www.dpsg-roden.de)`);
  - Bilder nur von freigegebenen Artikeln dauerhaft speichern, verkleinert;
  - der Abruf ist eine Hilfe, keine Voraussetzung: Fällt er aus oder ändert sich das Seitenformat, geht alles mit Handeingabe weiter;
  - Grundlage: Die Produktseiten des Rüsthaus-Shops (Shopware) enthalten Artikelnummer (`data-ordernumber`), Preis (`itemprop="price"`) und die Größenauswahl. Produktseiten sind laut `robots.txt` nicht gesperrt.
- **SB-17** MUSS: Der Preis wird bei der Bestellung festgehalten. Ändert sich der Rüsthaus-Preis danach, gilt für bereits bezahlte Positionen der bezahlte Preis. Bei offenen Positionen kann die Sammelbestellerin den Preis anpassen; die Person bekommt eine geänderte Zahlungsaufforderung.

### 5.2 Bestellrunden

- **SB-20** MUSS: Runde mit Name, **Bestelldatum** (Pflicht, zugleich Zahlungsfrist) und Status (Kapitel 4).
- **SB-21** MUSS: Öffnen und Schließen nur von Hand durch die Sammelbestellerin. Höchstens eine Runde ist offen. Ist keine offen, zeigt `/bestellen` den Shop zum Ansehen und einen pflegbaren Hinweis („Die nächste Sammelbestellung startet …“).
- **SB-22** SOLL: Erinnerung an die Sammelbestellerin am Bestelldatum, falls die Runde noch offen ist.

### 5.3 Bestellen (öffentlich)

- **SB-30** MUSS: Seite „Ausrüstung bestellen“ (`/bestellen`): lokaler Shop mit Kategorien, Bild, Beschreibung, Größenwahl und Preis; daneben „Anderen Rüsthaus-Artikel bestellen“ (SB-14). Im Menü abschaltbar wie jede Seite, Einleitungstext pflegbar.
- **SB-31** MUSS: Formular:
  - bestellende Person: Name, E-Mail, optional Telefon; Angabe „Ich bin selbst Mitglied“ oder „Ich bin erziehungsberechtigt für das Mitglied“;
  - je Mitglied: Vor- und Nachname, Stufe; Positionen (Variante, Anzahl);
  - Bemerkung, gewünschte Abholung (Gruppenstunde der Stufe, nach Absprache).
- **SB-32** MUSS: Bestätigung per Mail-Link (Double-Opt-in wie bei Veranstaltungsanmeldungen). Unbestätigte Bestellungen verfallen nach 48 Stunden.
- **SB-33** MUSS: Über den Link in der Mail kann die Person ihre Bestellung ansehen, solange die Runde offen ist ändern und stornieren, und den Status sehen (Zahlung offen, bezahlt, wird geprüft, bestellt, abholbereit, ausgegeben). Ändert sich der Betrag einer bereits bezahlten Bestellung, entsteht eine Nachforderung bzw. Erstattung.
- **SB-34** MUSS: Einwilligung (Datenverarbeitung, Löschfrist) und Bestätigung, Mitglied bzw. erziehungsberechtigt zu sein. Texte wie bei Veranstaltungen (`event_consent`).
- **SB-35** MUSS: Summe mit Bestelldatum als Zahlungsfrist; freie Artikel als „vorläufig“.
- **SB-36** SOLL: Spamschutz wie beim Kontaktformular (Honeypot, Zeitprüfung, Drosselung).
- **SB-37** MUSS: **Nur Mitglieder:** Im Backend bei jeder Bestellung „Mitgliedschaft prüfen“ über den vorhandenen NaMi-Abgleich (`docs/mitgliederabgleich.md`, Name und Stamm). Ergebnis als Ampel. Ohne Treffer entscheidet die Sammelbestellerin (z. B. Neumitglied noch nicht in NaMi). Eltern müssen keine Mitgliedsnummer kennen.

### 5.4 Backend für die Sammelbestellung

- **SB-40** MUSS: Bestellungen einer Runde mit Filter (Zahlung offen/bezahlt, Prüfung offen, freie Artikel offen, Stufe, Artikel); bearbeiten, Positionen streichen oder ändern, Bestellung von Hand eintragen.
- **SB-41** MUSS: Sammelliste der **bezahlten** Positionen: je Rüsthaus-Artikelnummer Bezeichnung, Größe und Summe. Export als CSV/Excel und Druckansicht.
- **SB-42** MUSS: **Ausgabeliste** je Stufe bzw. Abholort, druckbar als PDF: Mitglied, Artikel, Größe, Anzahl, Spalte „erhalten am“ und **Unterschriftsspalte** (Mitglied bzw. erziehungsberechtigte Person). Zusätzlich je Bestellung ein einzelner Ausgabeschein mit Unterschriftsfeld, z. B. zum Mitgeben in die Gruppenstunde.
- **SB-43** MUSS: „Ausgegeben“ abhaken je Position oder Bestellung, durch die Sammelbestellerin oder die Verwaltung (Rolle „verwaltung“), mit Datum und wer abgehakt hat. Optional mit Vermerk „Unterschrift auf Liste vom …“. Teillieferung: Position als „nachgeliefert“ markieren oder in die nächste Runde übernehmen (bereits bezahlt).
- **SB-44** MUSS: Mails aus Vorlagen: Zahlungsaufforderung, Erinnerung, Storno wegen Nichtzahlung, „Ware ist da“.
- **SB-45** MUSS: Rüsthaus-Rechnung je Runde: Rechnungsbetrag (nach Provisionsabzug), Versandkosten, Datum. Kluft zeigt die Summe der Mitgliederzahlungen, den Rechnungsbetrag und die Differenz (= Provision abzüglich Versand) für die Kasse.

### 5.5 Bezahlung und Erstattung

- **SB-50** MUSS: Vorkasse auf das Stammeskonto, Frist ist das Bestelldatum. In die Sammelliste kommt nur, was vollständig bezahlt ist.
- **SB-51** MUSS: Zahlungswege nach Kapitel 6: mindestens Überweisung mit GiroCode (Stammeskonto als Einstellung, nur für Admin und Kasse änderbar).
- **SB-52** MUSS: Eindeutiger Verwendungszweck je Bestellung, z. B. `RH-2026-2-0123`. Offene Beträge, Eingänge und Erstattungen je Runde und gesamt für Sammelbestellerin und Kasse.
- **SB-53** MUSS: **Erstattung**, wenn ein bezahlter Artikel nicht lieferbar ist, die Bestellung storniert wird oder die Person kein Mitglied ist: Die Kasse erfasst sie (Betrag, Datum, Weg).
- **SB-54** MUSS: Die Verkaufspreise sind die Rüsthaus-Preise; Kluft schlägt nichts auf und zieht nichts ab. Versand trägt der Stamm und wird den Mitgliedern nicht berechnet.
- **SB-56** SOLL: Erinnerung bei offenem Betrag einen Tag vor dem Bestelldatum (abschaltbar).

### 5.6 Benachrichtigungen

- **SB-60** MUSS: Mails an Besteller*innen: Bestätigungslink, Bestellung mit Zahlungsaufforderung, Bestätigung freier Artikel, Änderung durch den Stamm, Zahlungseingang, Erinnerung, Storno/Erstattung, „Ware ist da“.
- **SB-61** MUSS: Mail an die Sammelbestellerin bei jeder bestätigten Bestellung mit freien Artikeln sofort, sonst als Tageszusammenfassung (einstellbar).
- **SB-62** MUSS: Absender ist die Website (`MAIL_FROM_*`), Antwort-an ist die eingestellte Adresse (in Roden z. B. michele@dpsg-roden.de).

---

## 6. Zahlungswege: Lohnt sich ein Zahlungsdienstleister?

### 6.1 Die Möglichkeiten

| | **Überweisung mit GiroCode** | **Stripe** | **PayPal** |
|---|---|---|---|
| **Wie** | Zahlungsaufforderung mit Betrag, Verwendungszweck und QR-Code (EPC-QR); Banking-App scannt ihn. | Bezahlseite von Stripe (Checkout): Karte, SEPA-Lastschrift, Apple/Google Pay; PayPal lässt sich dort als Zahlart zuschalten (Verfügbarkeit prüfen). | PayPal-Checkout. |
| **Kosten** | keine | je Zahlung Gebühr, für EU-Karten etwa 1,5 % + 0,25 €; aktuelle Preise beim Anbieter prüfen. | je Zahlung Gebühr, im üblichen Händlertarif deutlich über 2 % plus Festbetrag. |
| **Beispiel Halstuch 10 €** | 0 € | etwa 0,40 € | etwa 0,60 € |
| **Zahlungseingang erkennen** | Kasse hakt ab (später CSV-Import vom Konto) | automatisch (Webhook) | automatisch (Webhook) |
| **Erstattung** | Überweisung durch die Kasse | per Klick, Gebühr wird meist nicht erstattet | per Klick |
| **Voraussetzungen** | Stammeskonto (vorhanden) | Vertrag und Identitätsprüfung (KYC) für den Kontoinhaber des Stammeskontos; Auszahlung auf das Stammeskonto. Ob das für einen nicht eingetragenen Stamm geht, vorab klären. | wie Stripe (PayPal-Geschäftskonto) |
| **Aufwand in Kluft** | gering | mittel: Checkout, Webhook, Erstattung, Secrets im Environment | mittel, wie Stripe |
| **Datenschutz** | nur Kontoauszug | Stripe ist Auftragsverarbeiter, Datenschutzerklärung erweitern | PayPal ist eigener Verantwortlicher, Datenschutzerklärung erweitern |

### 6.2 Empfehlung

1. **Phase 1: Überweisung mit GiroCode auf das Stammeskonto.**
   - Kostet nichts und passt zur Frist „bis zum Bestelldatum“.
   - Die Kasse hakt die Eingänge anhand des Verwendungszwecks ab.
2. **Phase 2 bei Bedarf: Stripe als zusätzlicher Weg**, wenn das Abhaken zu viel Arbeit macht oder Eltern lieber sofort online zahlen.
   - **Lieber Stripe als PayPal:** niedrigere Gebühren, eine Anbindung für mehrere Zahlarten, PayPal dort zuschaltbar.
   - **Gebühren:** Der Stamm trägt sie aus der Provision.
3. **Ein Zahlungsbaustein für Rüsthaus und Veranstaltungen:**
   - **Aufbau:** Kluft bekommt einen Baustein „Zahlungen“ mit Betrag, Zweck, Status und austauschbarem Zahlungsweg.
   - **Nutzer:** Sammelbestellung und Teilnahmebeiträge von Veranstaltungen nutzen ihn gemeinsam.
   - **Absprache nötig:** Veranstaltungen gehören zum gemeinsamen Kern. Der Baustein wird mit der Haupt-Sitzung abgestimmt, bevor er gebaut wird.

- **SB-90** MUSS: Zahlungen als eigener Baustein mit austauschbarem Zahlungsweg. Phase 1: Überweisung mit GiroCode (EPC-QR, SEPA).
- **SB-91** KANN: Stripe Checkout mit Webhook-Prüfung (Signatur). Schlüssel nur als Secrets im GitHub-Environment, nie im Repository. Der Online-Zahlungsweg ist je Instanz einschaltbar.
- **SB-92** MUSS: Keine Karten- oder Kontodaten in Kluft speichern. Online-Zahlung läuft ausschließlich über die Bezahlseite des Dienstleisters.

---

## 7. Einbindung in Kluft

- **SB-80** MUSS: Funktionsschalter `sammelbestellung` in `App\Support\Features::KATALOG` mit `'ebene' => 'Stamm'`, Standard **aus**. Abgeschaltet heißt: keine Route, kein Menüpunkt, kein Backend-Bereich. DV und Bezirk sehen die Funktion nicht.
- **SB-81** SOLL: Ab Ausbaustufe 4.
- **SB-82** MUSS: Eigene Tabellen (Artikel/Cache, Varianten, Sperrliste, Runden, Bestellungen, Positionen, Zahlungen, Ausgaben). Keine Änderungen an Tabellen anderer Bereiche. Schema-Migrationen unter `database/migrations`, Startinhalt für Roden (Grundausstattung, Sperren) unter `instanzen/roden/migrations` (`docs/entscheidungen/kluft-inhalt-und-schema.md`).
- **SB-83** SOLL: Vorhandenes wiederverwenden: Double-Opt-in, Einwilligung und Löschfristen der Veranstaltungsanmeldungen, Spamschutz des Kontaktformulars, NaMi-Abgleich, Rollen wie „Kasse“, Mediathek für Bilder, Mailversand über die Warteschlange, PDF-Erzeugung wie bei den Teilnahmebescheinigungen.
- **SB-84** MUSS: Braucht den Cronjob (Warteschlange für Mails, Erinnerungen, Löschfristen); `/api/status` zeigt das.
- **SB-85** MUSS: Tests für Freigabe, Sperren, Bestellen, Bestätigen, Ändern, Zahlungsaufforderung, Zahlungseingang, Schließen mit Storno, Sammelliste, Ausgabeliste, Rechte und Schalter (aus = 404). Der Rüsthaus-Abruf wird mit gespeicherten Beispielseiten getestet, nicht live. Grün für alle drei Instanzen.

---

## 8. Datenschutz (KDG)

- **SB-70** MUSS: Gespeichert wird nur: Name, E-Mail, optional Telefon der bestellenden Person; Vor- und Nachname und Stufe des Mitglieds; Positionen; Ergebnis der Mitgliedsprüfung (ja/nein, keine NaMi-Daten); Zahlungsstatus und Verwendungszweck; Ausgabe (Datum, wer abgehakt hat). Keine Adressen, keine Geburtsdaten, keine Kontodaten.
- **SB-71** MUSS: Löschfrist: Personenbezogene Daten einer Runde werden **6 Monate nach Abschluss** gelöscht bzw. anonymisiert (Einstellung). Für die Kasse bleiben Summen, Zahlungen ohne Namen und die Rüsthaus-Rechnung. Unbestätigte Bestellungen: 48 Stunden. Unterschriebene Ausgabelisten auf Papier bewahrt der Stamm nach seinen eigenen Regeln auf.
- **SB-72** MUSS: Textbaustein für die Datenschutzerklärung wird mitgeliefert und beim Einschalten angezeigt, mit Zusatz für Stripe, falls eingeschaltet.
- **SB-73** MUSS: Die Sammelliste (SB-41) enthält keine Namen. Ausgabeliste und -scheine (SB-42) enthalten Namen und sind nur für Sammelbestellerin, Verwaltung und Admin sichtbar.
- **SB-74** MUSS: Alle Änderungen im Backend stehen im Änderungsprotokoll (Audit-Log).

---

## 9. Abnahme

Abgenommen ist die Funktion, wenn in Roden auf einer Testrunde:

1. Die Sammelbestellerin die Grundausstattung per Artikelnummer freigibt (Bild und Beschreibung erscheinen im lokalen Shop) und die Zelt-Kategorien gesperrt sind.
2. Sie eine Runde mit Bestelldatum öffnet.
3. Zwei Testbestellungen eingehen: eine aus dem Shop (die Zahlungsaufforderung kommt sofort), eine mit einem freien Artikel (die Zahlungsaufforderung kommt nach Bestätigung). Ein gesperrter Artikel (Gruppenzelt) wird abgewiesen.
4. Die Kasse eine Zahlung abhakt; die andere Bestellung bekommt vor dem Bestelldatum eine Erinnerung und wird beim Schließen storniert.
5. Die Sammelliste nur die bezahlte Bestellung enthält und als Excel herunterladbar ist.
6. Die Rüsthaus-Rechnung eingetragen wird und die Differenz stimmt.
7. Die Ausgabeliste als PDF mit Unterschriftsspalte gedruckt wird und die Verwaltung „ausgegeben“ abhakt.
8. Abgeschaltet `/bestellen` 404 liefert und im Backend nichts zu sehen ist; auf dv-trier und saar-hochwald gibt es die Funktion nicht.
9. Nach testweise verkürzter Löschfrist die Personendaten weg und die Summen noch da sind.

---

## 10. Offene Fragen

1. **Grundausstattung genau:** Welche Artikelnummern?
   - Klufthemd und -bluse: langarm, halbarm oder beides?
   - Welches Halstuch?
   - Welche Basisaufnäher?

   Hat der Stamm eigene Artikel, die nicht vom Rüsthaus kommen (z. B. ein Stammeshalstuch)? Die bräuchten einen Artikel ohne Rüsthaus-Nummer.
2. **Sperrliste:** Außer Zelten noch etwas, z. B. Jurten-Zubehör, Fahnen, Großgeräte?
3. **Rüsthaus:** Bestätigung zur Übernahme von Bild und Beschreibung schriftlich ablegen (Mail genügt).
4. **Stripe:** Ist ein Online-Zahlungsweg überhaupt gewünscht, oder reicht die Überweisung? Falls ja: Wer ist Kontoinhaber bzw. Vertragspartner?
5. **Name auf der Website:** „Ausrüstung bestellen“, „Kluft-Bestellung“ oder „Rüsthaus-Sammelbestellung“?

---

## 11. Umsetzung in Phasen (Vorschlag, nicht bindend)

| Phase | Inhalt | Ergebnis |
|---|---|---|
| **1** | Freigabe per Artikelnummer mit Cache (Bild, Beschreibung, Varianten, Preis), Sperrliste, lokaler Shop, manuelle Runden mit Bestelldatum, Bestellformular inkl. freier Artikel, Double-Opt-in, Zahlungsaufforderung mit GiroCode, Kasse hakt ab, Sammelliste mit Export, Rolle „Sammelbestellung“, Schalter | Michele kann die erste Runde fahren. |
| **2** | NaMi-Prüfung, Erinnerung und Storno zum Bestelldatum, Ausgabeliste und -scheine als PDF mit Unterschrift, Abhaken durch Verwaltung, Rüsthaus-Rechnung, Löschfrist | Ablauf vollständig. |
| **3** | Zahlungsbaustein mit Stripe (SB-91), gemeinsam mit Veranstaltungen nach Absprache mit der Haupt-Sitzung | Online-Zahlung. |
| später | Lagerbestand des Stamms, Kontoauszug-Import, Übernahme in den Einrichtungsassistenten für andere Stämme | nach Bedarf |

**Stand der Umsetzung (23.10.2026):** Phase 1 ist gebaut. Öffentlich
unter `/bestellen`, im Backend unter „Sammelbestellung“ (Artikel,
Sperrliste, Bestellrunden, Bestellungen). Freie Artikel bestätigt die
Sammelbestellung mit Preis; danach geht die Nachforderung raus. Die
Sammelliste (CSV) enthält nur bestätigte und bezahlte Positionen.
Rolle `sammelbestellung` (Haupt-Vorschlag war `material`). Zahlungen
laufen über den Baustein „Zahlungen“ mit Präfix `RH`, fällig zum
Bestelldatum.

Für andere Stämme gilt die Funktion genauso. Sie ist standardmäßig aus und
wird je Stamm unter „Verwaltung → Funktionen“ eingeschaltet.
