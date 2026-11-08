# Instagram-Konten: Einblendung und Teilen

Alle Instagram-Zugänge stehen an einer Stelle: **Verwaltung →
Instagram-Konten** (nur Admins). Ein Konto kann zwei Dinge:

- **Einblenden:** Die neuesten Beiträge erscheinen auf der Startseite oder auf
  Fachbereichsseiten, jeweils oberhalb von „Aktuelles“. Wer klickt, landet beim
  Beitrag auf Instagram.
- **Teilen:** Website-Beiträge werden mit „Auf Instagram teilen“ (im
  Beitrags-Editor) auf diesem Konto veröffentlicht. Das kann höchstens ein
  Konto (Haken **„Zum Teilen verwenden“**; setzt man ihn bei einem anderen
  Konto, wandert er).

Bis November 2026 hatte das Teilen ein eigenes Token unter „Integrationen“.
Die Migration `2026_11_09_120000_instagram_teilen_in_konten` hat es
übernommen: Gab es das Konto schon in der Einblendung, hat es Haken und Token
bekommen, sonst ist ein Konto ohne Einblendung entstanden. Die Seite
„Integrationen“ gibt es nicht mehr.

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
Problemen steht im Backend: Verwaltung → Instagram-Konten →
**„Anleitung: Token erzeugen“** (auch im Formular „Neu“). Kurzfassung:

**Voraussetzung:** Das Instagram-Konto ist ein **Business- oder Creator-Konto**
(in der Instagram-App: Einstellungen → Kontotyp).

1. **Meta-App:** Auf developers.facebook.com eine App anlegen (oder eine
   vorhandene nutzen) und das Produkt **„Instagram API mit Instagram-Login“**
   hinzufügen.
2. **Konto hinzufügen:** Dort unter „Zugangstoken generieren“ das
   Instagram-Konto hinzufügen und anmelden. Zum Einblenden reicht die
   Berechtigung `instagram_business_basic`; zum Teilen braucht das Token
   zusätzlich `instagram_business_content_publish`. Das angezeigte Token ist
   ein Long-Lived-Token (ca. 60 Tage).
3. **In Kluft eintragen:** Verwaltung → **Instagram-Konten** → „Neu“:
   - Anzeigename und Token eintragen;
   - bei **Einblenden auf** Startseite und/oder Fachbereiche ankreuzen
     (nichts ankreuzen, wenn das Konto nur zum Teilen dient);
   - die Anzahl der Beiträge wählen (2–12);
   - zum Teilen **„Zum Teilen verwenden“** einschalten.

   Kluft prüft das Token beim Speichern, mit Haken „Zum Teilen“ auch das
   Recht zum Veröffentlichen, und holt sofort die ersten Beiträge.

**Token-Erneuerung:** Kluft verlängert jedes Token selbst, ab 20 Tage vor
Ablauf: beim stündlichen Abruf und wöchentlich mit `instagram:refresh-token`
(auch für Konten, die nur teilen). Das klappt nur, solange das Token gültig
ist. Ist es abgelaufen (z. B. Passwort geändert oder App-Zugriff entzogen),
steht der Fehler in der Liste und auf der Statusseite, und die Admins bekommen
eine Mail. Dann ein neues Token erzeugen und beim Konto eintragen.

## Mehrere Konten

Jedes Konto bekommt ein eigenes Token. Stehen mehrere Konten auf derselben
Seite, werden ihre Beiträge gemischt, die neuesten zuerst. Die Zahl richtet
sich nach der höchsten „Anzahl“ der Konten. Unter den Bildern steht je Konto
ein Link „@name auf Instagram folgen“.

Konten von Stämmen oder anderen Gliederungen lassen sich nur einblenden, wenn
sie ihr Token selbst eintragen bzw. weitergeben (gleicher Weg wie oben).

## Technik

- **Tabellen:** `instagram_konten` (Token verschlüsselt, `teilen` für das
  Konto zum Teilen) und `instagram_beitraege`; Abruf
  `App\Services\Instagram\InstagramFeed`, Teilen über
  `App\Services\Instagram\InstagramClient` (nimmt `InstagramKonto::zumTeilen()`).
- **Scheduler:** `php artisan instagram:feed` stündlich,
  `php artisan instagram:refresh-token` wöchentlich.
- **Bilder:** nur von Instagram-Servern (`*.cdninstagram.com`,
  `*.fbcdn.net`), höchstens 640 px breit, als JPEG.
- **Statusseite:** „Instagram: Einblendung“ warnt, wenn ein Abruf scheitert
  oder länger als 6 Stunden her ist; „Instagram: Teilen“, wenn das Token bald
  abläuft oder einen Fehler hat.
