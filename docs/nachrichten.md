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

## Spamschutz und Spamverdacht

Alle Formulare (Kontakt, Frage zur Veranstaltung, Nachricht an eine Adresse,
Gremium) haben ohne Captcha und ohne Drittanbieter:

- ein unsichtbares Feld, das nur Bots ausfüllen,
- eine Zeitfalle: Wer schneller als 5 Sekunden absendet, ist ein Bot; ein
  Formular gilt 2 Stunden und **jede Anzeige nur für eine Absendung**
  (Spam-Programme, die ein Formular einmal laden und immer wieder schicken,
  kommen nicht durch),
- ein Rate-Limit je IP-Adresse.

Das wird still verworfen, Absender*innen sehen trotzdem „gesendet“.

Dazu kommt ein **Arbeitsnachweis** (ALTCHA, selbst gehostet, npm `altcha`):
Sobald jemand ins Formular klickt, holt der Browser unsichtbar eine Aufgabe
von `/spamschutz/aufgabe` und rechnet sie aus (etwa eine Sekunde,
PBKDF2/SHA-256). Kluft prüft die Lösung (`App\Support\Arbeitsnachweis`),
jede gilt nur einmal. Für Menschen unsichtbar, für Massen-Spam teuer. Keine
Anfragen an Dritte, kein Cookie. Die Schwierigkeit lässt sich mit
`KLUFT_ARBEITSNACHWEIS_KOSTEN` (Standard 5000) und
`KLUFT_ARBEITSNACHWEIS_PRAEFIX` (Standard `00`) einstellen.

**Spamverdacht** wird **nicht verworfen**, damit keine echte Nachricht
verloren geht. Verdacht besteht bei einem Link in Nachricht, Betreff oder
Name und bei fehlendem oder ungültigem Arbeitsnachweis (z. B. ohne
JavaScript). Die Gründe stehen in der Notiz.

- **Mit Nachrichten-Eingang:** keine Mail; die Nachricht liegt im Eingang mit
  Status „Spamverdacht“ und dem Grund in der Notiz. Die Standardansicht zeigt
  sie nicht, die Unterzeile nennt die Anzahl (Filter „Status“ →
  Spamverdacht). **„Kein Spam – zustellen“** mailt sie doch an die
  zuständige Person und setzt sie auf „neu“. Spamverdacht wird nach
  **30 Tagen** gelöscht.
- **Ohne Nachrichten-Eingang:** Die Mail geht raus, mit „[Spamverdacht]“ am
  Anfang des Betreffs; im Postfach lässt sich danach filtern.

Echte Nachrichten mit Link (z. B. „unser Bericht steht unter …“) landen
also ebenfalls unter Spamverdacht. Bitte die Liste ab und zu ansehen.

## E-Mail-Adressen öffnen ein Formular

Funktion **„E-Mail-Adressen öffnen ein Formular“** (Verwaltung → Funktionen, Standard aus):

- **Was es ändert:** Ein Klick auf eine E-Mail-Adresse der Website öffnet ein Formular „Nachricht an …“ statt des Mailprogramms. Das gilt für Personenkarten, „Melde dich bei …“, die Kontaktadresse in Kopf und Fuß und Adressen im Seitentext.
- **Was bleibt:** Die Adresse steht weiter sichtbar daneben. Wer lieber das eigene Mailprogramm nimmt, klickt unten im Formular auf die Adresse.
- **Wohin die Nachricht geht:** per Mail an genau diese Adresse. Mit eingeschaltetem Nachrichten-Eingang liegt sie zusätzlich im Eingang, Art „Nachricht an eine Adresse“, Thema „An <Name>“. Ohne Nachrichten-Eingang gibt es keine Kopie.
- **Seitentext:** Umgeleitet werden nur Adressen der eigenen Domain(s) und veröffentlichte Adressen von Personen. Fremde Adressen, etwa die eines Ausrüsters, bleiben normale mailto-Links.
- **Veranstaltungen:** Die Kontaktadresse einer Veranstaltung führt zum Formular „Fragen zur Veranstaltung“ auf derselben Seite. So bleibt die Frage der Veranstaltung zugeordnet.
- **Schutz vor Missbrauch:** Die Adresse steckt signiert im Link (mit dem `APP_KEY`). Das Formular schreibt deshalb nur an Adressen, die die Website selbst zeigt, nicht an beliebige. Dazu gelten derselbe Spamschutz und dieselbe Begrenzung wie beim Kontaktformular (5 Nachrichten in 10 Minuten).

## Formular auf einer Gremiumsseite

Unter einer Gremiumsseite (DV Trier: Diözesanleitung) steht ein
Kontaktformular, wenn in der Instanz-Konfiguration bei
`gremien.<gremium>.formular` `true` steht. Es schreibt an alle Personen der
Seite oder an eine ausgewählte Person; die Adressen kommen nur aus den
Personen der Seite.

Die Nachricht steht im Eingang mit der Herkunft „Nachricht an ein Gremium“.
Mit `gremien.<gremium>.auch` (Gremium und Untergruppe) erscheinen Personen
anderer Gremien zusätzlich auf der Seite und im Formular. Bei der
Diözesanleitung sind das die Bildungsreferent*innen aus dem Diözesanbüro
(Untergruppe „Bildungsreferent*innen“, wie im Backend eingetragen).

## Datenschutz

- Der Nachrichtentext wird verschlüsselt gespeichert.
- Gelöscht wird automatisch nach 6 Monaten (`NACHRICHTEN_AUFBEWAHRUNG_MONATE`,
  täglich `kontakt:prune`) – auch wenn die Funktion wieder ausgeschaltet ist.
- Auskunft und Löschung (Verwaltung → Datenschutz-Anfragen) umfassen den
  Eingang. Kopien in den Postfächern der Empfänger*innen sind davon nicht
  erfasst.
- Statuswechsel und Löschungen stehen im Protokoll.
