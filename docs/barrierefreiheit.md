# Barrierefreiheit und Bedienung am Handy

Grundlage: WCAG 2.2 AA (Pflichtenheft 7.3) und die Apple Human Interface
Guidelines (HIG) für die Bedienung mit dem Finger. Geprüft wird automatisch
im Workflow „Barrierefreiheit und Performance“ (`scripts/web-checks.mjs`,
jeden Montag und auf Knopfdruck).

## Regeln (zentral in `resources/css/app.css`, Schicht `base`)

| Regel | Umsetzung |
|---|---|
| Keine seitliche Verschiebung, auch auf 320 px (iPhone SE) | lange Wörter trennen (`hyphens: auto` ab 12 Zeichen) bzw. umbrechen (`overflow-wrap: anywhere` in Inhalt und Fußzeile); Glossar-Erklärungen am Handy als Leiste unten |
| Tippflächen mindestens 44 × 44 pt | auf Touch-Geräten Mindesthöhe für Felder, Knöpfe, Brotkrumen, Fußzeilen-Links und Ankreuz-Zeilen; Menü-Knopf 44 × 44; Link-Knöpfe mit `min-h-11` |
| Eingabefelder mindestens 16 px | sonst zoomt Safari beim Antippen in die Seite (`font-size: 1rem !important` auf Touch-Geräten) |
| Lesbare Schrift | Hilfetexte unter Feldern 14 px (`text-sm`), Bildnachweise mindestens 11 px |
| Lesbare Zeilen | Fließtext in breiten Textbereichen höchstens ca. 75 Zeichen |
| Cookie-Hinweis verdeckt wenig | kurzer Text, „Alle akzeptieren“ und „Nur notwendige“ gleichwertig, Einzelauswahl unter „Einstellungen“ |

## Neue Vorlagen

- Knöpfe und Link-Knöpfe: `inline-flex min-h-11 items-center` (bzw. `min-h-11`).
- Keine Feldschrift unter 16 px erzwingen; `text-sm` an Feldern wird am Handy überstimmt.
- Hilfetexte `text-sm text-text-secondary`, nicht `text-xs`.
- Lange Fließtexte nicht ohne Begrenzung über die volle Breite (`max-w-3xl` oder `.prose`).

## Bewusst offen

- Markierungen auf Teamfotos skalieren mit dem Foto (am Handy ca. 30 px); die
  Namensliste darunter („Von links: …“) ist die zugängliche Alternative.
- Dark Mode der öffentlichen Website (eigenes Vorhaben).
