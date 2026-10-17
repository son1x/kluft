# Änderungen an Kluft

Je Version eine Überschrift `## x.y.z`; die Spiegelstriche darunter
erscheinen im Backend unter „Verwaltung → Updates“ und in der Mail an die
Admins. Kurz und aus Sicht der Redaktion schreiben.

## 0.9.0

- Erste Version mit Updates über Kluft selbst (Verwaltung → Updates).
- Redaktion ohne Microsoft 365: Anmeldung per E-Mail-Code, Zugänge für Personen oder Rollen, NaMi-Prüfung.
- Startseite mit Titelbild der Website, auf Wunsch zufällig wechselnd.
- Fußzeile im Backend mit Kluft-Version und Info zum Namen.
- Altersangaben der Stufen selbst festlegen, je Grenze bis zu einem Jahr um den DPSG-Standard (Verwaltung → Stufen).
- Veranstaltungen für Erwachsene: eigenverantwortliche Anmeldung ohne Abschnitt für Sorgeberechtigte, z. B. für Elternabende.
- Stammes-Websites: „Leitungsteam“ und „Leitungsrunde“ auf den Stufenseiten.
- Einrichtungsassistent: Gruppenstundenzeiten je Stufe, NaMi-Abgleich für Lageranmeldungen; Vorbelegung für einen bestimmten Stamm, auch mit fertigen Seiten.
- Ausbaustufen von der Visitenkarte bis zum Vollausbau (Verwaltung → Ausbaustufe); im Assistenten Ort der Gruppenstunde je Gruppe.
- Stammes-Websites auch per GitHub deployen (erste: Roden); Environment per Kommandozeile anlegen (scripts/environment-anlegen.sh).
- Kasse für Stämme: Mitgliedsbeiträge je Halbjahr aus NaMi (Bundesbeitrag plus Stammesanteil), offene und bezahlte Beiträge abhaken, Abgleich mit der Rechnung des Bundesverbands (Rolle „Kasse“).
- NaMi-Testabfrage (Verwaltung): zeigt, was NaMi liefert, ohne Personendaten – zum Weitergeben an die Technik.
