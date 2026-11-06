# Videokonferenzen (Jitsi)

**Stand:** 6. November 2026 · Funktion „Videokonferenzen (Jitsi)“ (`videokonferenzen`, ab Ausbaustufe 3, beim DV an)

Im Backend unter **Verband & Kontakt → Videokonferenzen** legt man eine Konferenz mit **Titel und Beginn** an. Kluft zeigt dann:

- den **Teilnahme-Link** `https://meet.dpsg-trier.de/<raum>`, zum Kopieren;
- einen fertigen **Einladungstext**, nur mit dem Teilnahme-Link;
- den **Moderator-Link** `…/<raum>?jwt=<Token>`. Wer ihn öffnet, ist ohne Microsoft-Anmeldung Moderator*in dieses einen Raums. Er gilt **bis 23:59:59 Uhr am Tag nach der Konferenz** (Europe/Berlin) und wird danach nicht mehr angezeigt;
- den Link für Mitglieder mit Microsoft-Konto (`login.meet…/room/<raum>`).

Grundlage ist die Projektdokumentation der Jitsi-Installation, Kapitel 8 (Aufbau, Prosody-Konfiguration, Token-Spezifikation).

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

- Konferenz an eine Veranstaltung hängen (Link in der Bestätigungsmail an Teilnehmende).
- Wiederkehrende Termine.
- Mehrere Moderator-Namen.
