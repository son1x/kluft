# Auslagen und Fahrtkosten erstatten

Funktion „Auslagen erstatten“ unter Verwaltung → Funktionen (Standard: aus).
Backend → Mein Konto → **Auslagen erstatten**.

## Einreichen (alle mit aktivem Konto)

- Umschalter **Auslage** oder **Fahrtkosten**.
- Auslage: wofür, Datum, Betrag, **Beleg als Foto oder PDF** (Pflicht, bis 10
  Dateien à 10 MB). Die Belege liegen privat in `storage/app/private/auslagen`.
- Fahrt: wofür, Tag, Kilometer (hin und zurück), Strecke. Erstattet wird der
  Kilometersatz, den die Kasse einstellt (Standard 0,30 €/km). Der Satz wird
  beim Einreichen an der Auslage festgehalten.
- Bankverbindung (Kontoinhaber*in, IBAN mit Prüfung). „Für die nächste
  Erstattung merken“ speichert sie verschlüsselt am Konto und füllt sie beim
  nächsten Mal vor.
- Solange nichts geprüft ist, lässt sich die Auslage noch ändern oder
  zurückziehen. Jede Person sieht nur ihre eigenen.

## Kasse

Prüfen dürfen im **Stamm** die Rolle „Kasse“, im **DV oder Bezirk** Kasse und
Verwaltung (Admins ebenso). Das Menü zeigt die Zahl der offenen Auslagen; bei
jeder neuen Auslage geht eine Mail an Kasse bzw. Verwaltung.

- Reiter: Zu prüfen · Zum Auszahlen · Erledigt · Alle.
- **Freigeben**, oder **Ablehnen** mit Grund (geht per Mail an die Person).
- Auszahlen: Auslagen markieren → **SEPA-Datei für die Bank** (pain.001.001.03,
  Auftraggeber ist die Bankverbindung unter Kasse → Zahlungen → Bankverbindung)
  im Online-Banking hochladen. Danach **Als ausgezahlt markieren** (oder einzeln
  „Ausgezahlt“ mit Datum). Die Person bekommt eine Mail.
- **Kilometersatz** oben rechts.

## Datenschutz und Fristen

- Die IBAN an der Auslage wird bei „ausgezahlt“ oder „abgelehnt“ gelöscht; die
  gemerkte am Konto bleibt, bis die Person das Häkchen beim nächsten Einreichen
  abwählt.
- `auslagen:aufraeumen` (täglich 04:05): ausgezahlte Auslagen samt Belegen
  werden nach **10 Jahren** zum Jahresende gelöscht (Aufbewahrung nach
  Steuerrecht), abgelehnte nach einem Jahr.
- Die Datenschutzauskunft (Verwaltung → Datenschutz) listet die Auslagen zur
  Adresse. Gelöscht werden sie dort wegen der Aufbewahrungspflicht nicht.
