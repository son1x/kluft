# Instanz „saar-hochwald“: DPSG Bezirk Saar-Hochwald

Stand: Gerüst aus der bisherigen Bezirkswebsite (son1x/dpsg-bezirk-saar-hochwald, Branch `claude/dpsg-homepage-release-1-0-vzey6o`). Die Inhalte bitte prüfen und ergänzen.

## Was hier liegt

| Datei | Inhalt |
|---|---|
| `config.php` | Name und Wortformen, Kontakt, Karte (bbox), Gremien, Newsletter, Startseiten-Kacheln, Einwilligungstext, ausgeschaltete DV-Funktionen |
| `seeders/BasisSeeder.php` | Grundbestand: Bezirk und Stämme, Glossar (läuft auch in den Tests) |
| `seeders/SaarHochwaldSeeder.php` | Startinhalt beim ersten Deploy: Grundbestand, Pflichtseiten, Startseiten, Weiterleitungen der alten `.html`-Adressen, Kontaktthemen |
| `seeders/*Seeder.php` | Inhalte aus der Vorlage (Seiten, Stämme, Impressum/Datenschutz) |
| `public/` | Logo und Favicon; `php artisan kluft:assets` kopiert sie nach `public/instanz/` |

## Regeln für die Arbeit an den Inhalten

- Nur Dateien in diesem Ordner ändern. Fehlt etwas im gemeinsamen Code, beschreiben statt ändern.
- Format wie `instanzen/dv-trier/config.php`.
- Seeder legen Startinhalt an und dürfen später gepflegte Inhalte nicht überschreiben (`firstOrCreate` statt `updateOrCreate`, wo Redaktion nacharbeitet).
- Neue Seeder-Klassen brauchen `composer dump-autoload` (Classmap).
- Prüfen: `KLUFT_INSTANZ=saar-hochwald php artisan test` (Tests in `tests/Feature/Instanzen/SaarHochwaldTest.php`).
- Lokal ansehen: `KLUFT_INSTANZ=saar-hochwald php artisan kluft:assets`, dann `php artisan migrate:fresh --seed`.

## Offen

- Inhalte mit dem Bezirk abstimmen (Bezirksleitung, Stämme mit Standorten, Prävention, Impressum/Datenschutz).
- Kartendaten `maps/data/saar-hochwald-…pmtiles` werden beim Deploy gebaut (Schritt 6).
- Webspace, Zugangsdaten und Domain: GitHub-Environment `saar-hochwald` (Schritt 6).
