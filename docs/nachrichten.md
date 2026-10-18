# Nachrichten-Eingang

Alle Nachrichten aus dem Kontaktformular und alle Fragen zu Veranstaltungen
an einer Stelle im Backend: **Verband & Kontakt → Nachrichten**. Die
zuständige Person (Kontaktthema bzw. Ansprechperson der Veranstaltung)
bekommt jede Nachricht wie bisher per Mail; die Verwaltung sieht zusätzlich,
was hereinkommt und ob es erledigt ist.

## Einschalten

**Verwaltung → Funktionen → Nachrichten-Eingang.** Beim DV ist die Funktion
an (seine Datenschutzerklärung sagt schon, dass Anfragen gespeichert
werden), sonst aus. Vor dem Einschalten die Datenschutzerklärung anpassen –
Stammes- und Bezirksvorlagen sagen bisher „nicht gespeichert“. Der Hinweis
unter den Formularen passt sich von selbst an.

## Wer sieht was

- Verwaltung, Admins und Super-Admins: alle Nachrichten. Andere Rollen
  nicht – sie bekommen ihre Nachrichten per Mail.
- Der Menüpunkt zeigt die Zahl der neuen Nachrichten.

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

## Datenschutz

- Der Nachrichtentext wird verschlüsselt gespeichert.
- Gelöscht wird automatisch nach 6 Monaten (`NACHRICHTEN_AUFBEWAHRUNG_MONATE`,
  täglich `kontakt:prune`) – auch wenn die Funktion wieder ausgeschaltet ist.
- Auskunft und Löschung (Verwaltung → Datenschutz-Anfragen) umfassen den
  Eingang. Kopien in den Postfächern der Empfänger*innen sind davon nicht
  erfasst.
- Statuswechsel und Löschungen stehen im Protokoll.
