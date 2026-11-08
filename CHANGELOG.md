# Änderungen an Kluft

Je Version eine Überschrift `## x.y.z`; die Spiegelstriche darunter
erscheinen im Backend unter „Verwaltung → Updates“ und in der Mail an die
Admins. Kurz und aus Sicht der Redaktion schreiben.

## 0.9.0

- Roden: Kimberly und Flavio stehen auf der Seite „Stammesvorstand“ mit ihrer persönlichen Adresse statt vorstand@; die Vorstandsadresse bleibt im Seitentext.
- Der Vorstand pflegt die Vorstandsseite (Seite der Diözesan- bzw. Bezirksleitung oder des Stammesvorstands) und ihre Unterseiten selbst und kann sie veröffentlichen. Andere Seiten sieht er im Backend nicht mehr.
- Pro-Kopf-Zuschuss der Stadt Saarlouis: Die Liste der Jugendlichen kommt jetzt auf Knopfdruck direkt aus NaMi („Aus NaMi abrufen“), ohne Export-Datei; Anschriften werden in Etappen nachgeladen, gespeichert wird nichts. Der Datei-Upload bleibt als Ausweichweg.
- Mein Konto: „Meine Rollen und Rechte“ zeigt die eigenen Rollen mit Erklärung, seit wann und von wem; „Meine Benachrichtigungen“ bündelt die Hinweise per Mail (z. B. Spamverdacht) und den Stand der Push-Nachrichten.
- Instagram: Alle Zugänge stehen jetzt unter „Verwaltung → Instagram-Konten“. Das Konto für „Auf Instagram teilen“ ist dort mit „Zum Teilen verwenden“ markiert; das bisherige Token aus „Integrationen“ wurde übernommen, die Seite „Integrationen“ entfällt. Ein Token, eine Verlängerung, eine Warnung.
- Personen: „Rollen-Vorschläge prüfen“ schlägt für Ansprechpersonen eines Fachbereichs Sektionsadmin und für Stufenreferent*innen Stufenadmin vor, sobald ihr Konto verknüpft ist. Die Verwaltung hakt ab, die Person bekommt die übliche Mail.
- Mein Konto: „Mein Eintrag auf der Website“ – wer von der Verwaltung mit einem Personen-Eintrag verknüpft ist, ändert Vorstellungstext und Foto selbst und erlaubt die Veröffentlichung selbst oder zieht sie zurück. Name, Funktion und Gremium bleiben bei der Verwaltung. Kluft schlägt das passende Konto vor (gleiche E-Mail, sonst gleicher Name); die Verwaltung bestätigt nur noch („Konto-Vorschläge prüfen“).
- DV Trier: Scoutgames 2020 als digitaler Ersatz für das wegen Corona ausgefallene Diözesanlager beschrieben.
- Stämme und Bezirke: In der Fußzeile stehen unten rechts Zelte auf der Wiese, wo beim DV der Newsletter-Hinweis steht.
- DV Trier: Scoutgames 2020 im Aussehen der alten Seite scoutgames2020.info (Schriftzug, Wappen, Wegzeichen, Zelte auf der Wiese).
- DV Trier: Diözesanlager-Archiv um Gast>>FREUNDschaft 2015 (Ferschweiler) erweitert, mit eigenem Aussehen.
- Aufrufe: Gezählt werden nur noch echte Seitenaufrufe aus einem Browser; Programme, die sich als Browser ausgeben, fallen heraus.
- Push-Nachrichten fürs Handy gibt es jetzt in jeder Ausbaustufe, nicht erst im Vollausbau.
- Nachrichten-Eingang: täglicher Hinweis, wenn Nachrichten mit Spamverdacht warten, per Mail oder Push nach Wahl („Benachrichtigung“ im Eingang).
- Aufrufe: Anzeige „Gezählt seit …“ und Knopf „Alle Zähler löschen“ für Admins (Livegang: Statistik beginnt bei null).
- Seitenaufrufe zählen (Funktion „Seitenaufrufe zählen“): Verwaltung → Aufrufe zeigt, welche Seiten, Beiträge und Veranstaltungen wie oft aufgerufen werden, das Dashboard die meistgelesenen; beim Bearbeiten eines Beitrags oder einer Seite steht „Aufrufe: 30 Tage …, gesamt …“. Gezählt wird ohne IP-Adresse, Cookie oder Skript, ohne Bots und ohne angemeldete Redaktion.
- Kontaktformulare besser gegen Spam geschützt: Jedes Formular gilt nur einmal und zwei Stunden, dazu eine unsichtbare Rechenaufgabe im Browser (ohne Captcha, ohne Drittanbieter). Verdächtige Nachrichten (mit Link oder ohne Rechenaufgabe) gehen nicht mehr per Mail raus, sondern warten im Nachrichten-Eingang unter „Spamverdacht“; „Kein Spam – zustellen“ schickt sie doch. Ohne Nachrichten-Eingang steht „[Spamverdacht]“ im Betreff.
- Nachrichten-Eingang: „Erneut senden“ und „Weiterleiten“ (an eine andere Adresse, mit Hinweis), mit Verlauf unter „Ansehen“. Gehen Mails nur ins Server-Protokoll, steht das bei der Nachricht statt „zugestellt“.
- E-Mail-Versand: Live-Websites verschicken Mails jetzt auch ohne Test-Umleitung (Einstellung „KLUFT_MAIL_LIVE“). Gehen Mails nur ins Server-Protokoll, steht das deutlich unter Verwaltung → E-Mail-Versand und auf der Statusseite.
- Datenschutz-Einstellungen ohne die Auswahl „Statistik“, weil Kluft keine Statistik erhebt.
- DV Trier: Datenschutzerklärung auf Grundlage der abgestimmten Fassung von dpsg-trier.de, ergänzt um Anmeldungen, Umfragen, Nachrichten-Eingang, Videokonferenzen, Einbettungen und Löschfristen.
- Mediathek: Autor*innen, Stufen- und Sektionsadmins bearbeiten und löschen nur noch ihre eigenen Uploads; Vorstand und Verwaltung weiterhin alle. Hochladen und Auswählen bleibt für alle.
- NaMi: Ist NaMi unterwegs gesperrt (z. B. durch einen Proxy), zeigt „Mitglieder prüfen“ jetzt „nicht erreichbar“ statt eines Fehlers.
- Georgsbote: Titelbilder automatisch aus Seite 1 des PDFs – für alle Ausgaben ohne Titelbild mit „Titelbilder erzeugen“ in der Übersicht, für einzelne Ausgaben mit „Titelbild aus PDF erzeugen“ beim Bearbeiten; ein vorhandenes Titelbild wird nur nach Nachfrage ersetzt.
- Georgsbote: Kurzinhalt je Ausgabe (im Archiv sichtbar und durchsuchbar), zweiter Reiter „Georgsbrief“ für den Newsletter als PDF (Monat statt Ausgabe); beim Hochladen mehrerer PDFs erkennt Kluft „Georgsbrief_2019_02.pdf“ und übernimmt Titel und Inhalt aus der Archivliste der Instanz.
- Erste Version mit Updates über Kluft selbst (Verwaltung → Updates).
- Redaktion ohne Microsoft 365: Anmeldung per E-Mail-Code, Zugänge für Personen oder Rollen, NaMi-Prüfung.
- Startseite mit Titelbild der Website, auf Wunsch zufällig wechselnd.
- Fußzeile im Backend mit Kluft-Version und Info zum Namen.
- Altersangaben der Stufen selbst festlegen, je Grenze bis zu einem Jahr um den DPSG-Standard (Verwaltung → Stufen); auf der Website auf Wunsch als eigener Text, z. B. „3. bis 5. Klasse“.
- Veranstaltungen für Erwachsene: eigenverantwortliche Anmeldung ohne Abschnitt für Sorgeberechtigte, z. B. für Elternabende.
- Stammes-Websites: „Leitungsteam“ und „Leitungsrunde“ auf den Stufenseiten.
- Stammes-Websites: eigene Seite „Leiter*in werden“ im Menü (Text im Backend anpassbar, abschaltbar als Entwurf); der Aufruf zur Mitarbeit steht nicht mehr auf jeder Stufenseite.
- E-Mail-Adressen auf der Website öffnen auf Wunsch ein Formular an genau diese Person statt des Mailprogramms; mit dem Nachrichten-Eingang bleibt eine Kopie im Backend (Verwaltung → Funktionen).
- Kasse → Zahlungen: Forderungen mit Zahlungsaufforderung per Mail und GiroCode für die Banking-App, Zahlungseingang, Erstattung, Storno; Bankverbindung im Backend (Verwaltung → Funktionen → Zahlungen).
- Veranstaltungen mit Teilnahmebeitrag: Preisstufen je Veranstaltung, Auswahl bei der Anmeldung, Zahlungsaufforderung mit GiroCode nach der Bestätigung, Zahlungsstand in Teilnehmendenliste, Export und „Meine Anmeldungen“.
- Mitgliedsbeiträge: NaMi-Abruf nennt bei Fehlern Schritt und Meldung von NaMi, meldet sich bei Anmeldeseite statt Daten neu an und bricht bei einzelnen nicht abrufbaren Personen nicht mehr ab.
- Videokonferenzen (Jitsi): Konferenzen im Backend terminieren, Teilnahme-Link und Einladungstext zum Kopieren, Moderator-Link bis zum Ende des Folgetags (docs/videokonferenzen.md).
- Adressbuch (vorerst ausgeschaltet): Kontakte (auch Externe) und Verteiler, gemeinsam oder nur für mich; Adressen aus Outlook oder Excel einfügen, als Liste fürs Bcc kopieren; bei Videokonferenzen ganze Verteiler einladen; in der Datenschutzauskunft (docs/adressbuch.md).
- Videokonferenzen: Einladungen per E-Mail an Moderation (mit Moderator-Link) und Teilnehmende (nur Teilnahme-Link), je Person eine Mail mit Kalendereintrag; Nachladen auf der Detailseite; Adressen werden 7 Tage nach der Konferenz gelöscht.
- Testsystem kennzeichnen (Variable KLUFT_TESTSYSTEM): gelbes Band auf Website und im Backend, Suchmaschinen ausgesperrt, auf Wunsch nur nach Anmeldung; Deploy und Server-Prüfung auch für das Live-Ziel dv-trier-live.
- Personen: Ansprechperson für mehrere Fachbereiche (Mehrfachauswahl); die Person erscheint auf jeder gewählten Fachbereichsseite, in der Übersicht und bei den passenden Kontaktthemen.
- DV Trier: Diözesanlager-Archiv um Schongse 2004, futurscout 2011 und Scoutgames 2020 erweitert, jeweils mit eigenem Aussehen.
- DV Trier: Rubrik „Diözesanlager“ unter „Diözesanverband“; jedes Lager mit eigenem Aussehen im Inhaltsbereich. Erstes Archiv: „Scoutergy“ (FroLeiLa 2023) mit Video, Programm, häufigen Fragen und Beiträgen von scoutergy.de.
- Umfragen: anlegen, kopieren, Vorlagen, bedingte Fragen, Kommentar je Frage, Zugangscode, Nachfrage an Teilnehmende einer Veranstaltung, Gewinnspiel getrennt von den Antworten, Auswertung mit Balken und Einzelantworten, PDF (A4) und Excel, Löschfristen (docs/umfragen.md).
- Instagram-Einblendung: neueste Beiträge eigener Konten auf Startseite und Fachbereichen oberhalb von „Aktuelles“, Bilder lokal gespiegelt, kein Embed (docs/instagram.md).
- DV Trier: Diözesanleitung zeigt auch die Bildungsreferent*innen; darunter ein Kontaktformular an alle oder eine Person (config `gremien.<g>.auch` und `formular`).
- Mitgliedsbeiträge: Beitragsart von Hand, wenn NaMi die Details verweigert (Filter „Beitragsart fehlt“); „Halbjahr anlegen“ ohne NaMi; Liste springt auf das gewählte Halbjahr; Einstellungen dürfen länger als 255 Zeichen sein.
- Stammes-Websites: Sammelbestellung beim Rüsthaus (Verwaltung → Funktionen, braucht „Zahlungen“): Artikel per Rüsthaus-Nummer freigeben, Sperrliste, Bestellrunden mit Bestelldatum, Bestellformular unter /bestellen mit Bestätigung per Mail und Zahlungsaufforderung, freie Artikel nach Prüfung, Sammelliste als CSV; Rolle „Sammelbestellung (Rüsthaus)“. Zuerst im Testbetrieb: nur Angemeldete aus dem Backend sehen die Bestellseite, Testbestellungen lassen sich samt Zahlungen löschen; „Sammelbestellung für alle freigeben“ schaltet sie für alle frei.
- Sammelbestellung: Sets wie „Starterset“ (Größe je Teil, optionale Teile), Zahlungserinnerung am Vortag des Bestelldatums, Schließen storniert Unbezahltes mit Mail (Bestellungen lassen sich halten), „Ware ist da“-Mail, Rüsthaus-Rechnung mit Differenz, Ausgabeliste und Ausgabescheine als PDF mit Unterschrift, Abhaken der Ausgabe auch durch die Verwaltung, Mitgliedsprüfung gegen die Mitgliederliste, Löschfristen; Datenschutz-Anfragen erfassen Sammelbestellungen.
- Veranstaltungen: Zuschussanträge (Verwaltung → Funktionen). Je Veranstaltung „Zuschuss beantragen“ mit Art, Zuschussgebern und Kostenplan. Die Anmeldung fragt dann Anschrift, Geburtsdatum und Geschlecht ab. In der Teilnehmendenliste: Funktion (TN, Betreuung, Leitung, Referent*in), Juleica und Anwesenheit (auch je Tag), eine Übersicht mit fehlenden Angaben, die Teilnahmeliste als PDF für Jugendamt, Landesjugendamt, Stadt Saarlouis und Bistum Trier sowie eine Förderschätzung mit Rechenweg je Zuschussgeber.
- Zuschussanträge: Anträge und Berichte als PDF. Dazu gehören Formular A, Bericht D/E und Aufenthaltsbestätigung C für Land und Kreis sowie die Anträge der Kreisstadt Saarlouis und des Bistums Trier mit Programmfolge. Programm und Sachbericht stehen an der Veranstaltung. Bei den Personen gibt es interne Angaben für Zuschüsse (Anschrift, Geburtsdatum, Juleica mit Gültigkeit), mit „Leitung aus Personen übernehmen“. Neu ist die Nextcloud-Ablage: je Veranstaltung ein Ordner mit allen Unterlagen und einem Ordner für Belege, auf Knopfdruck und nachts.
- Zuschussanträge: Pro-Kopf-Zuschuss der Kreisstadt Saarlouis als PDF aus dem NaMi-Export, ohne dass Daten gespeichert werden. Dazu eine Erinnerung zwei Monate vor Ablauf der Juleica, die Spalte „Juleica bis“ bei den Personen und die Teilnahmeliste als Excel.
- Zuschussanträge: Zuschussgeber je Website ein- und ausschaltbar (Verwaltung → Zuschussgeber). Neben den Vorlagen für Land Saarland, Kreis und Stadt Saarlouis und Bistum Trier lassen sich eigene Zuschussgeber anlegen, etwa das Jugendamt eines anderen Kreises oder einer Stadt, mit Sätzen, Alter, Wohngebiet (Kreise und Gemeinden in Saarland und Rheinland-Pfalz) und Frist; dazu gibt es einen allgemeinen Antrag als PDF. Das Bistum Trier zählt jetzt auch Wohnorte in Rheinland-Pfalz.
- Zuschussanträge: eine Maske „Zuschussantrag“ je Veranstaltung mit allen Angaben (Antragsteller mit Bank und Leitung, Kosten, Programm, Bericht), daraus alle Anträge einzeln als PDF oder gesammelt als ZIP. Dazu Fristen je Zuschussgeber mit Erinnerung 14 und 3 Tage vorher, Bewilligungen (Bescheid, Aktenzeichen, Betrag, Auszahlung), die in die Anträge der Stadt und des Bistums übernommen werden, und eine Übersicht aller Anträge.
- Stammes-Websites: NaMi-Datenabgleich (Verwaltung → Funktionen). Mitglieder bzw. Eltern tragen ihre Daten unter /meine-daten ein und bestätigen per Mail. Kluft vergleicht sie und die Anmeldungen zu Veranstaltungen mit NaMi und zeigt eine Unterschiedsliste (Kasse → NaMi-Datenabgleich) mit Abhaken, PDF und Excel. Kluft schreibt nichts in NaMi; Meldungen und Ergebnisse werden nach Frist gelöscht.
- Sammelbestellung: Bestellseite als kompakte Liste wie ein Bestellformular (oben die Mitglieder, je Artikel Größe, Anzahl und „Hinzufügen“, unten die Übersicht je Mitglied), auch auf dem Handy übersichtlich; eigene Artikel des Stamms (z. B. Stammesaufnäher) im Backend anlegen, mit eigener Liste statt in der Rüsthaus-Sammelliste.
- Stammes-Websites: Adressen der Leitenden auf Wunsch nach Vorname (vorname@…), auch für die Leitungsteams.
- Stammes-Websites: Gruppenstunden unter „Verwaltung → Gruppenstunden“, auch mehrere Gruppen einer Stufe; sie erscheinen auf der Startseite („Unsere Gruppenstunden“) und auf der Stufenseite.
- Stammes-Websites: Menü „Unsere Gruppen · Mitmachen · Über uns · Termine · Aktuelles“; „Termine“ heißt bei Diözese und Bezirk weiter „Veranstaltungen“, der Kalender steht im Stamm darunter.
- Kasse: einheitlicher Beitrag je Halbjahr statt Aufteilung nach Beitragsart möglich; Schnuppermitglieder stehen markiert und ohne Beitrag in der Liste.
- Stufenfarben je Website anpassbar, z. B. gelbe Biber (Einrichtung, „stufenfarben“ in angaben.json).
- Einrichtungsassistent: Gruppenstundenzeiten je Stufe, NaMi-Abgleich für Lageranmeldungen; Vorbelegung für einen bestimmten Stamm, auch mit fertigen Seiten.
- Ausbaustufen von der Visitenkarte bis zum Vollausbau (Verwaltung → Ausbaustufe); im Assistenten Ort der Gruppenstunde je Gruppe.
- Stammes-Websites auch per GitHub deployen (erste: Roden); Environment per Kommandozeile anlegen (scripts/environment-anlegen.sh).
- Kasse für Stämme: Mitgliedsbeiträge je Halbjahr aus NaMi (Bundesbeitrag plus Stammesanteil), offene und bezahlte Beiträge abhaken, Abgleich mit der Rechnung des Bundesverbands (Rolle „Kasse“).
- NaMi-Testabfrage (Verwaltung): zeigt, was NaMi liefert, ohne Personendaten – zum Weitergeben an die Technik.
- Nachrichten-Eingang: Nachrichten aus dem Kontaktformular und Fragen zu Veranstaltungen zentral für die Verwaltung (Status, Notiz, Antworten per Mail); die zuständige Person bekommt sie weiter per Mail. Fragen zu Veranstaltungen sehen Stufen- und Sektionsadmins gebündelt, auch im Reiter „Fragen“ der Veranstaltung.
