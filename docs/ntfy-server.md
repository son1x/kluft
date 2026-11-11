# Eigener ntfy-Server (Push aufs Handy) hinter Caddy

Kluft schickt Push-Hinweise (neue Anmeldung, Platzstand) über **ntfy**. Standard
ist der öffentliche Dienst `ntfy.sh`. Ein eigener Server hält die Hinweise auf
unserer Infrastruktur; die Inhalte sind ohnehin knapp (Titel, Platzzahlen,
keine Namen), aber Themenname und Zeitpunkt liegen dann nicht mehr bei einem
Dritten.

## 1. Container

`docker-compose.yml` (z. B. unter `/opt/ntfy`):

```yaml
services:
  ntfy:
    image: binwiederhier/ntfy:v2.11.0
    command: serve
    restart: unless-stopped
    ports:
      - "127.0.0.1:2586:80"   # nur lokal, nach außen nur über Caddy
    volumes:
      - ./etc:/etc/ntfy
      - ./cache:/var/cache/ntfy
      - ./lib:/var/lib/ntfy
    environment:
      TZ: Europe/Berlin
```

`etc/server.yml`:

```yaml
base-url: "https://push.dpsg-trier.de"
behind-proxy: true
listen-http: ":80"
cache-file: "/var/cache/ntfy/cache.db"
cache-duration: "12h"
auth-file: "/var/lib/ntfy/user.db"
auth-default-access: "deny-all"
# iPhone: Zustellung über den Apple-Dienst von ntfy.sh (nur ein Weckruf ohne Inhalt).
upstream-base-url: "https://ntfy.sh"
```

## 2. Caddy

```
push.dpsg-trier.de {
    reverse_proxy 127.0.0.1:2586
}
```

DNS-Eintrag `push` auf den Server setzen; Caddy holt das Zertifikat selbst.

## 3. Zugänge

```bash
docker compose exec ntfy ntfy user add --role=user kluft
```

```bash
docker compose exec ntfy ntfy access kluft 'dpsg-trier-*' write-only
```

```bash
docker compose exec ntfy ntfy access everyone 'dpsg-trier-*' read-only
```

```bash
docker compose exec ntfy ntfy token add kluft
```

- Kluft erzeugt Themen der Form `<präfix><28 Zufallszeichen>` (Mein Konto →
  Push). Der Präfix kommt aus der Domain der Website ohne Subdomain und
  Endung: `test.dpsg-trier.de` und `dpsg-trier.de` ergeben beide
  `dpsg-trier-`, `stamm-roden.de` ergibt `stamm-roden-`. So passt dieselbe
  Regel für Test und Live. Mit `NTFY_PREFIX` lässt er sich festlegen; bereits
  angelegte Themen behalten ihren Namen. Der Zufallsteil wirkt wie ein Passwort: Lesen darf jede*r, die oder
  der das Thema kennt, schreiben nur Kluft.
- Den Token **nicht** in Chats oder Tickets kopieren, nur in die Umgebung.

## 4. Kluft einstellen

In der `.env` bzw. den Secrets der Instanz:

```
NTFY_SERVER=https://push.dpsg-trier.de
NTFY_TOKEN=tk_…
```

Danach unter Mein Konto → Push „Test senden“. Bereits eingerichtete Handys
müssen das Thema einmal neu abonnieren (Server in der App auf
`push.dpsg-trier.de` stellen); die Seite zeigt den passenden Link.

## 5. Betrieb

- Updates: Image-Version anheben, `docker compose pull && docker compose up -d`.
- Nachrichten liegen höchstens 12 Stunden im Cache (`cache-duration`).
- Logs enthalten Themennamen; Log-Aufbewahrung des Servers kurz halten.
