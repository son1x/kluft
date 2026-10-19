# Eigener Runner auf dem NAS

GitHub Actions zählt Minuten. Ein eigener Runner, z. B. auf dem NAS, führt
Deploys aus, ohne das Kontingent zu belasten. Die Dateien liegen in `runner/`.

## Voraussetzungen

- **Docker** mit Compose auf dem NAS (Synology: Container Manager, QNAP: Container Station).
- **Architektur:** amd64 oder arm64 (das Runner-Image gibt es für beide). Getestet sind die Workflows bisher nur auf amd64.
- **Speicher:** etwa 5 GB frei für Image, PHP, Node und Arbeitsordner.

## Einrichten

1. **Zugriffstoken anlegen:** auf GitHub unter Settings → Developer settings → Fine-grained tokens.
   - Repository: nur `son1x/kluft`.
   - Recht: „Administration: Read and write“. Damit holt sich der Runner seinen Anmelde-Token; Code lesen kann er damit nicht.
   - Ablaufdatum setzen, z. B. ein Jahr.
2. **Dateien aufs NAS kopieren:** den Ordner `runner/` aus dem Repository in einen eigenen Ordner, z. B. `/volume1/docker/kluft-runner`.
3. **`.env` anlegen:**

   ```bash
   cp .env.example .env
   chmod 600 .env
   stat -c %g /var/run/docker.sock   # Zahl als DOCKER_GID in .env eintragen
   ```

   In `.env` den Token als `GITHUB_PAT` eintragen. Er gehört nur in diese Datei, nie ins Repository oder in einen Chat.
4. **Starten:**

   ```bash
   docker compose up -d --build
   docker compose logs -f   # „Listening for Jobs“ = bereit
   ```

5. **Prüfen:** Unter GitHub → Repository → Settings → Actions → Runners steht der Runner „nas“ als *Idle*.

## Benutzen

**Deploy:** Actions → „Deploy“ → Run workflow → bei **Runner** `nas` wählen. Ist der Runner aus, wartet der Job, bis er wieder da ist.

**Laufzeit:** Der erste Lauf dauert länger, weil `setup-php` PHP per apt installiert. Danach bleibt PHP im Container, bis er neu gebaut wird.

**MariaDB für die Tests:** Der Deploy startet sie als Service-Container über den Docker des NAS, auf dem Port 33306. So stört er keine MariaDB, die schon auf dem NAS läuft (Port 3306).

**Nur ein Job gleichzeitig:** Für mehr Jobs einen zweiten Dienst mit anderem `RUNNER_NAME` anlegen.

## Pflege

- **Aktualisieren:** Der Runner aktualisiert sich selbst. Das Basis-Image in `runner/Dockerfile` gelegentlich anheben und `docker compose up -d --build` ausführen.
- **Token erneuern:** vor Ablauf einen neuen Token in `.env` eintragen, dann `docker compose up -d`.
- **Stoppen:** `docker compose down` meldet den Runner bei GitHub ab.

## Sicherheit

- **Secrets:** Während eines Deploys liegen die Secrets des Environments (SSH-Schlüssel, Datenbank-Zugang) im Arbeitsspeicher des Runners. Der Runner sollte deshalb nur auf einem Gerät laufen, dem man das anvertraut.
- **Nur private Repositories:** Eigene Runner nur für private Repositories nutzen. Bei öffentlichen könnte fremder Code aus Pull Requests darauf laufen.
- **Docker-Socket:** Er ist eingebunden, damit der Runner MariaDB starten kann. Damit hat der Container weitreichende Rechte auf dem NAS.
