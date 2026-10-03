# Push-Benachrichtigungen bei Anmeldungen und Fragen – Abwägung

Stand: 4. Oktober 2026 · Status: **entschieden: ntfy, umgesetzt** (siehe unten „Umsetzung mit ntfy“)

## Ziel

Verantwortliche sollen sofort aufs Handy benachrichtigt werden, wenn

- eine **Anmeldung** zu einer Veranstaltung eingeht (inkl. Warteliste, Stornierung) oder
- jemand eine **Frage zu einer Veranstaltung** stellt.

Ursprüngliche Idee: Anbindung an **Signal**.

## Ausgangslage

- Die Website läuft auf dem **Netcup-Webspace (Plesk)**. Dort sind nur Webanwendungen und Cronjobs möglich – keine dauerhaft laufenden Hintergrundprogramme oder Docker-Container. Ausgehende HTTPS-Aufrufe sind möglich.
- Der Verband nutzt bereits **Microsoft 365** (Anmeldung ins Backend über M365) und eine **Nextcloud** (Kalender).
- Transaktionsmails sollen ohnehin über **Brevo** laufen (SMTP-Zugang noch offen, s. Roadmap).
- Heute gibt es **keine** Benachrichtigung der Verantwortlichen bei neuen Anmeldungen – die Liste ist nur im Backend sichtbar.
- Kontaktformular und **„Frage zur Veranstaltung“** gibt es inzwischen (Pflichtenheft F-CMS-09); beide schicken eine E-Mail an die zuständigen Personen bzw. die Kontaktperson der Veranstaltung und speichern nichts.

## Optionen

| Weg | Aufwand | Push aufs Handy | Datenschutz | Betrieb |
|---|---|---|---|---|
| **Microsoft Teams** (Kanal, z. B. „Anmeldungen“) | gering – Website schickt Nachricht an einen Kanal-Webhook (Teams-Workflow) | über die Teams-App | bleibt im eigenen M365-Tenant | wartungsarm, offiziell unterstützt |
| **Nextcloud Talk** (Unterhaltung mit Bot) | gering – Talk-Bot bzw. Bot-Benutzer per API | über die Talk-App | bleibt auf dem eigenen Server – am saubersten | wartungsarm, offiziell unterstützt |
| **E-Mail an die Verantwortlichen** | sehr gering | über die Mail-App | Versand über Brevo (Auftragsverarbeitung) | kaum Wartung |
| **Signal** | hoch – eigener Server, eigene Telefonnummer, Wartung | ja | Ende-zu-Ende-verschlüsselt | inoffiziell, kann bei Signal-Updates brechen |

### Signal im Detail

- Signal bietet **keine offizielle Schnittstelle** für automatische Nachrichten.
- Möglich nur mit dem inoffiziellen Programm **signal-cli** (bzw. signal-cli-rest-api), das
  - eine **eigene Telefonnummer** benötigt (z. B. günstige Prepaid-SIM) und
  - **dauerhaft laufen** muss.
- Das geht **nicht auf dem Webspace**. Nötig wäre zusätzlich ein kleiner Server (günstiger VPS oder Raspberry Pi), den jemand betreut und aktuell hält.
- Signal ändert sein Protokoll gelegentlich; dann funktioniert signal-cli bis zum nächsten Update nicht – Benachrichtigungen fallen in der Zeit unbemerkt aus.

### Microsoft Teams im Detail

- Je Kanal wird ein Webhook eingerichtet (Teams → Kanal → Workflows → „Beim Empfang einer Webhookanforderung in einem Kanal posten“).
- Die Website schickt bei jedem Ereignis eine kurze Nachricht dorthin; die Teams-App zeigt sie als Push an.
- Voraussetzung: Die Verantwortlichen sind in Teams aktiv.

### Nextcloud Talk im Detail

- Ein Bot (Talk-Bot-API) bzw. ein eigener Bot-Benutzer schreibt in eine Unterhaltung.
- Push über die Nextcloud-Talk-App; alle Daten bleiben auf dem eigenen Nextcloud-Server.
- Voraussetzung: Talk ist auf der Nextcloud aktiv und die Verantwortlichen nutzen die App.

## Datenschutz (gilt für jeden Weg)

Die Benachrichtigung enthält **keine Teilnehmerdaten**, sondern nur:

> Neue Anmeldung: Diözesan-Wölflingstag 2027 (12/30 Plätze) – im Backend ansehen

Namen, Geburtsjahr, Stamm usw. stehen weiterhin nur im Backend, wo die Zugriffsrechte je Stufe/Fachbereich gelten und Exporte protokolliert werden. Das passt zum KDG (Datensparsamkeit) und dazu, dass Chat-Verläufe auf privaten Handys nicht kontrolliert gelöscht werden können.

Bei Fragen zu Veranstaltungen gilt dasselbe: Die Benachrichtigung nennt nur Veranstaltung und Eingang; der Text der Frage und die Kontaktdaten stehen im Backend.

## Offene Fragen

1. **Welcher Kanal?** Teams, Nextcloud Talk, E-Mail – oder doch Signal (dann: wer stellt Server und Nummer bereit und betreut beides)?
2. **Wer bekommt welche Meldung?**
   - Vorschlag A: ein Kanal je Stufe/Fachbereich plus einer für den ganzen Verband (Veranstaltungen ohne Stufe).
   - Vorschlag B: Meldung an die bei der Veranstaltung eingetragene Kontaktperson.
3. **Welche Ereignisse?** Neue Anmeldung, Nachrücken von der Warteliste, Stornierung, Veranstaltung voll, neue Frage.

## Empfehlung

**Microsoft Teams**, falls die Verantwortlichen dort aktiv sind – sonst **Nextcloud Talk**. Beides läuft ohne zusätzlichen Server direkt vom Webspace aus, ist offiziell unterstützt und hält die Daten in eigener Hand. E-Mail eignet sich als Rückfallebene. Signal nur, wenn ausdrücklich gewünscht und jemand Server und Nummer dauerhaft betreut.

## Umsetzungsskizze (nach Entscheidung)

- Kanal-Adressen (Webhook-URLs bzw. Talk-Zugang) werden als **GitHub-Secrets** bzw. im Backend unter „Integrationen“ hinterlegt – nie im Code.
- Benachrichtigungen laufen über die vorhandene **Warteschlange** (wie die Instagram-Veröffentlichung), damit eine Anmeldung nie an einem nicht erreichbaren Chat-Dienst scheitert; Fehlschläge werden protokolliert und erneut versucht.
- Zuordnung Ereignis → Kanal je Stufe/Fachbereich im Backend einstellbar.
- Tests mit simuliertem Chat-Dienst (wie bei Instagram).

## Umsetzung mit ntfy (Entscheidung 3./4. Oktober 2026)

Gewählt wurde **ntfy** (App für Android und iPhone, ohne Konto, Open Source). Gründe: läuft ohne eigenen Server direkt vom Webspace, jede Person entscheidet selbst, und es passt auch für Ehrenamtliche ohne Teams.

- **Je Person freiwillig:** Backend → „Meine Push-Nachrichten“ → „Einschalten“. Jede Person bekommt ein eigenes, zufälliges Thema (wirkt wie ein Passwort, verschlüsselt gespeichert) und wählt die Ereignisse.
- **Nur passend zu den Rechten** (dieselben Prüfungen wie im Backend):

| Ereignis | Wer kann es abonnieren |
|---|---|
| Anmeldungen zu Veranstaltungen (neu, Warteliste, Storno) | Verwaltung; Stufen-/Sektionsadmins nur für Veranstaltungen ihres Bereichs |
| Beiträge, die auf Freigabe warten | wer freigeben darf, nur für Beiträge des eigenen Bereichs; nicht die Autor:in selbst |
| Neu veröffentlichte Beiträge | alle mit Backend-Zugang |
| Neue Rechte für mich | alle |
| Konten zur Freischaltung, weitreichende Rechtevergaben | Verwaltung |
| Störungen der Website (Monitoring) | Admin (Technik) |

- **Ohne personenbezogene Daten:** z. B. „Neue Anmeldung – Wölflingstag: 12/30 Plätze belegt“ mit Link ins Backend. Keine Namen, Adressen oder Fragetexte.
- **Zusätzlich zur E-Mail:** Mails laufen unverändert weiter. Push-Fehler werden protokolliert und brechen nie eine Anmeldung oder Mail ab.
- **Server:** Standard ntfy.sh; ein eigener ntfy-Server ist über die Secrets `NTFY_SERVER` und `NTFY_TOKEN` möglich (z. B. später auf einem eigenen Server des Verbands).
- **Datenschutz:** ntfy.sh ist ein externer Dienst (Betreiber in den USA, Nachrichten werden kurz zwischengespeichert). Weil die Nachrichten keine personenbezogenen Daten enthalten und die Nutzung freiwillig ist, ist das vertretbar; im Verzeichnis der Verarbeitungstätigkeiten beim Redaktions-Login vermerken.
