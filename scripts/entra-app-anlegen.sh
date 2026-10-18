#!/usr/bin/env bash
# App-Registrierung für die Anmeldung mit Microsoft 365 im eigenen Mandanten
# anlegen – ohne Klicken im Azure-Portal (docs/deploy.md). Braucht die
# Azure-CLI, angemeldet als Admin des Mandanten:
#
#   az login --tenant dpsg-roden.de --allow-no-subscriptions
#   scripts/entra-app-anlegen.sh roden ~/kluft-geheim/roden https://www.dpsg-roden.de [weitere Adressen …]
#
# Legt die App „Kluft <instanz>“ an (Single-Tenant, Redirect-URI je Adresse
# <adresse>/auth/entra/callback, delegierte Rechte openid, profile, email,
# User.Read, User.ReadBasic.All mit Administratorzustimmung) oder ergänzt eine
# bestehende gleichen Namens. Das Client-Secret (2 Jahre gültig) geht direkt
# in <ordner>/secrets.env und wird nicht angezeigt. Danach wie gewohnt:
#   scripts/environment-anlegen.sh <instanz> <ordner>
#
# Mit KLUFT_ZUWEISUNG=1 gilt „Zuweisung erforderlich“: Dann dürfen sich nur
# Personen oder Gruppen anmelden, die in der Unternehmensanwendung
# freigegeben sind. Ohne landet jede Person des Mandanten zunächst auf
# „wartet auf Freischaltung“, bis ihr im Backend eine Rolle gegeben wird.
set -euo pipefail

instanz=${1:?Instanz angeben, z. B. roden}
ordner=${2:?Ordner mit secrets.env angeben}
shift 2
[ $# -ge 1 ] || { echo "Mindestens eine Adresse angeben, z. B. https://www.dpsg-roden.de" >&2; exit 1; }

[[ $instanz =~ ^[a-z0-9-]+$ ]] || { echo "Ungültiger Instanzname: $instanz" >&2; exit 1; }
mkdir -p "$ordner"
ordner=$(cd "$ordner" && pwd)
wurzel=$(cd "$(dirname "$0")/.." && pwd)
case "$ordner/" in "$wurzel"/*) echo "Der Ordner liegt im Repository – Secrets gehören nicht hierher." >&2; exit 1 ;; esac
command -v az >/dev/null || { echo "Azure-CLI fehlt: https://learn.microsoft.com/cli/azure/install-azure-cli" >&2; exit 1; }

redirects=()
for url in "$@"; do
    [[ $url =~ ^https://[a-z0-9.-]+$ ]] || { echo "Adresse bitte als https://domain ohne Pfad: $url" >&2; exit 1; }
    redirects+=("$url/auth/entra/callback")
done

tenant=$(az account show --query tenantId -o tsv)
name="Kluft $instanz"
echo "Mandant: $tenant"

app=$(az ad app list --display-name "$name" --query '[0].appId' -o tsv)
if [ -z "$app" ]; then
    app=$(az ad app create --display-name "$name" --sign-in-audience AzureADMyOrg --query appId -o tsv)
    echo "App „$name“ angelegt."
else
    echo "App „$name“ gibt es schon – wird ergänzt."
fi
az ad app update --id "$app" --web-redirect-uris "${redirects[@]}"

# Microsoft Graph, delegiert: openid, profile, email, User.Read, User.ReadBasic.All
az ad app permission add --id "$app" --api 00000003-0000-0000-c000-000000000000 --api-permissions \
    37f7f235-527c-4136-accd-4a02d197296e=Scope 14dad69e-099b-42c9-810b-d002981feec1=Scope \
    64a6cdd6-aab1-4aaf-94b8-3cc8405e90d0=Scope e1fe6dd8-ba31-4d61-89e7-88639da4683d=Scope \
    b340eb25-3456-403f-be2f-af7a0d370277=Scope >/dev/null || true

az ad sp show --id "$app" >/dev/null 2>&1 || az ad sp create --id "$app" >/dev/null
if [ "${KLUFT_ZUWEISUNG:-0}" = "1" ]; then
    az ad sp update --id "$app" --set appRoleAssignmentRequired=true
    echo "„Zuweisung erforderlich“ ist an – Personen/Gruppen in der Unternehmensanwendung freigeben."
fi
# Kurz warten, bis die Unternehmensanwendung überall bekannt ist.
sleep 10
az ad app permission admin-consent --id "$app" \
    || echo "Administratorzustimmung bitte im Portal erteilen: App-Registrierungen → $name → API-Berechtigungen." >&2

# Secret direkt in die Datei, nie auf den Bildschirm.
geheim=$(az ad app credential reset --id "$app" --append --display-name "kluft-$(date +%Y-%m)" --years 2 --query password -o tsv)
datei="$ordner/secrets.env"
touch "$datei"
chmod 600 "$datei"
setze() {
    local tmp
    tmp=$(mktemp)
    chmod 600 "$tmp"
    grep -v -E "^$1=" "$datei" > "$tmp" || true
    printf '%s=%s\n' "$1" "$2" >> "$tmp"
    mv "$tmp" "$datei"
}
setze ENTRA_TENANT_ID "$tenant"
setze ENTRA_CLIENT_ID "$app"
setze ENTRA_CLIENT_SECRET "$geheim"
unset geheim

echo
echo "Fertig: ENTRA_TENANT_ID, ENTRA_CLIENT_ID und ENTRA_CLIENT_SECRET stehen in $datei."
echo "Das Secret läuft am $(date -d '+2 years' +%d.%m.%Y 2>/dev/null || date -v+2y +%d.%m.%Y) ab – dann dieses Skript erneut ausführen."
echo "Weiter: scripts/environment-anlegen.sh $instanz $ordner, danach neu deployen."
