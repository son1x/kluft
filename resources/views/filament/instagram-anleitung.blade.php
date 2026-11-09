{{-- Anleitung: Instagram-Zugangstoken für Einblendung und Teilen erzeugen (docs/instagram.md).
     Eigenes CSS statt Tailwind-Klassen: Das Backend liefert nur die Klassen aus,
     die Filament selbst nutzt; Klassen wie list-decimal kämen hier nicht an. --}}
<style>
    .iga { --iga-akzent: var(--primary-600); --iga-rand: var(--gray-200); --iga-flaeche: var(--gray-50); --iga-leise: var(--gray-500);
        display: grid; gap: 1.5rem; font-size: .875rem; line-height: 1.6; color: var(--gray-950); }
    .dark .iga { --iga-akzent: var(--primary-400); --iga-rand: rgb(255 255 255 / .1); --iga-flaeche: rgb(255 255 255 / .05); --iga-leise: var(--gray-400); color: var(--gray-100); }
    .iga p, .iga ul, .iga ol, .iga h3 { margin: 0; }
    .iga h3 { font-size: .95rem; font-weight: 700; }
    .iga a { color: var(--iga-akzent); font-weight: 600; text-decoration: underline; text-underline-offset: 2px; }
    .iga em { font-style: normal; font-weight: 600; }
    .iga code { font-size: .8rem; padding: .05rem .3rem; border-radius: .25rem; background: var(--iga-flaeche); border: 1px solid var(--iga-rand); overflow-wrap: anywhere; }
    .iga ul { padding-inline-start: 1.1rem; list-style: disc; display: grid; gap: .35rem; }
    .iga ul li::marker { color: var(--iga-leise); }
    .iga-lead { font-size: .925rem; }
    .iga-kasten { border: 1px solid var(--iga-rand); background: var(--iga-flaeche); border-radius: .75rem; padding: 1rem 1.1rem; display: grid; gap: .6rem; }
    .iga-schritte { list-style: none; padding: 0; display: grid; gap: 0; counter-reset: iga; }
    .iga-schritt { counter-increment: iga; display: grid; grid-template-columns: 2rem 1fr; column-gap: .9rem; position: relative; padding-bottom: 1.4rem; }
    .iga-schritt:last-child { padding-bottom: 0; }
    .iga-schritt::before { content: counter(iga); grid-row: 1 / span 2; width: 2rem; height: 2rem; border-radius: 9999px; display: grid; place-items: center;
        background: var(--iga-akzent); color: #fff; font-weight: 700; font-size: .85rem; }
    .dark .iga-schritt::before { color: var(--gray-950); }
    .iga-schritt:not(:last-child)::after { content: ""; position: absolute; left: calc(1rem - 1px); top: 2.25rem; bottom: .25rem; width: 2px; background: var(--iga-rand); }
    .iga-schritt > h3 { align-self: center; min-height: 2rem; display: flex; align-items: center; }
    .iga-schritt > div { grid-column: 2; display: grid; gap: .5rem; min-width: 0; }
    .iga-hinweis { border-inline-start: 3px solid var(--iga-akzent); background: var(--iga-flaeche); border-radius: 0 .5rem .5rem 0; padding: .6rem .8rem; }
    .iga-hilfe { display: grid; gap: .5rem; }
    .iga details { border: 1px solid var(--iga-rand); border-radius: .6rem; background: var(--iga-flaeche); }
    .iga summary { cursor: pointer; padding: .6rem .8rem; font-weight: 600; }
    .iga details > div { padding: 0 .8rem .75rem; display: grid; gap: .4rem; }
    .iga-fuss { color: var(--iga-leise); font-size: .8rem; }
</style>

<div class="iga">
    <p class="iga-lead">Das Token erzeugst du einmal bei Meta (Facebook). Du brauchst dafür etwa <strong>15 Minuten</strong> und die Zugangsdaten des Instagram-Kontos. Kluft verlängert das Token danach selbst.</p>

    <section class="iga-kasten">
        <h3>Vorher prüfen</h3>
        <ul>
            <li><strong>Profikonto:</strong> Das Instagram-Konto muss ein Business- oder Creator-Konto sein. In der Instagram-App: Profil → Menü (☰) → <em>Kontotyp und Tools</em> → <em>Zu professionellem Konto wechseln</em>. Kostenlos, an den Beiträgen ändert sich nichts.</li>
            <li><strong>Facebook-Konto:</strong> Du brauchst ein persönliches Facebook-Konto für den Meta-Entwicklerbereich. Es muss nicht mit Instagram verknüpft sein.</li>
        </ul>
    </section>

    <ol class="iga-schritte">
        <li class="iga-schritt">
            <h3>Entwicklerkonto</h3>
            <div>
                <p>Öffne <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener noreferrer">developers.facebook.com/apps</a> und melde dich mit Facebook an.</p>
                <p>Beim ersten Mal fragt Meta, ob du dich als Entwickler*in registrieren willst: bestätigen (Telefonnummer oder E-Mail).</p>
            </div>
        </li>
        <li class="iga-schritt">
            <h3>App anlegen</h3>
            <div>
                <p>Auf <em>App erstellen</em> (Create App) klicken.</p>
                <ul>
                    <li>Name z. B. „Kluft Website“ (ohne „Instagram“ oder „Facebook“ im Namen), Kontakt-E-Mail eintragen.</li>
                    <li>Anwendungsfall: <em>Nachrichten und Inhalte auf Instagram verwalten</em> (Manage messaging &amp; content on Instagram). Gibt es das nicht, nimm <em>Sonstiges</em> und als App-Typ <em>Business</em>.</li>
                    <li>Unternehmensportfolio: „Ich möchte noch kein Unternehmensportfolio verbinden“ reicht.</li>
                </ul>
            </div>
        </li>
        <li class="iga-schritt">
            <h3>Instagram einrichten</h3>
            <div>
                <p>Im App-Dashboard links <em>Instagram</em> (bzw. unter <em>Produkt hinzufügen</em> bei Instagram auf <em>Einrichten</em>), dann <em>API-Einrichtung mit Instagram-Login</em> (API setup with Instagram business login).</p>
            </div>
        </li>
        <li class="iga-schritt">
            <h3>Instagram-Tester eintragen</h3>
            <div>
                <p class="iga-hinweis">Nötig vor Schritt 5, sonst lehnt Meta das Konto ab.</p>
                <ul>
                    <li>Im App-Dashboard links <em>App-Rollen → Rollen</em> (oder der Link „Rollen“ in Schritt 2 der API-Einrichtung).</li>
                    <li>Bei <em>Instagram-Tester</em> auf <em>Hinzufügen</em>, den Instagram-Benutzernamen des Kontos eintragen, senden.</li>
                    <li>Einladung annehmen: im <strong>Browser</strong> (nicht in der App) bei instagram.com mit dem Konto anmelden → <a href="https://www.instagram.com/accounts/manage_access/" target="_blank" rel="noopener noreferrer">Einstellungen → Apps und Websites</a> → Reiter <em>Tester-Einladungen</em> → <em>Annehmen</em>. Am Handy notfalls „Desktop-Website anfordern“.</li>
                </ul>
            </div>
        </li>
        <li class="iga-schritt">
            <h3>Konto hinzufügen und Token erzeugen</h3>
            <div>
                <p>Zurück in der API-Einrichtung, Abschnitt <em>1. Zugriffsschlüssel generieren</em> (Generate access tokens):</p>
                <ul>
                    <li>Auf <em>Konto hinzufügen</em> klicken, mit dem Instagram-Konto anmelden und den Zugriff erlauben.</li>
                    <li>Neben dem Konto auf <em>Token generieren</em> (Generate token) klicken und noch einmal bestätigen.</li>
                    <li>Das Token kopieren: eine lange Zeichenkette, die mit <code>IG</code> beginnt.</li>
                </ul>
                <p class="iga-hinweis"><strong>Zum Teilen</strong> (Website-Beiträge auf diesem Konto veröffentlichen) muss beim Erlauben auch „Inhalte veröffentlichen“ (<code>instagram_business_content_publish</code>) angehakt sein. Nur zum Einblenden reicht das Standard-Recht.</p>
                <p>Schritt 3 „Webhooks konfigurieren“ und alles danach (App-Prüfung, Business-Verifizierung) brauchst du nicht.</p>
            </div>
        </li>
        <li class="iga-schritt">
            <h3>In Kluft eintragen</h3>
            <div>
                <p>Hier auf dieser Seite <em>Neu</em> klicken:</p>
                <ul>
                    <li>Anzeigename eintragen und das Token einfügen.</li>
                    <li>Bei „Einblenden auf“ die Startseite und/oder Fachbereiche ankreuzen.</li>
                    <li>Soll Kluft Website-Beiträge auf diesem Konto teilen: „Zum Teilen verwenden“ einschalten.</li>
                    <li>Speichern. Kluft prüft das Token und holt sofort die ersten Beiträge.</li>
                </ul>
            </div>
        </li>
    </ol>

    <section class="iga-hilfe">
        <h3>Wenn es hakt</h3>
        <details>
            <summary>„Konto hinzufügen“ lehnt ab</summary>
            <div>
                <p>Meist fehlt die angenommene Tester-Einladung (Schritt 4).</p>
                <ul>
                    <li>Links <em>App-Rollen → Rollen</em>, bei <em>Instagram-Tester</em> den Instagram-Namen hinzufügen.</li>
                    <li>Dann in Instagram (Browser, <a href="https://www.instagram.com/accounts/manage_access/" target="_blank" rel="noopener noreferrer">instagram.com → Einstellungen → Apps und Websites</a>) unter <em>Tester-Einladungen</em> annehmen.</li>
                    <li>Danach Schritt 5 wiederholen.</li>
                </ul>
            </div>
        </details>
        <details>
            <summary>Kein Profikonto</summary>
            <div><p>Persönliche Instagram-Konten bekommen kein Token. Vorher umstellen (siehe „Vorher prüfen“).</p></div>
        </details>
        <details>
            <summary>„Token nicht gültig“ beim Speichern</summary>
            <div><p>Das Token wurde nicht vollständig kopiert, oder es war ein Token einer anderen Meta-Schnittstelle (Facebook-Login). Es muss aus <em>API-Einrichtung mit Instagram-Login</em> stammen.</p></div>
        </details>
        <details>
            <summary>„Dieses Token darf nicht veröffentlichen“</summary>
            <div><p>Beim Erzeugen fehlte das Recht „Inhalte veröffentlichen“. Token in Schritt 5 neu erzeugen und dabei das Recht anhaken, oder den Haken „Zum Teilen verwenden“ weglassen.</p></div>
        </details>
        <details>
            <summary>Muss die App von Meta geprüft oder veröffentlicht werden?</summary>
            <div><p>Nein. Die App darf im Entwicklungsmodus bleiben, solange das Konto eine Rolle in der App hat.</p></div>
        </details>
    </section>

    <p class="iga-fuss">Das Token ist wie ein Passwort: nur hier ins Formular einfügen, nicht per Mail oder Chat weitergeben. Meta benennt Menüpunkte gelegentlich um. Die Anleitung von Meta: <a href="https://developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login/" target="_blank" rel="noopener noreferrer">Instagram API mit Instagram-Login</a>.</p>
</div>
