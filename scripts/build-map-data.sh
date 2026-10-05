#!/usr/bin/env bash
# Erzeugt den selbstgehosteten Kartenausschnitt der Instanz (bbox aus der config)
# (Pflichtenheft F-MAP-04): Vektorkacheln aus dem Protomaps-Tagesbuild
# (OpenStreetMap-Daten, ODbL), per HTTP-Range-Requests ausgeschnitten.
# Die Datei ist zu groß fürs Repository und wird in der CI erzeugt.
#
# Benötigt: go-pmtiles (go install github.com/protomaps/go-pmtiles@v1.31.2)
# Aufruf:   scripts/build-map-data.sh [pfad/zu/go-pmtiles]
set -euo pipefail

PMTILES_BIN="${1:-go-pmtiles}"
# config/dpsg.php ohne Laravel lesen (KLUFT_INSTANZ aus der Umgebung wählt die Instanz).
MAP_PHP='if (! function_exists("env")) { function env($k, $d = null) { $v = getenv($k); return $v === false ? $d : $v; } } $m = (require "config/dpsg.php")["map"];'
BUILD_DATE="$(php -r "$MAP_PHP"' echo $m["build"];')"
TARGET="public/$(php -r "$MAP_PHP"' echo $m["pmtiles"];')"
BBOX="$(php -r "$MAP_PHP"' echo implode(",", $m["bbox"]);')"

if [ -f "$TARGET" ]; then
  echo "Kartendaten bereits vorhanden: $TARGET"
  exit 0
fi

mkdir -p "$(dirname "$TARGET")"
"$PMTILES_BIN" extract "https://build.protomaps.com/${BUILD_DATE}.pmtiles" "$TARGET" \
  --bbox="$BBOX" --maxzoom=13
ls -la "$TARGET"
