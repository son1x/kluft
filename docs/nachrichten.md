# Nachrichten-Eingang

Alle Nachrichten aus dem Kontaktformular und alle Fragen zu Veranstaltungen
an einer Stelle im Backend: **Verband & Kontakt → Nachrichten**. Die
zuständige Person (Kontaktthema bzw. Ansprechperson der Veranstaltung)
bekommt jede Nachricht wie bisher per Mail; die Verwaltung sieht zusätzlich,
was hereinkommt und ob es erledigt ist.

## Einschalten

**Verwaltung → Funktionen → Nachrichten-Eingang** (jederzeit an- und
abschaltbar). Beim DV ist die Funktion
an (seine Datenschutzerklärung sagt schon, dass Anfragen gespeichert
werden), sonst aus. Vor dem Einschalten die Datenschutzerklärung anpassen –
Stammes- und Bezirksvorlagen sagen bisher „nicht gespeichert“. Der Hinweis
unter den Formularen passt sich von selbst an.

## Wer sieht was

- Verwaltung, Admins und Super-Admins: alle Nachrichten.
- Stufen- und Sektionsadmins: gebündelt die Fragen zu den Veranstaltungen
  ihrer Stufe bzw. ihres Bereichs – im selben Menüpunkt (nur diese) und bei
  jeder Veranstaltung im Reiter **Fragen** neben „Teilnehmende“. Dort sieht
  sie jede Person, die die Teilnehmenden der Veranstaltung verwalten darf.
- Andere Rollen nicht – sie bekommen ihre Nachrichten per Mail.
- Menüpunkt und Reiter zeigen die Zahl der neuen Nachrichten.

## Arbeiten im Eingang

- Standardansicht: neue und in Bearbeitung befindliche Nachrichten; Filter
  für erledigte, Herkunft (Kontaktformular/Veranstaltung) und fehlgeschlagene
  Mails.
- „Ansehen“ zeigt die ganze Nachricht, an wen sie per Mail ging und ob das
  geklappt hat. „Per Mail antworten“ öffnet das eigene Mailprogramm.
- Status: neu → in Bearbeitung → erledigt (mit Notiz, z. B. „beantwortet von
  …“), wieder öffnen, löschen. Mehrere auf einmal erledigen oder löschen.
- Ist die Mail an die zuständige Person fehlgeschlagen, kommt die Nachricht
  trotzdem an (Kreuz in der Spalte „Mail“) – dann bitte selbst weitergeben.

## E-Mail-Adressen öffnen ein Formular

Funktion **„E-Mail-Adressen öffnen ein Formular“** (Verwaltung → Funktionen, Standard aus):

- **Was es ändert:** Ein Klick auf eine E-Mail-Adresse der Website öffnet ein Formular „Nachricht an …“ statt des Mailprogramms. Das gilt für Personenkarten, „Melde dich bei …“, die Kontaktadresse in Kopf und Fuß und Adressen im Seitentext.
- **Was bleibt:** Die Adresse steht weiter sichtbar daneben. Wer lieber das eigene Mailprogramm nimmt, klickt unten im Formular auf die Adresse.
- **Wohin die Nachricht geht:** per Mail an genau diese Adresse. Mit eingeschaltetem Nachrichten-Eingang liegt sie zusätzlich im Eingang, Art „Nachricht an eine Adresse“, Thema „An <Name>“. Ohne Nachrichten-Eingang gibt es keine Kopie.
- **Seitentext:** Umgeleitet werden nur Adressen der eigenen Domain(s) und veröffentlichte Adressen von Personen. Fremde Adressen, etwa die eines Ausrüsters, bleiben normale mailto-Links.
- **Veranstaltungen:** Die Kontaktadresse einer Veranstaltung führt zum Formular „Fragen zur Veranstaltung“ auf derselben Seite. So bleibt die Frage der Veranstaltung zugeordnet.
- **Schutz vor Missbrauch:** Die Adresse steckt signiert im Link (mit dem `APP_KEY`). Das Formular schreibt deshalb nur an Adressen, die die Website selbst zeigt, nicht an beliebige. Dazu gelten derselbe Spamschutz und dieselbe Begrenzung wie beim Kontaktformular (5 Nachrichten in 10 Minuten).

## Datenschutz

- Der Nachrichtentext wird verschlüsselt gespeichert.
- Gelöscht wird automatisch nach 6 Monaten (`NACHRICHTEN_AUFBEWAHRUNG_MONATE`,
  täglich `kontakt:prune`) – auch wenn die Funktion wieder ausgeschaltet ist.
- Auskunft und Löschung (Verwaltung → Datenschutz-Anfragen) umfassen den
  Eingang. Kopien in den Postfächern der Empfänger*innen sind davon nicht
  erfasst.
- Statuswechsel und Löschungen stehen im Protokoll.
