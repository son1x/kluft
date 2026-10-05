# Mitgliederabgleich: Teilnehmende ↔ NaMi

**Stand:** 5. Oktober 2026 · **Status:** gebaut und getestet, standardmäßig aus (`MITGLIEDER_DRIVER=off`)

Bei Veranstaltungen lässt sich prüfen, ob die angemeldeten Personen Mitglied der DPSG sind, zum Beispiel für den Versicherungsschutz, einen Mitgliederpreis oder Veranstaltungen nur für Mitglieder. Das CMS fragt dafür die Mitgliederverwaltung ab und speichert **nur das Ergebnis**.

## Warum zwei Treiber?

Die DPSG stellt die Mitgliederverwaltung um:

| | NaMi 2 (heute) | NaMi 3 (hitobito) |
|---|---|---|
| Zeitplan | bis Q1 2027, danach nur noch lesbar, dann abgeschaltet | Umstieg Frühjahr 2027 (laut ncm.dpsg.de, Update #6) |
| Zugang | Mitgliedsnummer + Passwort eines Kontos | Service-Token der eigenen Ebene |
| Schnittstelle | `nami.dpsg.de/ica/rest/api/1/1/service/nami` | JSON:API `/api/people` |
| Treiber | `MITGLIEDER_DRIVER=nami` | `MITGLIEDER_DRIVER=hitobito` |

Das CMS spricht nur mit der Schnittstelle `App\Services\Mitglieder\Mitgliederverzeichnis` (Suche nach Vor- und Nachname, Ergebnis: Nummer, Name, Geburtsdatum, E-Mail, Stamm, aktiv ja/nein). **Die Umstellung ist nur ein Wechsel der Secrets**, Code und gespeicherte Ergebnisse bleiben gleich.

## Wie abgeglichen wird

1. In der Veranstaltung „Mitgliedschaft mit der Mitgliederverwaltung abgleichen“ einschalten, am besten zusammen mit dem Feld „Geburtsjahr“. Der Schalter erscheint nur, wenn ein Treiber eingerichtet ist.
2. Der Einwilligungstext der Anmeldung bekommt automatisch einen Hinweis auf den Abgleich. Die Anmeldung speichert die Version `<Einwilligung>+mitglieder-<Version>`, damit nachweisbar bleibt, was galt.
3. Nach der Bestätigung (Double-Opt-In) wird die Anmeldung über die Warteschlange abgeglichen, höchstens 30 Abfragen je Minute. Unbestätigte Anmeldungen werden nie abgefragt.
4. Im Backend unter **Teilnehmende**:
   - Spalte und Filter **Mitglied**: Mitglied (mit Nummer und Stamm), ehemals Mitglied, nicht gefunden, unklar.
   - **Mitgliedschaft prüfen** startet den Abgleich für alle noch nicht geprüften Anmeldungen, auf Wunsch auch für alle neu.
   - **Mitgliedschaft** je Person: Entscheidung von Hand, z. B. bei „unklar“. Sie wird von späteren Abgleichen nicht überschrieben.
   - Der Export (.xlsx) enthält Ergebnis, Mitgliedsnummer und Stamm.

**Regeln:**
- Gesucht wird nur mit dem ersten Vornamen und dem Nachnamen, nicht mit der ganzen Mitgliederliste.
- Ein Treffer zählt, wenn Nachname und erster Vorname übereinstimmen. Groß-/Kleinschreibung, Umlaute (ä = ae) und Bindestriche spielen keine Rolle.
- Sind bei beiden Seiten Geburtsdaten bekannt, muss das Geburtsjahr passen.
- Aktive Mitgliedschaften gehen vor beendeten. Bleiben mehrere Treffer übrig, entscheidet die E-Mail-Adresse, sonst „unklar“.
- NaMi 2 führt auch Nicht-Mitglieder (z. B. Kontakte); die zählen nicht als Mitglied.

## Datenschutz (KDG)

- **Zweck:** Prüfung der Mitgliedschaft für die Veranstaltung (Versicherung, Beitrag, Teilnahmevoraussetzung). Die Teilnehmenden werden im Einwilligungstext informiert.
- **Datensparsamkeit:** Es wird keine Kopie der Mitgliederdaten angelegt. Gespeichert werden nur Ergebnis, Mitgliedsnummer, Stamm und Prüfzeitpunkt an der Anmeldung. Sie werden mit der Anmeldung nach der Löschfrist gelöscht.
- **Auskunft:** Die Datenschutzauskunft (Backend → Datenschutz) enthält das Ergebnis.
- **Protokoll:** Abgleich starten und Entscheidungen von Hand stehen im Audit-Log.
- **Rechte:** Nur wer die Teilnehmenden der Veranstaltung sehen darf (`manageParticipants`), sieht das Ergebnis und kann den Abgleich starten.
- **Vor dem Einschalten:** mit der/dem Datenschutzbeauftragten abstimmen und den Abschnitt zu Veranstaltungen in der Datenschutzerklärung ergänzen.

## Einrichten

Zugangsdaten nur als GitHub-Secrets bzw. in der `.env` auf dem Server, nie im Repository oder im Chat.

### NaMi 2 (bis zur Umstellung)

Ein eigenes **technisches NaMi-Konto** verwenden, kein persönliches. Es braucht nur Leserecht auf die Mitglieder der Ebene (z. B. „Lesezugriff“ auf Diözesanebene); Schreibrechte sind nicht nötig.

```
MITGLIEDER_DRIVER=nami
NAMI_USER=<Mitgliedsnummer des technischen Kontos>
NAMI_PASSWORD=<Passwort>
# optional, falls sich der Pfad ändert:
# NAMI_URL=https://nami.dpsg.de
# NAMI_PATH=/ica/rest/api/1/1/service/nami
```

### NaMi 3 / hitobito (ab dem Umstieg)

1. Eine Person mit Leitungsrechten auf der eigenen Ebene (`layer_and_below_full`) legt im Reiter **API-Keys** einen Service-Account an.
2. Berechtigung: **„Personen dieser und der darunterliegenden Ebenen“**. Mehr nicht.
3. Ob Geburtsdatum und Austrittsdatum mitkommen, hängt am Detail-Leserecht des Tokens. Ohne sie vergleicht der Abgleich nur Name und E-Mail; „unklar“ kommt dann häufiger vor.

```
MITGLIEDER_DRIVER=hitobito
HITOBITO_URL=https://<Adresse der NaMi 3>
HITOBITO_TOKEN=<Service-Token>
# Attribut mit der Mitgliedsnummer, sobald die DPSG es festlegt;
# leer = hitobito-Personen-ID
# HITOBITO_MEMBER_NUMBER_ATTRIBUTE=
```

### Prüfen

`php artisan mitglieder:check` bzw. Actions → „Server-Prüfung“ → `mitglieder:check`: meldet sich an und sucht einen Namen, den es nicht gibt. Gibt keine Mitgliederdaten aus.

## Umstellung NaMi 2 → NaMi 3 (Checkliste)

1. Sobald die NaMi 3 als Testsystem erreichbar ist: Service-Token anlegen und auf `test.<domain>` mit `MITGLIEDER_DRIVER=hitobito` prüfen (`mitglieder:check`, dann eine Testveranstaltung).
2. Klären, wie die DPSG die alte Mitgliedsnummer in NaMi 3 abbildet (eigenes Attribut oder Personen-ID), und `HITOBITO_MEMBER_NUMBER_ATTRIBUTE` setzen. Laut DPSG bleibt der Stammeswechsel über „Mitgliedsnummer und Geburtsdatum“ möglich, die Nummer bleibt also erhalten.
3. Am Umstiegstag die Secrets der Live-Seite umstellen. Gespeicherte Ergebnisse bleiben gültig; bei laufenden Veranstaltungen lässt sich mit „Auch bereits geprüfte neu prüfen“ gegen NaMi 3 abgleichen.
4. NaMi-2-Secrets löschen und das technische NaMi-2-Konto stilllegen.

## Offene Punkte

- Die Antwortfelder der NaMi-2-Suche (`entries_vorname`, `entries_mitgliedsNummer`, `entries_geburtsDatum`, `entries_status`, `entries_mglType`, `entries_gruppierung`) folgen der NaMi-App (digital-scouts/dpsg-nami-app). Beim ersten echten Lauf mit `mitglieder:check` und einer Testveranstaltung prüfen.
- Für NaMi 3 sind Feldnamen der DPSG-Erweiterung (hitobito_dpsg, hitobito_pfadi_de) noch in Arbeit. Austritt (`exit_date`) und Stamm (`layer_group`) sind schon vorhanden.
