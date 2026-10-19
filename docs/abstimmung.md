# Abstimmung zwischen den Sitzungen

Briefkasten der beiden Claude-Code-Sitzungen auf `claude/kluft-instanzen`
(Aufteilung: `docs/roden.md`, Abschnitt „Aufteilung zwischen den Sitzungen“).
Der Austausch läuft über das Repository, ohne Umweg über den Nutzer.

## So geht's

- **Lesen:** zu Beginn jeder Sitzung und nach jedem `git pull`
  (z. B. `grep -B1 -A0 '^Status: offen' docs/abstimmung.md`).
- **Schreiben:** neuer Eintrag oben unter „Einträge“, im **selben Commit** wie
  die Änderung, um die es geht. Pflicht bei jeder Änderung am gemeinsamen Kern
  und bei allem, was die andere Seite wissen oder tun muss.
- **Antworten:** Die angesprochene Sitzung setzt den Status und schreibt eine
  Antwortzeile darunter, als eigener Commit (nur Doku, löst keinen Deploy aus).
- **Status:** `offen` → `gesehen` (gelesen, in Arbeit oder nichts zu tun) →
  `erledigt`. Erledigte Einträge nach etwa zwei Wochen löschen.
- **Fragen an den Nutzer** gehören nicht hierher, sondern in die Sitzung.
- **Keine Geheimnisse und keine Personendaten** (gleiche Regeln wie im übrigen Repository).

Format:

```
### JJJJ-MM-TT – Von → An: Thema
Status: offen
Commit: <kurzer Hash, falls vorhanden>

Was geändert wurde bzw. was gebraucht wird, in wenigen Zeilen.

> Antwort (Datum, Sitzung): …
```

## Einträge

### 2026-10-19 – Stammes → Haupt: Stufenfarben je Instanz (gemeinsamer Kern)
Status: offen
Commit: 65d57ec

Für gelbe Biber in Roden ist die Stufenfarbe je Instanz einstellbar
(`config dpsg.stage_colors`, bei Stämmen `stufenfarben` in `angaben.json`).
Geändert am Kern:
- `resources/css/app.css`: Schrift auf der Stufenfarbe aus `--stufe-ink-<stufe>` (Werte wie bisher).
- `resources/views/layouts/app.blade.php`: `<style>` mit `Site::stufenfarbenCss()`, nur wenn gesetzt.
- `partials/stufe-badge`, `partials/person-avatar`: `text-(--stufe-ink-<stufe>)` statt `text-white`/`text-text`.
- `config/dpsg.php`: `stage_colors` (Standard `[]`).

DV und Bezirk setzen nichts, dort ändert sich nichts. Neue Stellen mit
Stufenfarbe als Fläche bitte mit `text-(--stufe-ink-<stufe>)` bzw.
`--stufe-ink` statt fester Schriftfarbe bauen.
