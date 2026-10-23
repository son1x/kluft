# Änderungen an Kluft

Je Version eine Überschrift `## x.y.z`; die Spiegelstriche darunter
erscheinen im Backend unter „Verwaltung → Updates“ und in der Mail an die
Admins. Kurz und aus Sicht der Redaktion schreiben.

## 0.9.0

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
- Mitgliedsbeiträge: Beitragsart von Hand, wenn NaMi die Details verweigert (Filter „Beitragsart fehlt“); „Halbjahr anlegen“ ohne NaMi; Liste springt auf das gewählte Halbjahr; Einstellungen dürfen länger als 255 Zeichen sein.
- Stammes-Websites: Sammelbestellung beim Rüsthaus (Verwaltung → Funktionen, braucht „Zahlungen“): Artikel per Rüsthaus-Nummer freigeben, Sperrliste, Bestellrunden mit Bestelldatum, Bestellformular unter /bestellen mit Bestätigung per Mail und Zahlungsaufforderung, freie Artikel nach Prüfung, Sammelliste als CSV; Rolle „Sammelbestellung (Rüsthaus)“. Zuerst im Testbetrieb: nur Angemeldete aus dem Backend sehen die Bestellseite, Testbestellungen lassen sich samt Zahlungen löschen; „Sammelbestellung für alle freigeben“ schaltet sie für alle frei.
- Sammelbestellung: Sets wie „Starterset“ (Größe je Teil, optionale Teile), Zahlungserinnerung am Vortag des Bestelldatums, Schließen storniert Unbezahltes mit Mail (Bestellungen lassen sich halten), „Ware ist da“-Mail, Rüsthaus-Rechnung mit Differenz, Ausgabeliste und Ausgabescheine als PDF mit Unterschrift, Abhaken der Ausgabe auch durch die Verwaltung, Mitgliedsprüfung gegen die Mitgliederliste, Löschfristen; Datenschutz-Anfragen erfassen Sammelbestellungen.
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
