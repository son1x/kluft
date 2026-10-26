# Livegang www.dpsg-trier.de

**Termin:** Totensonntag, 22. November 2026 (Umschalten am Abend des 21. oder
am 22.11.). Meilenstein „Livegang“ in der Roadmap (`/roadmap`).

## Entscheidungen (26.10.2026)

- **Inhalte:** Die Live-Website startet mit allen Inhalten und Einstellungen
  aus test.dpsg-trier.de (Seiten, Beiträge, Personen, Veranstaltungen,
  Kalender, Mediathek, Funktionsschalter, Benutzer und Rollen).
- **Test bleibt:** test.dpsg-trier.de läuft weiter als Testsystem, deutlich
  gekennzeichnet bzw. geschlossen. Dort werden neue Funktionen ausprobiert.
  Inhalte werden nach dem Livegang nur noch auf Live gepflegt.
- **Weg:** der erprobte Deploy über GitHub Actions. Das Update-System
  (`docs/updates.md`) ist für Stammes-Installationen gedacht und wird erst
  danach an einem Stamm erprobt.

## Aufbau

| | Test | Live |
|---|---|---|
| Adresse | test.dpsg-trier.de | www.dpsg-trier.de (Probelauf: neu.dpsg-trier.de) |
| GitHub-Environment | `dv-trier` | `dv-trier-live` |
| Ordner und Datenbank | wie bisher | neu, eigener Ordner und eigene MariaDB |
| Mails | an die Testadresse (außer Test-Empfänger*innen) | an die echten Empfänger*innen |
| Suchmaschinen | ausgesperrt | erlaubt |
| Hinweis | Band „Testsystem“ auf jeder Seite, auf Wunsch nur nach Anmeldung | – |

Beide nutzen den Instanz-Ordner `instanzen/dv-trier`. Unterschiede kommen nur
aus den Secrets und Variablen des Environments.

## Zeitplan

| Bis | Was | Wer |
|---|---|---|
| 1.11. | Webspace und Datenbank für Live, vorläufige Adresse, Environment `dv-trier-live`, Entra-URIs (Roadmap „Livegang 1–3“) | Projektleitung |
| 8.11. | Deploy auf zwei Ziele, Workflow „Test → Live übernehmen“, Kennzeichnung des Testsystems | Technik |
| 15.11. | Inhalte fertig (WordPress-Beiträge, Impressum, Personen, Fachbereiche, Bolivien), Datenschutzerklärung (Roadmap „Livegang 4“) | Redaktion |
| ~18.11. | Probelauf auf neu.dpsg-trier.de mit Kopie der Inhalte | beide |
| 21./22.11. | Umschalten (unten) | beide |

## Ablauf am Livetag

1. **Redaktionsstopp auf Test:** ab dem verabredeten Zeitpunkt keine
   Änderungen mehr auf test.dpsg-trier.de.
2. **Sichern:** Backup von Test und der alten WordPress-Seite (Plesk).
3. **Übernehmen:** Workflow „Test → Live übernehmen“ (Datenbank und
   Dateien, mit Anpassungen für Live).
4. **Domain:** In Plesk dpsg-trier.de und www auf den Live-Ordner zeigen
   lassen, Let’s-Encrypt-Zertifikat. Im Environment `dv-trier-live`
   APP_URL auf https://www.dpsg-trier.de, dann Deploy „Live“.
5. **Prüfen:**
   - Startseite und `/api/status`;
   - Anmeldung mit Microsoft 365;
   - Kontaktformular mit echter Mail;
   - eine alte WordPress-Adresse (Weiterleitung);
   - Systemstatus im Backend.
6. **Danach:**
   - bolivienpartnerschaft.de und beraten-online.de als Aliasse;
   - MONITOR_URL im Environment auf www.dpsg-trier.de;
   - Cronjob und Backups der Live-Website prüfen;
   - alte WordPress-Seite abschalten, sobald alles läuft.
7. **Testsystem schließen:** Schalter für das Testsystem setzen (Band,
   noindex, auf Wunsch nur nach Anmeldung).

**Zurück:** Klappt etwas nicht, zeigt die Domain wieder auf die alte
WordPress-Seite. Test und die Sicherung bleiben unverändert.
