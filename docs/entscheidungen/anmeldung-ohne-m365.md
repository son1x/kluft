# Anmeldung der Redaktion ohne eigenen Microsoft-365-Mandanten

**Stand:** 5. Oktober 2026 · **Status:** Vorschlag, nicht gebaut

## Frage

Kluft meldet Redakteur*innen heute nur über Microsoft Entra ID (M365) an, Single-Tenant mit dem Mandanten der Gliederung. Dazu gibt es einen lokalen Notfall-Zugang mit Passwort und TOTP. Viele Bezirke und manche Diözesanverbände haben aber keinen eigenen M365-Mandanten. Wie melden sich deren Redakteur*innen an?

Was heute schon da ist und wiederverwendet werden kann:
- **Freischaltung:** Eine unbekannte Anmeldung legt ein Konto „wartet auf Freischaltung“ an, ohne Rechte, bis die Verwaltung eine Rolle vergibt.
- **E-Mail-Einmalcodes:** „Meine Anmeldungen“ meldet Teilnehmende per Code an.
- **TOTP:** gibt es beim Notfall-Zugang.

## Möglichkeiten

| | A: Gäste im Mandanten einer anderen Gliederung | B: Jedes Microsoft-Konto (Multi-Tenant) | C: E-Mail-Code + TOTP | D: Anmeldung über NaMi 3 (hitobito, OIDC) |
|---|---|---|---|---|
| Idee | Der DV lädt die Bezirks-Redaktion als Gäste in seinen Mandanten ein; die Bezirks-Instanz nutzt die App-Registrierung des DV | `ENTRA_TENANT_ID=common`, App für alle Konten registriert | Anmeldung mit E-Mail-Adresse, Code per Mail, dazu Pflicht-TOTP | „Mit DPSG-Konto anmelden“ über die neue Mitgliederverwaltung |
| Aufwand im Code | keiner | sehr klein | mittel (Login-Seite, TOTP-Einrichtung je Konto, Zurücksetzen) | mittel (zweiter Socialite-Treiber, Zuordnung der Konten) |
| Abhängigkeit | Admins des DV-Mandanten für jede Einladung | Microsoft | eigener Mailversand (Brevo, schon vorhanden) | DPSG muss OAuth-Anwendungen für Gliederungen freigeben; frühestens Frühjahr 2027 |
| Sicherheit | gut (MFA des Gast-Mandanten) | schwach: Jede*r kann ein wartendes Konto erzeugen; MFA nicht steuerbar | gut, wenn TOTP Pflicht ist; Mail allein wäre zu schwach | gut, wenn die NaMi 3 MFA erzwingt |
| Kosten | keine | keine | keine | keine |
| Ausscheiden von Personen | Gast im DV entfernen | Rolle in Kluft entziehen | Rolle in Kluft entziehen | Austritt in der NaMi wirkt automatisch, wenn die Rolle daran hängt |

## Vorschlag

1. **Jetzt: C**, also E-Mail-Code mit Pflicht-TOTP, als zweiter Anmeldeweg neben Entra, je Instanz einschaltbar. Funktioniert für jede Gliederung sofort und ohne fremde Admins. Konten legt die Verwaltung vorab an; eine Selbstregistrierung gibt es nicht. Bausteine:
   - Code-Versand wie bei „Meine Anmeldungen“, mit Sperre nach Fehlversuchen
   - TOTP-Einrichtung bei der ersten Anmeldung, dazu Wiederherstellungscodes
   - Zurücksetzen durch die Verwaltung, protokolliert im Audit-Log
2. **Später: D** prüfen, sobald die NaMi 3 läuft. Dann melden sich alle mit dem DPSG-Konto an, das sie ohnehin haben, auch Gliederungen mit M365. Vorher klären, ob die DPSG OAuth-/OIDC-Anwendungen für Gliederungen zulässt und welche Angaben (Gliederung, Rollen) mitkommen.
3. **A** bleibt eine Übergangslösung, wenn ein DV seine Bezirke ohnehin schon als Gäste führt; ohne Code-Änderung möglich.
4. **B nicht verwenden.** Jedes beliebige Microsoft-Konto käme bis zur Freischaltung, und die Verwaltung bekäme Benachrichtigungen von Fremden.

## Offene Fragen

- Soll eine Instanz beide Wege anbieten (Entra für die Geschäftsstelle, E-Mail-Code für Ehrenamtliche)? Technisch ja, die Konten wären dann je Person einem Weg zugeordnet.
- Wer darf bei verlorenem TOTP zurücksetzen: Verwaltung allein oder nur im Vier-Augen-Prinzip?
