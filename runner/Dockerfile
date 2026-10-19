# Selbst gehosteter GitHub-Actions-Runner für Kluft, z. B. auf dem NAS
# (docs/runner.md). Basis ist das offizielle Runner-Image von GitHub; der
# Runner aktualisiert sich danach selbst.
FROM ghcr.io/actions/actions-runner:2.338.0

USER root
# Was die Kluft-Workflows außer PHP und Node brauchen (die installieren
# setup-php bzw. setup-node selbst): jq für API-Abfragen, lftp für den Upload.
RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl git jq lftp unzip \
    && rm -rf /var/lib/apt/lists/* \
    && mkdir -p /home/runner/_work \
    && chown runner:runner /home/runner/_work
COPY --chmod=755 start.sh /start.sh

USER runner
ENTRYPOINT ["/start.sh"]
