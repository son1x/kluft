#!/usr/bin/env bash
# GitHub-Environment einer Kluft-Instanz anlegen oder aktualisieren – ohne
# Klicken auf github.com (docs/deploy.md). Braucht die GitHub-CLI, angemeldet
# mit Admin-Recht am Repository (gh auth login).
#
#   scripts/environment-anlegen.sh roden ~/kluft-geheim/roden
#
# Im Ordner – AUSSERHALB des Repositories – liegen (Vorlagen in
# docs/environment-vorlage/):
#   variablen.env  APP_URL=…, MAIL_FROM_ADDRESS=…, PHP_BIN=…  (sichtbar)
#   secrets.env    SSH_HOST=…, DB_PASSWORD=…, BREVO_SMTP_KEY=… (verschlüsselt)
#   ssh_key        privater Deploy-Schlüssel (optional, wird SSH_PRIVATE_KEY)
# Fehlt eine Datei, bleibt der Teil unverändert. Bestehende Werte mit gleichem
# Namen werden überschrieben, andere bleiben. Am Ende stehen nur die Namen da.
set -euo pipefail

instanz=${1:?Instanz angeben, z. B. roden}
ordner=${2:?Ordner mit variablen.env / secrets.env angeben}
repo=${KLUFT_REPO:-son1x/kluft}

[[ $instanz =~ ^[a-z0-9-]+$ ]] || { echo "Ungültiger Instanzname: $instanz" >&2; exit 1; }
[ -f "$(dirname "$0")/../instanzen/$instanz/config.php" ] || echo "Hinweis: instanzen/$instanz/config.php gibt es (noch) nicht." >&2
ordner=$(cd "$ordner" && pwd)
wurzel=$(cd "$(dirname "$0")/.." && pwd)
case "$ordner/" in "$wurzel"/*) echo "Der Ordner liegt im Repository – Secrets gehören nicht hierher." >&2; exit 1 ;; esac
command -v gh >/dev/null || { echo "GitHub-CLI fehlt: https://cli.github.com" >&2; exit 1; }

# Nur Zeilen mit Wert übernehmen (Kommentare und „NAME=“ ohne Wert weglassen).
gefiltert=$(mktemp)
chmod 600 "$gefiltert"
trap 'rm -f "$gefiltert"' EXIT
mit_wert() { grep -E '^[A-Z][A-Z0-9_]*=.+' "$1" > "$gefiltert" || true; }

gh api --method PUT "repos/$repo/environments/$instanz" --silent
echo "Environment $instanz in $repo bereit."

if [ -f "$ordner/variablen.env" ]; then
    mit_wert "$ordner/variablen.env"
    if [ -s "$gefiltert" ]; then gh variable set --repo "$repo" --env "$instanz" --env-file "$gefiltert"; fi
fi
if [ -f "$ordner/secrets.env" ]; then
    mit_wert "$ordner/secrets.env"
    if [ -s "$gefiltert" ]; then gh secret set --repo "$repo" --env "$instanz" --env-file "$gefiltert"; fi
fi
if [ -f "$ordner/ssh_key" ]; then
    gh secret set SSH_PRIVATE_KEY --repo "$repo" --env "$instanz" < "$ordner/ssh_key"
fi

echo
echo "Variablen:"
gh variable list --repo "$repo" --env "$instanz"
echo
echo "Secrets (nur Namen):"
gh secret list --repo "$repo" --env "$instanz"
echo
echo "Weiter: Actions → Deploy → Run workflow → $instanz mit „Nur prüfen“."
