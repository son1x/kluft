# Mitgliederabgleich: Teilnehmende ↔ NaMi

**Stand:** 6. Oktober 2026 · **Status:** gebaut und getestet, standardmäßig aus (`MITGLIEDER_DRIVER=off`)

Bei Veranstaltungen lässt sich prüfen, ob die angemeldeten Personen Mitglied der DPSG sind, zum Beispiel für den Versicherungsschutz, einen Mitgliederpreis oder Veranstaltungen nur für Mitglieder. Das CMS fragt dafür die Mitgliederverwaltung ab und speichert **nur das Ergebnis**.

## Warum zwei Treiber?

Die DPSG stellt die Mitgliederverwaltung um:

| | NaMi 2 (heute) | NaMi 3 (hitobito) |
|---|---|---|
| Zeitplan | bis Q1 2027, danach nur noch lesbar, dann abgeschaltet | Umstieg Frühjahr 2027 (laut ncm.dpsg.de, Update #6) |
| Zugang | eigenes NaMi-Konto jeder prüfenden Person (Standard) oder technisches Konto | Service-Token der eigenen Ebene |
| Schnittstelle | `nami.dpsg.de/ica/rest/api/1/1/service/nami` | JSON:API `/api/people` |
| Treiber | `MITGLIEDER_DRIVER=nami` | `MITGLIEDER_DRIVER=hitobito` |

Das CMS spricht nur mit der Schnittstelle `App\Services\Mitglieder\Mitgliederverzeichnis` (Suche nach Vor- und Nachname, Ergebnis: Nummer, Name, Geburtsdatum, E-Mail, Stamm, aktiv ja/nein). **Die Umstellung ist nur ein Wechsel der Secrets**, Code und gespeicherte Ergebnisse bleiben gleich.

## Wie abgeglichen wird

1. In der Veranstaltung „Mitgliedschaft mit der Mitgliederverwaltung abgleichen“ einschalten, am besten zusammen mit dem Feld „Geburtsjahr“. Der Schalter erscheint nur, wenn ein Treiber eingerichtet ist.
2. Der Einwilligungstext der Anmeldung bekommt automatisch einen Hinweis auf den Abgleich. Die Anmeldung speichert die Version `<Einwilligung>+mitglieder-<Version>`, damit nachweisbar bleibt, was galt.
3. Mit technischem Konto (NaMi 2 `NAMI_AUTH=konto`, NaMi 3): Nach der Bestätigung (Double-Opt-In) wird die Anmeldung über die Warteschlange abgeglichen, höchstens 30 Abfragen je Minute. Mit persönlicher NaMi-Anmeldung startet die Veranstaltungsleitung den Abgleich selbst (Punkt 4). Unbestätigte Anmeldungen werden nie abgefragt.
4. Im Backend unter **Teilnehmende**:
   - Spalte und Filter **Mitglied**: Mitglied (mit Nummer und Stamm), ehemals Mitglied, nicht gefunden, unklar.
   - **Mitgliedschaft prüfen** startet den Abgleich für alle noch nicht geprüften Anmeldungen, auf Wunsch auch für alle neu. Bei persönlicher NaMi-Anmeldung fragt das Fenster nach der eigenen Mitgliedsnummer und dem NaMi-Passwort (siehe unten).
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
- **NaMi-Zugänge:** Ein persönlicher Zugang wird nur gespeichert, wenn die Person das selbst will, und zwar verschlüsselt. Er wird gelöscht, sobald die Person es will oder ihr Konto gesperrt wird. Wer `APP_KEY` und Datenbank zugleich hat, könnte ihn entschlüsseln. Beides deshalb wie bisher nur in Secrets bzw. der `.env` halten. Vor dem Einschalten klären, ob die NaMi-Nutzungsbedingungen das Hinterlegen erlauben.
- **Datensparsamkeit:** Es wird keine Kopie der Mitgliederdaten angelegt. Gespeichert werden nur Ergebnis, Mitgliedsnummer, Stamm und Prüfzeitpunkt an der Anmeldung. Sie werden mit der Anmeldung nach der Löschfrist gelöscht.
- **Auskunft:** Die Datenschutzauskunft (Backend → Datenschutz) enthält das Ergebnis.
- **Protokoll:** Abgleich starten und Entscheidungen von Hand stehen im Audit-Log.
- **Rechte:** Nur wer die Teilnehmenden der Veranstaltung sehen darf (`manageParticipants`), sieht das Ergebnis und kann den Abgleich starten.
- **Vor dem Einschalten:** mit der/dem Datenschutzbeauftragten abstimmen und den Abschnitt zu Veranstaltungen in der Datenschutzerklärung ergänzen.

## Einrichten

Zugangsdaten nur als GitHub-Secrets bzw. in der `.env` auf dem Server, nie im Repository oder im Chat.

### NaMi 2 (bis zur Umstellung)

**Standard: persönliche Anmeldung.** Wer im Backend „Mitgliedschaft prüfen“ startet, meldet sich mit dem **eigenen NaMi-Konto** an. Ein technisches Konto ist nicht nötig.

```
MITGLIEDER_DRIVER=nami
# optional, falls sich der Pfad ändert:
# NAMI_URL=https://nami.dpsg.de
# NAMI_PATH=/ica/rest/api/1/1/service/nami
```

So funktioniert die persönliche Anmeldung:
- **Ablauf:** Das CMS meldet sich einmal bei NaMi an (`auth/manual/sessionStartup`). Danach arbeitet die Warteschlange nur mit dem Session-Cookie.
- **Passwort nur auf Wunsch gespeichert:** Ohne Häkchen wird das Passwort weder gespeichert noch protokolliert. In die Warteschlange geht nur das Session-Cookie, verschlüsselt (`ShouldBeEncrypted`).
- **Zugang im Profil (freiwillig):** Unter **Mein Konto → Mein NaMi-Zugang** lassen sich Mitgliedsnummer und Passwort speichern. Das geht auch per Häkchen direkt beim Prüfen.
  - Gespeichert wird nur, wenn die Anmeldung bei NaMi klappt.
  - Beide Werte liegen verschlüsselt mit dem `APP_KEY` in der Datenbank. Niemand im Backend kann sie lesen.
  - Danach fragt „Mitgliedschaft prüfen“ nicht mehr. Läuft die NaMi-Sitzung mitten im Abgleich ab, meldet sich der Job neu an. Dafür trägt er nur die ID der Person, nie das Passwort.
  - Wird ein Konto gesperrt, wird der gespeicherte Zugang gelöscht. Löschen geht jederzeit selbst; das Speichern und Löschen steht im Audit-Log.
- **„Verbindung testen“** (Profil): meldet sich an und sucht nach dem eigenen Namen aus dem Backend. Zeigt die eigene Person, wenn Anmeldung, Suche und Feldzuordnung stimmen.
- **Ampel:** im Profil und im Fenster „Mitgliedschaft prüfen“.
  - **NaMi-Server:** grün = erreichbar, rot = nicht erreichbar. Wird 5 Minuten zwischengespeichert.
  - **Dein NaMi-Zugang:** grün = Anmeldung klappt, rot = letzte Anmeldung fehlgeschlagen (z. B. Passwort in NaMi geändert), gelb = noch nicht geprüft, grau = nicht gespeichert.
- **Statusseite:** zeigt „Mitgliederverwaltung (NaMi)“ als eigene Zeile. Ist NaMi nicht erreichbar, ist das eine Warnung, kein Alarm.
- **Ergebnis nach eigenen Rechten:** Das Ergebnis folgt den **NaMi-Rechten der prüfenden Person**. „Nicht gefunden“ kann auch heißen, dass die Person für sie in NaMi nicht sichtbar ist. Wer nur Leserechte im eigenen Stamm hat, findet Teilnehmende aus anderen Stämmen nicht.
- **Abgelaufene Sitzung:** Läuft die NaMi-Sitzung ab, hört der Abgleich auf. Der Rest bleibt „noch nicht geprüft“; einfach erneut „Mitgliedschaft prüfen“ starten.
- **Kein automatischer Abgleich:** Neue Anmeldungen werden nach der Bestätigung nicht automatisch geprüft, weil kein Konto hinterlegt ist.
- **`mitglieder:check`:** prüft in dieser Betriebsart nur, ob NaMi erreichbar ist.

**Alternative: technisches Konto.** Damit werden neue Anmeldungen automatisch geprüft. Es braucht ein eigenes Konto mit Leserecht auf die Mitglieder der Ebene (z. B. „Lesezugriff“ auf Diözesanebene), ohne Schreibrechte. Mit gesetztem `NAMI_USER` gilt automatisch `NAMI_AUTH=konto`.

```
MITGLIEDER_DRIVER=nami
NAMI_AUTH=konto
NAMI_USER=<Mitgliedsnummer des technischen Kontos>
NAMI_PASSWORD=<Passwort>
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

`php artisan mitglieder:check` bzw. Actions → „Server-Prüfung“ → `mitglieder:check`: meldet sich an und sucht einen Namen, den es nicht gibt. Gibt keine Mitgliederdaten aus. Bei persönlicher NaMi-Anmeldung nur Erreichbarkeit; den eigentlichen Test macht man mit dem eigenen Konto an einer Testveranstaltung.

## Umstellung NaMi 2 → NaMi 3 (Checkliste)

1. Sobald die NaMi 3 als Testsystem erreichbar ist: Service-Token anlegen und auf `test.<domain>` mit `MITGLIEDER_DRIVER=hitobito` prüfen (`mitglieder:check`, dann eine Testveranstaltung).
2. Klären, wie die DPSG die alte Mitgliedsnummer in NaMi 3 abbildet (eigenes Attribut oder Personen-ID), und `HITOBITO_MEMBER_NUMBER_ATTRIBUTE` setzen. Laut DPSG bleibt der Stammeswechsel über „Mitgliedsnummer und Geburtsdatum“ möglich, die Nummer bleibt also erhalten.
3. Am Umstiegstag die Secrets der Live-Seite umstellen. Gespeicherte Ergebnisse bleiben gültig; bei laufenden Veranstaltungen lässt sich mit „Auch bereits geprüfte neu prüfen“ gegen NaMi 3 abgleichen.
4. NaMi-2-Secrets löschen und ggf. das technische NaMi-2-Konto stilllegen.

## Offene Punkte

- Die Antwortfelder der NaMi-2-Suche (`entries_vorname`, `entries_mitgliedsNummer`, `entries_geburtsDatum`, `entries_status`, `entries_mglType`, `entries_gruppierung`) folgen der NaMi-App (digital-scouts/dpsg-nami-app). Beim ersten echten Lauf mit `mitglieder:check` und einer Testveranstaltung prüfen.
- Für NaMi 3 sind Feldnamen der DPSG-Erweiterung (hitobito_dpsg, hitobito_pfadi_de) noch in Arbeit. Austritt (`exit_date`) und Stamm (`layer_group`) sind schon vorhanden.
