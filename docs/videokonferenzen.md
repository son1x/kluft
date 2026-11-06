# Videokonferenzen (Jitsi)

**Stand:** 6. November 2026 · Funktion „Videokonferenzen (Jitsi)“ (`videokonferenzen`, ab Ausbaustufe 3, beim DV an)

**Abschaltbar** wie jede Funktion unter Verwaltung → Funktionen. Standard ist aus; nur der DV hat sie in `instanzen/dv-trier/config.php` eingeschaltet. Sie setzt eine eigene Jitsi-Installation mit Token-Anmeldung voraus: Prosody mit `authentication = "token"`, Gäste warten auf die Moderation, Anmeldung der Mitglieder über einen OpenID-Adapter wie `jitsi-openid`. Ohne diesen Aufbau die Funktion aus lassen.

Im Backend unter **Verband & Kontakt → Videokonferenzen** legt man eine Konferenz mit **Titel und Beginn** an. Kluft zeigt dann:

- den **Teilnahme-Link** `https://meet.dpsg-trier.de/<raum>`, zum Kopieren;
- einen fertigen **Einladungstext**, nur mit dem Teilnahme-Link;
- den **Moderator-Link** `…/<raum>?jwt=<Token>`. Wer ihn öffnet, ist ohne Microsoft-Anmeldung Moderator*in dieses einen Raums. Er gilt **bis 23:59:59 Uhr am Tag nach der Konferenz** (Europe/Berlin) und wird danach nicht mehr angezeigt;
- den Link für Mitglieder mit Microsoft-Konto (`login.meet…/room/<raum>`).

Grundlage ist die Projektdokumentation der Jitsi-Installation, Kapitel 8 (Aufbau, Prosody-Konfiguration, Token-Spezifikation).

## Einladungen per E-Mail

Im Formular stehen zwei Felder für E-Mail-Adressen; mehrere Adressen lassen sich mit Komma, Leerzeichen oder Enter einfügen.

| Feld | Bekommt | Höchstens |
|---|---|---|
| **Moderation** | Moderator-Link (mit Gültigkeit), Teilnahme-Link, Hinweis „nicht weitergeben“ | 10 |
| **Teilnehmende** | nur den Teilnahme-Link und den Hinweis, dass die Konferenz mit der Moderation beginnt | 200 |

- **Beim Anlegen** gehen die Mails automatisch raus: an jede Adresse eine eigene Mail, niemand sieht die anderen Adressen.
  - Wer unter Moderation steht, bekommt keine zweite Mail als Teilnehmer*in.
  - Antworten gehen an die Person, die die Konferenz angelegt hat.
- **Kalendereintrag:** Jede Mail hat einen Termin (`.ics`) mit Beginn, Dauer (Feld „Dauer“, Standard 60 Minuten) und Teilnahme-Link im Anhang, nie mit Moderator-Link.
- **Nachladen:** Auf der Detailseite schickt „Einladungen senden“ weitere Einladungen. Nur die neuen Adressen eintragen; wer in der Liste steht, bekommt die Mail auch erneut.
- **Versand** über die Warteschlange (Brevo-Tageslimit, Wiederholungen). Der Moderator-Link entsteht erst beim Versand, die Warteschlange ist verschlüsselt.
- **Testsystem:** Auf test.dpsg-trier.de gehen Mails an die Testadresse, außer an freigeschaltete Test-Empfänger*innen (Verwaltung → E-Mail-Versand).
- **Datenschutz:**
  - Die Adressen liegen verschlüsselt in der Datenbank und werden **7 Tage nach der Konferenz gelöscht**.
  - Die Konferenzen selbst werden **nach 12 Monaten gelöscht** (`videokonferenzen:aufraeumen`, täglich 03:55).

## Rechte

- **Anlegen:** Stufen- und Fachbereichsleitungen (`stufenadmin`, `sektionsadmin`), Vorstand, Verwaltung und Admins, wie bei den Umfragen.
- **Sehen und Löschen:** Jede*r sieht die eigenen Konferenzen, Verwaltung und Admins sehen alle. Fremde Konferenzen sind für andere nicht auffindbar (404).
- **Bearbeiten:** Gibt es nicht, weil Raumname und Links am Termin hängen. Für einen neuen Termin eine neue Konferenz anlegen.

## Technik

| Teil | Wo |
|---|---|
| Tabelle `videokonferenzen` | Titel, Beginn, Raumname (eindeutig, mit 6 Zufallszeichen), angelegt von |
| Token | `App\Services\Jitsi\Jitsi`: JWT HS256, `iss`/`aud` = `jitsi`, `sub` = `meet.dpsg-trier.de`, `room`, `iat`, `exp`, `context.user.name` = „Moderator“ |
| Moderator-Tokens | **werden nicht gespeichert**, sondern bei jedem Aufruf neu berechnet |
| Konfiguration | `config/services.php` → `jitsi`: `JITSI_URL` (Standard `https://meet.dpsg-trier.de`), `JITSI_SECRET`, `JITSI_SUB` |

## Einrichten

1. **Secret ins GitHub-Environment**, als Secret `JITSI_SECRET` in `dv-trier`, nach dem Livegang auch in `dv-trier-live`.
   - Es ist derselbe Wert wie `app_secret` in der Prosody-Datei und `JITSI_SECRET` in `/docker/jitsi-openid/.env`.
   - Nie in Chat, Mail oder Repository.
2. **Deploy** der Instanz. Bis dahin zeigt die Detailseite „Noch nicht eingerichtet“ statt des Moderator-Links.
3. **Prüfen, ohne den Wert zu zeigen** (Prüfsumme muss mit Prosody und Adapter übereinstimmen, Befehle aus der Jitsi-Doku, Kapitel 4). Auf dem Webspace:

   ```bash
   grep '^JITSI_SECRET=' .env | cut -d= -f2- | tr -d '"\n' | sha256sum | cut -c1-12
   ```

## Live-Test (Jitsi-Doku 8.8, Tests 2–6)

1. **Moderator-Link** in einem frischen Browser öffnen, der nie bei Microsoft angemeldet war. Erwartet: direkt im Raum, als Moderator*in.
2. **Teilnahme-Link** vorher in einem zweiten Browser öffnen. Erwartet: Wartebildschirm, nach dem Beitritt des Moderators im Raum.
3. **Falscher Raum:** Den Token in eine andere Raum-Adresse kopieren. Erwartet: abgelehnt.
4. **Abgelaufen bzw. falsches Secret:** Erwartet: abgelehnt. Im Prosody-Log steht `Error verifying token` bzw. `Invalid signature`.

## Sicherheit

- Wer den Moderator-Link hat, ist Moderator*in. Den Link nur an die Moderation geben, nie in Einladungen oder Mails an Teilnehmende.
- Einzelne Links lassen sich nicht zurückziehen. Löschen in Kluft ändert nichts an schon verschickten Links. Im Notfall das Secret tauschen, an allen drei Stellen: Prosody, Adapter, Kluft.
- Der Token steht in der Adresse. Das Zugriffsprotokoll von nginx auf `meet` sollte ihn nicht dauerhaft speichern (Jitsi-Doku 8.7).

## Später (bei Bedarf)

- Konferenz an eine Veranstaltung hängen (Teilnehmende der Veranstaltung automatisch einladen).
- Absage-Mail beim Löschen.
- Wiederkehrende Termine.
- Mehrere Moderator-Namen.
