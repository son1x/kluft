# Zahlungen

Gemeinsamer Baustein für Geld, das Mitglieder, Eltern oder Teilnehmende an die
Gliederung zahlen: Sammelbestellung, später Teilnahmebeiträge von
Veranstaltungen, auf Wunsch Mitgliedsbeiträge von Überweisern. Er ist für alle
Ebenen (Stamm, Bezirk, Diözese) gedacht.

## Einschalten

1. **Funktion einschalten:** Verwaltung → Funktionen → **Zahlungen** (Standard aus).
2. **Wer es sieht:** Im Stamm die Rolle „Kasse“ und Admins; bei Bezirk und Diözese Verwaltung, Kasse und Admins.
3. **Bankverbindung hinterlegen:** Kasse → Zahlungen → **Bankverbindung**, mit Kontoinhaber, IBAN (Prüfsumme wird geprüft) und optional BIC.
   - Steht nur in der Datenbank, nicht im Repository.
   - Ohne Bankverbindung gibt es keine Zahlungsaufforderung.

## Arbeiten

- **Liste:** Kasse → Zahlungen zeigt alle Forderungen mit Verwendungszweck, Betrag, Eingang, Status (offen, teilweise bezahlt, bezahlt, erstattet, storniert) und Fälligkeit. Das Menü zeigt die Zahl der offenen.
- **Eingang:** den eingegangenen Betrag erfassen, auch als Teilzahlung. Mehrere auf einmal: auswählen und „Als bezahlt markieren“, z. B. nach dem Blick auf den Kontoauszug.
- **Erstattung:** Betrag und Datum, z. B. wenn ein Artikel nicht lieferbar ist.
- **Stornieren:** Die offene Forderung entfällt, bleibt aber sichtbar.
- **Neue Forderung:** für Einzelfälle ohne Modul (Zweck, Betrag, Fälligkeit). Auf Wunsch geht sofort die Zahlungsaufforderung an Name und E-Mail; beides wird nicht gespeichert.
- **Protokoll:** Jede Aktion steht im Protokoll.

## Teilnahmebeiträge von Veranstaltungen

- **Preisstufen:** Bei einer Veranstaltung unter „Anmeldung“ → **Teilnahmebeitrag**, z. B. „Mitglied 120 €“, „Nichtmitglied 140 €“, „Geschwisterkind 100 €“. Leer heißt kein Beitrag. Dazu optional die **Zahlungsfrist** in Tagen (Standard 14, höchstens bis zum Vortag des Beginns).
- **Anmeldung:** Die Seite zeigt die Stufen. Wer sich anmeldet, wählt eine; bei nur einer Stufe ist sie vorausgewählt.
- **Forderung:** entsteht erst, wenn der Platz fest ist:
  - nach der Bestätigung per Link;
  - beim Nachrücken von der Warteliste;
  - beim Eintragen im Backend mit Status „bestätigt“.

  Dann geht die Zahlungsaufforderung mit GiroCode an die angegebene E-Mail. Verwendungszweck `VA-2026-0041`, Zweck „Veranstaltung (Datum) – Preisstufe“.
- **Warteliste:** Wer auf der Warteliste steht, zahlt nichts. Wird eine bestätigte Anmeldung auf die Warteliste verschoben, wird die offene Forderung storniert.
- **Storno:** Eine offene Forderung wird storniert. Eine bezahlte bleibt stehen und erscheint unter „Kasse → Zahlungen“ mit dem Hinweis „Anmeldung storniert – Erstattung prüfen“; die Kasse erfasst dann die Erstattung.
- **Teilnehmendenliste:** Spalten „Beitrag“ und „Zahlung“ (Stand, Verwendungszweck), dazu die Aktion **Beitrag**: Preisstufe festlegen oder ändern (eine offene Forderung wird dann ersetzt) und die Aufforderung erneut schicken. Der Export enthält Teilnahmebeitrag, Betrag, Zahlungsstand und Verwendungszweck.
- **Meine Anmeldungen:** zeigt Beitrag und Stand, bei offener Zahlung auch Kontodaten, Verwendungszweck und GiroCode.
- **Ohne Funktion „Zahlungen“ oder ohne Bankverbindung:** Der Beitrag steht nur als Information an der Anmeldung, es entstehen keine Forderungen. Wird die Funktion später eingeschaltet, legt die Aktion „Beitrag“ die Forderung nachträglich an.
- **Später geplant:** Ratenzahlung und Kostenteilung (z. B. Zuschuss des Bezirks).

## Zahlungsaufforderung

Die Mail enthält:
- Empfänger, IBAN, BIC, Betrag, Fälligkeit und den **Verwendungszweck**, z. B. `RH-2026-0012`;
- einen **GiroCode** (EPC-QR nach EPC069-12). Die Banking-App liest daraus alle Angaben.

Mit dem eindeutigen Verwendungszweck findet die Kasse die Zahlung auf dem Kontoauszug.

**Zahlungswege:** Phase 1 ist die Überweisung. Der Weg ist austauschbar (`App\Services\Zahlungen\Zahlungsweg`).
- Später ist z. B. Stripe Checkout denkbar: eigener Schalter, Webhook mit Signaturprüfung, Schlüssel nur als Secrets.
- Karten- oder Kontodaten der Zahlenden speichert Kluft nie.

## Für Module (Entwicklung)

```php
use App\Support\Zahlungen;

// Forderung am eigenen Datensatz anlegen (Präfix 2–4 Großbuchstaben je Modul)
$zahlung = Zahlungen::anlegen($bestellung, 1250, 'RH', 'Sammelbestellung Herbst 2026', faelligAm: '2026-11-15');

// Aufforderung mit GiroCode verschicken (Name/Mail kommen vom Modul)
Zahlungen::auffordern($zahlung, $bestellung->email, $bestellung->name);

// Auf den vollständigen Eingang reagieren
Event::listen(\App\Events\ZahlungEingegangen::class, function ($e) {
    if ($e->zahlung->zahlbar instanceof Bestellung) { /* als bezahlt markieren */ }
});
```

- **Tabelle `zahlungen`:**
  - Beträge in Cent;
  - polymorph am Auslöser (`zahlbar_type/zahlbar_id`);
  - `referenz` eindeutig, höchstens 35 Zeichen, nur A–Z, 0–9 und `-`.
- **Nur über das Model ändern:** Eingang, Erstattung und Storno über `Zahlung::eingang()`, `erstattung()`, `stornieren()`, damit Status und Ereignis stimmen.
- **Präfixe:**

  | Präfix | Modul |
  |---|---|
  | `ZA` | von Hand |
  | `RH` | Rüsthaus / Sammelbestellung |
  | `VA` | Teilnahmebeiträge von Veranstaltungen (`App\Support\Teilnahmebeitraege`) |
  | `MB` | Mitgliedsbeiträge |

## Datenschutz

- **Keine Personendaten in `zahlungen`:** Name und E-Mail kennt nur das Modul (Bestellung, Anmeldung). Die Aufforderungsmail nutzt sie, gespeichert werden sie hier nicht.
- **Buchungsbelege:** Beträge, Daten und Verwendungszweck sind Buchungsbelege der Kasse. Wird der Datensatz eines Moduls gelöscht, bleibt die Zahlung ohne Personenbezug stehen.
- **Bankverbindung:** Sie ist die der Gliederung und keine personenbezogene Angabe der Zahlenden.
