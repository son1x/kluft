# Prävention: Nachweise der Leitenden

Backend → Verband & Kontakt → **Prävention**. Funktion „Präventions-Nachweise“
unter Verwaltung → Funktionen (Standard: aus).

## Was gespeichert wird

Je leitender Person (aus „Personen“ übernommen oder nur mit Namen):

| Art | Datum | Gültig bis |
| --- | --- | --- |
| Erweitertes Führungszeugnis | eingesehen am | Vorbelegung: Datum + 5 Jahre |
| Präventionsschulung (z. B. Basisschulung) | teilgenommen am | Vorbelegung: Datum + 5 Jahre |
| Juleica | ausgestellt am | laut Karte |

Gespeichert werden **nur Daten**: keine Kopie und keine Inhalte des
Führungszeugnisses (§ 72a SGB VIII erlaubt nur Einsichtnahme, Datum und ob ein
Eintrag vorliegt). Die Vorbelegung von 5 Jahren bitte an die Regel des
Bistums bzw. Verbands anpassen.

## Wer

Sehen und pflegen dürfen **Vorstand** und **Verwaltung** (Admins ebenso).

## Ampel und Erinnerungen

- Liste nach Person gruppiert, Status: Gültig, „Noch N Tage“ (ab 90 Tagen vor
  Ablauf), Abgelaufen. Das Menü zeigt die Zahl der fälligen Nachweise.
- `praevention:erinnerungen` (täglich 08:30): 90 und 30 Tage vor Ablauf je
  einmal eine Mail an die Person (falls Adresse hinterlegt) und eine Übersicht
  an den Vorstand (ohne Vorstand: an die Verwaltung). Ein neues Datum setzt die
  Erinnerungen zurück.

## Löschen

Ein Jahr nach Ablauf löscht der tägliche Lauf den Nachweis (Protokoll
„Präventions-Nachweise gelöscht“). Wird eine Person unter „Personen“
gelöscht, gehen ihre Nachweise mit. Datenschutzauskunft und -löschung
(Verwaltung → Datenschutz) erfassen die Nachweise zur E-Mail-Adresse.
