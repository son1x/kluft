# Pflichtenheft: Sammelbestellung beim Rüsthaus („Schattenshop“)

**Stand:** 19. Oktober 2026 · **Status:** Entwurf, 2. Fassung (Antworten des Stamms Roden eingearbeitet) · **Bezug:** Kluft (`docs/pflichtenheft.md`, `docs/beitraege.md`, `docs/mitgliederabgleich.md`)

Mitglieder des Stamms bzw. ihre Erziehungsberechtigten bestellen Kluft,
Halstuch, Aufnäher und andere Artikel aus dem Rüsthaus über die
Stammes-Website. Die zuständige Person (in Roden: Michele Franke,
Rüsthaus-Bestellungen) öffnet und schließt die Bestellrunden von Hand,
prüft die Bestellungen, fordert die Bezahlung an und bestellt gesammelt
beim Rüsthaus, sobald bezahlt ist. Danach verteilt sie die Ware.

Anforderungen tragen IDs der Form `SB-<Nr>`. **MUSS** = Pflicht, **SOLL** =
Pflicht außer mit begründeter Ausnahme, **KANN** = optional.

---

## 1. Ziel, Abgrenzung, Entscheidungen

### 1.1 Ziel

1. **Weg mit Zetteln, WhatsApp und Excel:** Bestellungen kommen strukturiert an (Artikel, Rüsthaus-Artikelnummer, Größe, Anzahl, für welches Mitglied).
2. **Vorkasse:** Beim Rüsthaus wird nur bestellt, was bezahlt ist. Der Stamm geht kein Risiko ein.
3. **Eine Sammelbestellung je Runde:** Michele sieht auf einen Blick, was zu bestellen ist, nach Artikelnummer und Größe summiert.
4. **Überblick für die Kasse:** Zahlungseingänge, offene Beträge und die Provision des Stamms sieht Hendrik Schuler (Kasse).
5. **Wenig Pflegeaufwand:** Grundausstattung fest im Katalog, alles andere frei über die Rüsthaus-Artikelnummer.

### 1.2 Entscheidungen des Stamms (19.10.2026)

| Frage | Entscheidung |
|---|---|
| Wer darf bestellen? | Nur Mitglieder des Stamms bzw. ihre Erziehungsberechtigten. |
| Bezahlung | Vorab. |
| Zuschüsse | Keine. Der Stamm bekommt eine Provision vom Rüsthaus. |
| Katalog | Grundausstattung: Klufthemd bzw. -bluse, Halstuch, Basisaufnäher. Zusätzlich frei bestellbar per Rüsthaus-Artikelnummer. |
| Bestellrunden | Werden von Hand geöffnet und geschlossen. |
| Zahlungsdienstleister | Noch offen, Bewertung in Kapitel 6. |

### 1.3 Was es nicht ist

- **Kein Nachbau des Rüsthaus-Shops:** Der Katalog enthält die Grundausstattung; der Rest läuft über die Artikelnummer (SB-14).
- **Keine Bestellung beim Rüsthaus aus Kluft heraus:** Das Rüsthaus hat keine öffentliche Bestell-Schnittstelle. Michele bestellt dort selbst; Kluft liefert die fertige Liste (SB-41).
- **Kein Lager:** Restbestände des Stamms KANN eine spätere Ausbaustufe abbilden (Kapitel 11).

---

## 2. Begriffe

| Begriff | Bedeutung |
|---|---|
| **Katalogartikel** | Fest angebotener Artikel (Grundausstattung) mit Rüsthaus-Artikelnummer, Varianten und Preis. |
| **Freier Artikel** | Beliebiger Rüsthaus-Artikel, den jemand über Artikelnummer oder Link bestellt. Der Preis gilt erst, wenn Michele ihn bestätigt hat. |
| **Variante** | Größe oder Ausführung, beim Rüsthaus mit eigener Artikelnummer (z. B. `0100003.30` = Halbarm-Klufthemd, Kragenweite 30). |
| **Bestellrunde** | Zeitraum zwischen manuellem Öffnen und Schließen. |
| **Bestellung** | Was eine Person in einer Runde für ein oder mehrere Mitglieder bestellt: eine oder mehrere Positionen. |
| **Zahlungsaufforderung** | Mail mit Betrag, Zahlungsfrist und Zahlungsweg, nachdem Michele die Bestellung geprüft hat. |
| **Sammelliste** | Alle bezahlten Positionen einer Runde, nach Artikelnummer summiert: das, was beim Rüsthaus bestellt wird. |
| **Provision** | Betrag, den der Stamm vom Rüsthaus für die Bestellungen erhält. |

---

## 3. Rollen

| Rolle | Wer (Roden) | Darf |
|---|---|---|
| **Besteller*in** (öffentlich, ohne Konto) | Mitglieder, Erziehungsberechtigte | Bestellen, eigene Bestellung über den Link in der Mail ansehen, ändern oder stornieren, solange die Runde offen ist; bezahlen. |
| **Material** (neue Rolle) | Michele Franke | Katalog pflegen, Runden öffnen und schließen, Bestellungen prüfen, freie Artikel bestätigen, Zahlungsaufforderungen auslösen, Sammel- und Ausgabeliste, Ausgabe abhaken. |
| **Kasse** (vorhanden) | Hendrik Schuler | Zahlungseingänge abhaken, offene Beträge, Erstattungen, Provision; nicht den Katalog ändern. |
| **Admin** | Stammesvorstand | alles, Funktion ein- und ausschalten. |

- **SB-01** MUSS: Die Rolle „Material“ wird wie „Kasse“ unter „Benutzer“ vergeben und gilt nur auf Stammes-Websites. Rollen gehören zum gemeinsamen Kern; die Rolle wird mit der Haupt-Sitzung abgestimmt (`docs/abstimmung.md`).
- **SB-02** MUSS: Ohne Rolle „Material“, „Kasse“ oder Admin ist im Backend nichts davon sichtbar.

---

## 4. Ablauf einer Bestellrunde

1. **Öffnen (von Hand):** Michele öffnet eine Runde, z. B. „Herbst 2026“, optional mit einem angepeilten Termin („wir bestellen voraussichtlich Mitte November“). Auf Wunsch geht eine Mail an die Leitenden mit dem Link.
2. **Bestellen:** Mitglieder bzw. Erziehungsberechtigte bestellen auf der Website für ein oder mehrere Mitglieder und bestätigen per Mail-Link.
3. **Schließen (von Hand):** Michele schließt die Runde. Danach sind keine neuen Bestellungen und keine Änderungen durch Besteller*innen mehr möglich.
4. **Prüfen:** Michele prüft je Bestellung:
   - Ist das Mitglied Mitglied im Stamm? (NaMi-Abgleich, SB-37)
   - Freie Artikel: Artikelnummer, Größe und Preis stimmen, der Artikel ist lieferbar.
   - Positionen streichen oder korrigieren; die Person bekommt eine Mail.
5. **Zahlungsaufforderung:** Mit einem Klick erhalten alle geprüften Bestellungen ihre Zahlungsaufforderung (Betrag, Frist, Zahlungsweg nach Kapitel 6).
6. **Zahlungseingang:** Bezahlt wird online (automatisch erkannt) oder per Überweisung (Kasse hakt ab). Nach Ablauf der Frist unbezahlte Bestellungen: Erinnerung oder Storno (Michele entscheidet).
7. **Rüsthaus-Bestellung:** Die Sammelliste enthält nur bezahlte Positionen. Michele bestellt beim Rüsthaus und markiert die Runde als „bestellt“.
8. **Lieferung und Ausgabe:** Michele markiert „geliefert“. Die Besteller*innen bekommen eine Mail („Ware ist da, Abholung …“). Bei der Übergabe wird „ausgegeben“ abgehakt.
9. **Abschluss:** Provision eintragen, Runde erledigt. Danach läuft die Löschfrist (SB-71).

Statusfolge einer Runde: `offen` → `geschlossen` → `zahlung` → `bestellt` → `geliefert` → `erledigt`.

---

## 5. Funktionale Anforderungen

### 5.1 Katalog und Rüsthaus-Daten

- **SB-10** MUSS: Katalogartikel mit Name, kurzer Beschreibung, Rüsthaus-Link, Kategorie, aktiv/inaktiv, Reihenfolge. Je Variante eine Rüsthaus-Artikelnummer und ein Preis.
- **SB-11** MUSS: Grundausstattung als Startinhalt für Roden: Klufthemd, Kluftbluse (jeweils lang/halbarm nach Wahl des Stamms), Halstuch, Basisaufnäher. Michele kann weitere Artikel aufnehmen.
- **SB-12** SOLL: **Daten aus dem Rüsthaus übernehmen:** Michele gibt eine Artikelnummer oder einen Rüsthaus-Link ein. Kluft ruft **diese eine** Produktseite ab und füllt Name, Preis und Varianten (Größen mit Artikelnummer) vor. Michele prüft und speichert. Grundlage: Die Produktseiten des Rüsthaus-Shops (Shopware) enthalten Artikelnummer (`data-ordernumber`), Preis (`itemprop="price"`) und Größenauswahl; Produktseiten sind laut `robots.txt` nicht gesperrt. Siehe Grenzen in SB-15.
- **SB-13** SOLL: „Preise prüfen“: Für alle aktiven Katalogartikel die Preise beim Rüsthaus abrufen und Abweichungen anzeigen, nur auf Knopfdruck (z. B. beim Öffnen einer Runde), nicht automatisch im Hintergrund.
- **SB-14** MUSS: **Freie Artikel** im Bestellformular: Artikelnummer oder Rüsthaus-Link eingeben, Kluft zeigt Name, Preis und Größen wie in SB-12 an. Klappt der Abruf nicht, gibt die Person Bezeichnung und Größe selbst ein. Freie Positionen sind mit „Preis wird von Michele bestätigt“ gekennzeichnet; bezahlt wird erst nach der Bestätigung (Ablauf, Schritt 4–5).
- **SB-15** MUSS: **Grenzen des Abrufs beim Rüsthaus:**
  - nur Einzelabrufe auf Anlass (Eingabe, Knopfdruck), kein Durchsuchen des Shops; höchstens eine Anfrage pro Sekunde, Ergebnis 24 Stunden zwischengespeichert;
  - eigene Kennung im User-Agent (`Kluft/… (+https://www.dpsg-roden.de)`);
  - **keine Produktfotos kopieren** (Urheberrecht); höchstens ein Link zur Produktseite. Eigene Fotos des Stamms sind möglich;
  - fällt der Abruf aus oder ändert sich die Seite, geht alles weiter mit Handeingabe. Der Abruf ist eine Hilfe, keine Voraussetzung;
  - vor dem Einschalten beim Rüsthaus nachfragen, ob das in Ordnung ist bzw. ob es für Stämme eine Artikelliste (CSV) gibt; das wäre die bessere Quelle (Kapitel 10, Frage 3).
- **SB-16** MUSS: Der Preis wird bei der Bestellung festgehalten. Ändert sich der Rüsthaus-Preis vor der Zahlungsaufforderung, passt Michele ihn an; die Person bekommt den neuen Betrag in der Zahlungsaufforderung. Nach der Zahlung ändert sich der Preis nicht mehr.

### 5.2 Bestellrunden

- **SB-20** MUSS: Runde mit Name, optionalem angepeiltem Bestelltermin (nur Anzeige), Status (Kapitel 4), Zahlungsfrist in Tagen (Standard 10).
- **SB-21** MUSS: Öffnen und Schließen nur von Hand durch „Material“. Höchstens eine Runde ist offen. Ist keine offen, zeigt `/bestellen` den Katalog und einen pflegbaren Hinweis („Die nächste Sammelbestellung startet …“).
- **SB-22** KANN: Erinnerung an „Material“, wenn eine Runde länger als X Wochen offen ist (Standard aus).

### 5.3 Bestellen (öffentlich)

- **SB-30** MUSS: Seite „Ausrüstung bestellen“ (`/bestellen`), im Menü abschaltbar wie jede Seite, Einleitungstext pflegbar.
- **SB-31** MUSS: Formular:
  - besteller*in: Name, E-Mail, optional Telefon; Angabe „Ich bin selbst Mitglied“ oder „Ich bin erziehungsberechtigt für das Mitglied“;
  - je Mitglied: Vor- und Nachname, Stufe; Positionen aus dem Katalog (Variante, Anzahl) oder frei (SB-14);
  - Bemerkung, gewünschte Abholung (Gruppenstunde der Stufe, nach Absprache).
- **SB-32** MUSS: Bestätigung per Mail-Link (Double-Opt-in wie bei Veranstaltungsanmeldungen). Unbestätigte Bestellungen verfallen nach 48 Stunden.
- **SB-33** MUSS: Über den Link in der Mail kann die Person ihre Bestellung ansehen, solange die Runde offen ist ändern und stornieren, und später bezahlen und den Status sehen (geprüft, Zahlung offen, bezahlt, bestellt, abholbereit, ausgegeben).
- **SB-34** MUSS: Einwilligung (Datenverarbeitung, Löschfrist) und Bestätigung, Mitglied bzw. erziehungsberechtigt zu sein. Texte wie bei Veranstaltungen (`event_consent`).
- **SB-35** MUSS: Vorläufige Summe mit dem Hinweis: Bezahlt wird nach Prüfung auf Zahlungsaufforderung; freie Artikel zum bestätigten Preis.
- **SB-36** SOLL: Spamschutz wie beim Kontaktformular (Honeypot, Zeitprüfung, Drosselung).
- **SB-37** MUSS: **Nur Mitglieder:** Im Backend bei jeder Bestellung „Mitgliedschaft prüfen“ über den vorhandenen NaMi-Abgleich (`docs/mitgliederabgleich.md`, Name und Stamm). Ergebnis als Ampel. Ohne Treffer entscheidet Michele (z. B. Neumitglied noch nicht in NaMi). Eltern müssen keine Mitgliedsnummer kennen.

### 5.4 Backend für „Material“

- **SB-40** MUSS: Bestellungen einer Runde mit Filter (Status, Stufe, Artikel, Prüfung offen), bearbeiten, Positionen streichen oder ändern, Bestellung von Hand eintragen.
- **SB-41** MUSS: Sammelliste der **bezahlten** Positionen: je Rüsthaus-Artikelnummer Bezeichnung, Größe und Summe. Export als CSV/Excel und Druckansicht. Damit legt Michele den Warenkorb beim Rüsthaus an.
- **SB-42** MUSS: Ausgabeliste je Stufe bzw. Abholort: wer bekommt was. Druckbar.
- **SB-43** MUSS: Je Position „ausgegeben“ abhaken. Teillieferung: Position als „nachgeliefert“ markieren oder in die nächste Runde übernehmen (bereits bezahlt).
- **SB-44** MUSS: Rundenmails aus Vorlagen: Zahlungsaufforderung, Erinnerung, „Ware ist da“.
- **SB-45** SOLL: Rüsthaus-Rechnung eintragen (Betrag, Versand, Provision), damit Abweichungen zur Summe der Bestellungen sichtbar werden.

### 5.5 Bezahlung, Erstattung, Provision

- **SB-50** MUSS: Vorkasse. In die Sammelliste kommt nur, was vollständig bezahlt ist.
- **SB-51** MUSS: Zahlungswege nach Kapitel 6: mindestens Überweisung mit GiroCode, optional online.
- **SB-52** MUSS: Offene Beträge, Zahlungseingänge und Erstattungen je Runde und gesamt, sichtbar für „Material“ und „Kasse“.
- **SB-53** MUSS: **Erstattung**, wenn ein bezahlter Artikel nicht lieferbar ist oder storniert wird: Kasse erfasst die Erstattung (Überweisung oder beim Online-Zahlungsweg über den Dienstleister) mit Betrag und Datum.
- **SB-54** MUSS: Provision: je Runde ein Feld „Provision vom Rüsthaus“ (Betrag, Datum des Eingangs), Summe je Jahr für die Kasse. Die Verkaufspreise entsprechen den Rüsthaus-Preisen; Kluft schlägt nichts auf.
- **SB-55** SOLL: Versandkosten des Rüsthaus trägt der Stamm (aus der Provision) oder sie werden umgelegt. Einstellung je Runde, Standard „trägt der Stamm“.
- **SB-56** KANN: Erinnerung bei offenem Betrag einen Tag vor Fristende (Standard an).

### 5.6 Benachrichtigungen

- **SB-60** MUSS: Mails an Besteller*innen: Bestätigungslink, „Bestellung eingegangen“, Änderung durch den Stamm, Zahlungsaufforderung, Zahlungseingang, Storno/Erstattung, „Ware ist da“.
- **SB-61** MUSS: Mail an „Material“ bei jeder bestätigten Bestellung (abschaltbar zugunsten einer Tageszusammenfassung).
- **SB-62** MUSS: Absender ist die Website (`MAIL_FROM_*`), Antwort-an ist die eingestellte Adresse (in Roden z. B. michele@dpsg-roden.de).

---

## 6. Zahlungswege: Lohnt sich ein Zahlungsdienstleister?

### 6.1 Die Möglichkeiten

| | **Überweisung mit GiroCode** | **Stripe** | **PayPal** |
|---|---|---|---|
| **Wie** | Zahlungsaufforderung mit Betrag, Verwendungszweck und QR-Code (EPC-QR); Banking-App scannt ihn. | Bezahlseite von Stripe (Checkout): Karte, SEPA-Lastschrift, Apple/Google Pay; PayPal lässt sich dort als Zahlart zuschalten (Verfügbarkeit für den Stamm prüfen). | PayPal-Checkout. |
| **Kosten** | keine | je Zahlung Gebühr, für EU-Karten etwa 1,5 % + 0,25 €; SEPA-Lastschrift geringer, aber mit Wartezeit. Aktuelle Preise beim Anbieter prüfen. | je Zahlung Gebühr, im üblichen Händlertarif deutlich über 2 % plus Festbetrag. Für Vereine gibt es zum Teil Sonderkonditionen, aber meist nur für Spenden. |
| **Beispiel Halstuch 10 €** | 0 € | etwa 0,40 € | etwa 0,60 € |
| **Zahlungseingang erkennen** | Kasse hakt ab (oder später CSV-Import vom Konto) | automatisch (Webhook) | automatisch (Webhook) |
| **Erstattung** | Überweisung durch die Kasse | per Klick, Gebühr wird meist nicht erstattet | per Klick |
| **Voraussetzungen** | Stammeskonto | Konto bei Stripe auf den Rechtsträger mit Identitätsprüfung (KYC), Bankkonto, Ansprechperson. Ob ein nicht eingetragener Stamm das kann oder ob es über den Förderverein (e. V.) laufen muss, ist zu klären. | wie Stripe (PayPal-Geschäftskonto) |
| **Aufwand in Kluft** | gering | mittel: Checkout, Webhook, Erstattung, Secrets im Environment | mittel, wie Stripe |
| **Datenschutz** | nur Kontoauszug | Stripe ist Auftragsverarbeiter, Datenschutzerklärung erweitern | PayPal ist eigener Verantwortlicher, Datenschutzerklärung erweitern |

### 6.2 Empfehlung

1. **Phase 1: Überweisung mit GiroCode.**
   - Kostet nichts und ist schnell gebaut.
   - Passt zur Vorkasse, weil bis zur Rüsthaus-Bestellung ohnehin Zeit ist.
   - Die Kasse hakt Zahlungseingänge ab. Der Verwendungszweck ist eindeutig, z. B. `RH-2026-2-0123`.
2. **Phase 2 bei Bedarf: Stripe als zusätzlicher Weg.**
   - **Für Stripe:** Eltern zahlen sofort, der Eingang wird automatisch erkannt, Erstattungen gehen per Klick.
   - **Lieber Stripe als PayPal:** niedrigere Gebühren, eine Anbindung für mehrere Zahlarten (PayPal dort zuschaltbar), saubere Webhooks.
   - **Gebühren:**
     - Der Stamm trägt sie aus der Provision, oder es gibt einen kleinen Aufschlag nur bei Online-Zahlung.
     - Ein Aufschlag für einzelne Zahlarten ist rechtlich heikel und muss vorher geprüft werden.
3. **Ein Zahlungsmodul für Rüsthaus und Veranstaltungen:**
   - **Aufbau:** Kluft bekommt einen Baustein „Zahlungen“. Er hat einen Betrag, einen Zweck, einen Status und einen austauschbaren Zahlungsweg (Überweisung, Stripe, später weitere).
   - **Nutzer:** Sammelbestellung und Teilnahmebeiträge von Veranstaltungen nutzen ihn gemeinsam.
   - **Absprache nötig:** Veranstaltungen gehören zu allen Ebenen und damit zum gemeinsamen Kern. Das Modul wird mit der Haupt-Sitzung abgestimmt, bevor es gebaut wird.

- **SB-90** MUSS: Zahlungen als eigener Baustein mit austauschbarem Zahlungsweg. Phase 1: Überweisung mit GiroCode (EPC-QR, SEPA).
- **SB-91** KANN: Stripe Checkout mit Webhook-Prüfung (Signatur). Schlüssel nur als Secrets im GitHub-Environment, nie im Repository. Der Online-Zahlungsweg ist je Instanz einschaltbar.
- **SB-92** MUSS: Keine Karten- oder Kontodaten in Kluft speichern. Online-Zahlung läuft ausschließlich über die Bezahlseite des Dienstleisters.

---

## 7. Einbindung in Kluft

- **SB-80** MUSS: Funktionsschalter `sammelbestellung` in `App\Support\Features::KATALOG` mit `'ebene' => 'Stamm'`, Standard **aus**. Abgeschaltet heißt: keine Route, kein Menüpunkt, kein Backend-Bereich. DV und Bezirk sehen die Funktion nicht.
- **SB-81** SOLL: Ab Ausbaustufe 4.
- **SB-82** MUSS: Eigene Tabellen (Artikel, Varianten, Runden, Bestellungen, Positionen, Zahlungen). Keine Änderungen an Tabellen anderer Bereiche. Migrationen mit aktuellem Datum und Uhrzeit.
- **SB-83** SOLL: Vorhandenes wiederverwenden: Double-Opt-in, Einwilligung und Löschfristen der Veranstaltungsanmeldungen, Spamschutz des Kontaktformulars, NaMi-Abgleich, Rollen wie „Kasse“, Mailversand über die Warteschlange.
- **SB-84** MUSS: Braucht den Cronjob (Warteschlange für Mails, Löschfristen); `/api/status` zeigt das.
- **SB-85** MUSS: Tests für Bestellen, Bestätigen, Ändern, Schließen, Prüfen, Zahlungsaufforderung, Zahlungseingang, Sammelliste, Rechte und Schalter (aus = 404). Der Rüsthaus-Abruf wird mit gespeicherten Beispielseiten getestet, nicht live. Grün für alle drei Instanzen.

---

## 8. Datenschutz (KDG)

- **SB-70** MUSS: Gespeichert wird nur: Name, E-Mail, optional Telefon der bestellenden Person; Vor- und Nachname und Stufe des Mitglieds; Positionen; Ergebnis der Mitgliedsprüfung (ja/nein, keine NaMi-Daten); Zahlungsstatus und Verwendungszweck. Keine Adressen, keine Geburtsdaten, keine Kontodaten.
- **SB-71** MUSS: Löschfrist: Personenbezogene Daten einer Runde werden **6 Monate nach Abschluss** gelöscht bzw. anonymisiert (Einstellung). Für die Kasse bleiben Summen, Zahlungen ohne Namen und die Provision. Unbestätigte Bestellungen: 48 Stunden. Steuerliche Aufbewahrungspflichten für Belege liegen bei der Kasse (Kontoauszug, Rüsthaus-Rechnung), nicht in Kluft.
- **SB-72** MUSS: Textbaustein für die Datenschutzerklärung wird mitgeliefert und beim Einschalten angezeigt, mit Zusatz für Stripe, falls eingeschaltet.
- **SB-73** MUSS: Die Sammelliste (SB-41) enthält keine Namen. Die Ausgabeliste (SB-42) enthält Namen und ist nur für „Material“ und Admin sichtbar.
- **SB-74** MUSS: Alle Änderungen im Backend stehen im Änderungsprotokoll (Audit-Log).

---

## 9. Abnahme

Abgenommen ist die Funktion, wenn in Roden auf einer Testrunde:

1. Michele die Grundausstattung prüft, einen Artikel per Artikelnummer übernimmt (SB-12) und eine Runde öffnet.
2. Zwei Testbestellungen eingehen, eine davon mit einem freien Artikel, und bestätigt werden; eine wird geändert, eine dritte storniert.
3. Michele die Runde schließt, die Mitgliedschaft prüft, den freien Artikel bestätigt und die Zahlungsaufforderungen verschickt.
4. Eine Zahlung per Überweisung von der Kasse abgehakt wird, die andere nach Fristende eine Erinnerung bekommt.
5. Die Sammelliste nur die bezahlte Bestellung enthält und als Excel herunterladbar ist.
6. „Ware ist da“ verschickt, „ausgegeben“ abgehakt und die Provision eingetragen wird.
7. Abgeschaltet `/bestellen` 404 liefert und im Backend nichts zu sehen ist; auf dv-trier und saar-hochwald gibt es die Funktion nicht.
8. Nach testweise verkürzter Löschfrist die Personendaten weg und die Summen noch da sind.

---

## 10. Offene Fragen

1. **Rechtsträger und Konto:** Auf welches Konto fließt das Geld: Stammeskonto oder Förderverein? Das entscheidet auch, wer einen Stripe-Vertrag schließen könnte.
2. **Provision und Steuer:** Wie ist die Provision steuerlich zu behandeln (Stamm bzw. Förderverein, Gemeinnützigkeit)? Bitte mit der Kasse bzw. der Diözese klären. Kluft erfasst nur die Beträge.
3. **Rüsthaus fragen:**
   - Ist der Abruf einzelner Produktseiten in Ordnung (SB-15)?
   - Gibt es eine Artikelliste für Stämme?
   - Wie genau läuft die Provision (Kundennummer des Stamms)?
4. **Grundausstattung genau:**
   - Klufthemd und -bluse: nur langarm oder auch halbarm?
   - Welches Halstuch: das Stammeshalstuch (falls es das gibt, nicht vom Rüsthaus)?
   - Welche Basisaufnäher?
5. **Zahlungsfrist:** 10 Tage als Standard in Ordnung?
6. **Versandkosten:** Trägt sie der Stamm?
7. **Ausgabe:** Dürfen die Stufenleitungen „ausgegeben“ abhaken oder nur Michele?
8. **Name auf der Website:** „Ausrüstung bestellen“, „Kluft-Bestellung“ oder „Rüsthaus-Sammelbestellung“?

---

## 11. Umsetzung in Phasen (Vorschlag, nicht bindend)

| Phase | Inhalt | Ergebnis |
|---|---|---|
| **1** | Katalog mit Grundausstattung, manuelle Runden, Bestellformular mit freien Artikeln (Handeingabe), Double-Opt-in, Prüfung inkl. NaMi, Zahlungsaufforderung mit GiroCode, Kasse hakt ab, Sammelliste mit Export, Rolle „Material“, Schalter | Michele kann die erste Runde fahren. |
| **2** | Rüsthaus-Abruf (SB-12 bis SB-14), Ausgabe- und Lieferstatus, Erinnerungen, Provision, Löschfrist | Ablauf vollständig, weniger Tipparbeit. |
| **3** | Zahlungsbaustein mit Stripe (SB-91), gemeinsam mit Veranstaltungen nach Absprache mit der Haupt-Sitzung | Online-Zahlung. |
| später | Lagerbestand des Stamms, Kontoauszug-Import, Übernahme in den Einrichtungsassistenten für andere Stämme | nach Bedarf |

Für andere Stämme gilt die Funktion genauso. Sie ist standardmäßig aus und
wird je Stamm unter „Verwaltung → Funktionen“ eingeschaltet.
