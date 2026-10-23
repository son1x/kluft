# Instagram-Einblendung

Die neuesten Beiträge **eigener** Instagram-Konten erscheinen auf der
Startseite oder auf Fachbereichsseiten, jeweils oberhalb von „Aktuelles“.
Wer klickt, landet beim Beitrag auf Instagram.

Das Teilen von Website-Beiträgen auf Instagram („Auf Instagram teilen“,
Verwaltung → Integrationen) ist eine eigene Funktion und hier nicht gemeint.

## Datenschutz

Auf der Website gibt es kein Instagram-Embed und keine Skripte von Meta
(Pflichtenheft F-IG-07).

- **Bilder:** Kluft holt Bild und Bildunterschrift stündlich über die
  offizielle API und legt die Bilder verkleinert auf dem eigenen Server ab
  (`public/uploads/instagram-feed`).
- **Beim Aufruf:** Besucher*innen laden nichts von Instagram. Erst ein Klick
  auf einen Beitrag öffnet instagram.com.
- **Aufräumen:** Ältere Beiträge und auf Instagram gelöschte verschwinden
  beim nächsten Abruf samt Bild.
- **Backup:** Die gespiegelten Bilder sind nicht im Backup; sie werden neu
  geholt.

## Konto einrichten

Eine ausführliche Schritt-für-Schritt-Anleitung mit Links und Hilfe bei
Problemen steht im Backend: Verwaltung → Instagram-Einblendung →
**„Anleitung: Token erzeugen“** (auch im Formular „Neu“). Kurzfassung:

**Voraussetzung:** Das Instagram-Konto ist ein **Business- oder Creator-Konto**
(in der Instagram-App: Einstellungen → Kontotyp).

1. **Meta-App:** Auf developers.facebook.com eine App anlegen (oder die
   vorhandene für „Auf Instagram teilen“ nutzen) und das Produkt **„Instagram
   API mit Instagram-Login“** hinzufügen.
2. **Konto hinzufügen:** Dort unter „Zugangstoken generieren“ das
   Instagram-Konto hinzufügen und anmelden. Nötig ist nur die Berechtigung
   `instagram_business_basic`. Das angezeigte Token ist ein Long-Lived-Token
   (ca. 60 Tage).
3. **In Kluft eintragen:** Verwaltung → **Instagram-Einblendung** → „Neu“:
   - Anzeigename und Token eintragen;
   - bei **Anzeigen auf** Startseite und/oder Fachbereiche ankreuzen;
   - die Anzahl der Beiträge wählen (2–12).

   Kluft prüft das Token beim Speichern und holt sofort die ersten Beiträge.

**Token-Erneuerung:** Kluft verlängert das Token selbst, rechtzeitig vor
Ablauf. Das klappt nur, solange es gültig ist. Ist es abgelaufen (z. B.
Passwort geändert oder App-Zugriff entzogen), steht der Fehler in der Liste
und auf der Statusseite. Dann ein neues Token erzeugen und beim Konto
eintragen.

## Mehrere Konten

Jedes Konto bekommt ein eigenes Token. Stehen mehrere Konten auf derselben
Seite, werden ihre Beiträge gemischt, die neuesten zuerst. Die Zahl richtet
sich nach der höchsten „Anzahl“ der Konten. Unter den Bildern steht je Konto
ein Link „@name auf Instagram folgen“.

Konten von Stämmen oder anderen Gliederungen lassen sich nur einblenden, wenn
sie ihr Token selbst eintragen bzw. weitergeben (gleicher Weg wie oben).

## Technik

- **Tabellen:** `instagram_konten` (Token verschlüsselt) und
  `instagram_beitraege`; Abruf `App\Services\Instagram\InstagramFeed`.
- **Scheduler:** `php artisan instagram:feed` stündlich.
- **Bilder:** nur von Instagram-Servern (`*.cdninstagram.com`,
  `*.fbcdn.net`), höchstens 640 px breit, als JPEG.
- **Statusseite:** Prüfung „Instagram-Einblendung“; sie warnt, wenn ein
  Abruf scheitert oder länger als 6 Stunden her ist.
