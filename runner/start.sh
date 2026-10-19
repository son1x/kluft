#!/usr/bin/env bash
# Meldet den Runner beim Start am Repository an und beim Stoppen wieder ab.
# Den Anmelde-Token holt er sich mit dem Zugriffstoken aus .env; beides
# erscheint nie im Log.
set -euo pipefail

: "${GITHUB_REPO:?GITHUB_REPO fehlt, z. B. son1x/kluft}"
: "${GITHUB_PAT:?GITHUB_PAT fehlt (Fine-grained Token, Repository-Recht „Administration: Read and write“)}"
NAME=${RUNNER_NAME:-nas}
LABELS=${RUNNER_LABELS:-nas}

token() {
    curl -fsS -X POST \
        -H "Authorization: Bearer ${GITHUB_PAT}" \
        -H "Accept: application/vnd.github+json" \
        -H "X-GitHub-Api-Version: 2022-11-28" \
        "https://api.github.com/repos/${GITHUB_REPO}/actions/runners/$1" | jq -r .token
}

cd /home/runner
# Nach einem Neustart des Containers liegt die alte Anmeldung noch da.
rm -f .runner .credentials .credentials_rsaparams
./config.sh --unattended --replace \
    --url "https://github.com/${GITHUB_REPO}" \
    --token "$(token registration-token)" \
    --name "$NAME" --labels "$LABELS" --work /home/runner/_work

abmelden() {
    ./config.sh remove --token "$(token remove-token)" || true
}

./run.sh &
pid=$!
trap 'kill -INT "$pid" 2>/dev/null || true; wait "$pid" || true; abmelden; exit 0' TERM INT
wait "$pid"
