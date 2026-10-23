{{-- Anleitung: Instagram-Zugangstoken für die Einblendung erzeugen (docs/instagram.md). --}}
@php($link = 'font-semibold text-primary-600 underline dark:text-primary-400')
<div class="space-y-5 text-sm leading-relaxed">
    <p>Das Token erzeugst du einmal bei Meta (Facebook). Du brauchst dafür etwa 15 Minuten und die Zugangsdaten des Instagram-Kontos. Kluft verlängert das Token danach selbst.</p>

    <div>
        <h3 class="text-base font-bold">Vorher prüfen</h3>
        <ul class="mt-2 list-disc space-y-1 ps-5">
            <li><strong>Profikonto:</strong> Das Instagram-Konto muss ein Business- oder Creator-Konto sein. In der Instagram-App: Profil → Menü (☰) → <em>Kontotyp und Tools</em> → <em>Zu professionellem Konto wechseln</em>. Kostenlos, an den Beiträgen ändert sich nichts.</li>
            <li><strong>Facebook-Konto:</strong> Du brauchst ein persönliches Facebook-Konto für den Meta-Entwicklerbereich. Es muss nicht mit Instagram verknüpft sein.</li>
        </ul>
    </div>

    <ol class="list-decimal space-y-4 ps-5">
        <li>
            <strong>Entwicklerkonto:</strong> Öffne <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener noreferrer" class="{{ $link }}">developers.facebook.com/apps</a> und melde dich mit Facebook an. Beim ersten Mal fragt Meta, ob du dich als Entwickler*in registrieren willst: bestätigen (Telefonnummer oder E-Mail).
        </li>
        <li>
            <strong>App anlegen:</strong> <em>App erstellen</em> (Create App).
            <ul class="mt-1 list-disc space-y-1 ps-5">
                <li>Name z. B. „Kluft Website“ (ohne „Instagram“ oder „Facebook“ im Namen), Kontakt-E-Mail eintragen.</li>
                <li>Anwendungsfall: <em>Nachrichten und Inhalte auf Instagram verwalten</em> (Manage messaging &amp; content on Instagram). Gibt es das nicht, nimm <em>Sonstiges</em> und als App-Typ <em>Business</em>.</li>
                <li>Unternehmensportfolio: „Ich möchte noch kein Unternehmensportfolio verbinden“ reicht.</li>
            </ul>
        </li>
        <li>
            <strong>Instagram einrichten:</strong> Im App-Dashboard links <em>Instagram</em> (bzw. unter <em>Produkt hinzufügen</em> bei Instagram auf <em>Einrichten</em>), dann <em>API-Einrichtung mit Instagram-Login</em> (API setup with Instagram business login).
        </li>
        <li>
            <strong>Instagram-Tester eintragen (nötig vor Schritt 5):</strong> Im App-Dashboard links <em>App-Rollen → Rollen</em> (oder der Link „Rollen“ in Schritt 2 der API-Einrichtung) → bei <em>Instagram-Tester</em> auf <em>Hinzufügen</em> → den Instagram-Benutzernamen des Kontos eintragen → senden.
            <ul class="mt-1 list-disc space-y-1 ps-5">
                <li>Dann die Einladung annehmen: im Browser (nicht in der App) bei instagram.com mit dem Konto anmelden → <a href="https://www.instagram.com/accounts/manage_access/" target="_blank" rel="noopener noreferrer" class="{{ $link }}">Einstellungen → Apps und Websites</a> → Reiter <em>Tester-Einladungen</em> → <em>Annehmen</em>. Am Handy notfalls „Desktop-Website anfordern“.</li>
            </ul>
        </li>
        <li>
            <strong>Konto hinzufügen und Token erzeugen:</strong> Auf dieser Seite im Abschnitt <em>1. Zugriffsschlüssel generieren</em> (Generate access tokens) auf <em>Konto hinzufügen</em> klicken.
            <ul class="mt-1 list-disc space-y-1 ps-5">
                <li>Im Fenster mit dem Instagram-Konto anmelden und den Zugriff erlauben.</li>
                <li>Danach steht das Konto in der Liste; daneben <em>Token generieren</em> (Generate token), noch einmal bestätigen.</li>
                <li>Das Token kopieren: eine lange Zeichenkette, die mit <code>IG</code> beginnt.</li>
                <li>Schritt 3 „Webhooks konfigurieren“ und alles danach (App-Prüfung, Business-Verifizierung) brauchst du nicht.</li>
            </ul>
        </li>
        <li>
            <strong>In Kluft eintragen:</strong> Hier auf dieser Seite <em>Neu</em>:
            <ul class="mt-1 list-disc space-y-1 ps-5">
                <li>Anzeigename eintragen und das Token einfügen;</li>
                <li>bei „Anzeigen auf“ die Startseite und/oder Fachbereiche ankreuzen;</li>
                <li>speichern.</li>
            </ul>
            Kluft prüft das Token und holt sofort die ersten Beiträge.
        </li>
    </ol>

    <div class="rounded-lg bg-gray-50 p-4 dark:bg-white/5">
        <h3 class="font-bold">Wenn es hakt</h3>
        <ul class="mt-2 list-disc space-y-2 ps-5">
            <li><strong>„Konto hinzufügen“ lehnt ab:</strong> Meist fehlt die angenommene Tester-Einladung (Schritt 4).
                <ul class="mt-1 list-disc space-y-1 ps-5">
                    <li>Links <em>App-Rollen → Rollen</em>, bei <em>Instagram-Tester</em> den Instagram-Namen hinzufügen.</li>
                    <li>Dann in Instagram (Browser, <a href="https://www.instagram.com/accounts/manage_access/" target="_blank" rel="noopener noreferrer" class="{{ $link }}">instagram.com → Einstellungen → Apps und Websites</a>) unter <em>Tester-Einladungen</em> annehmen.</li>
                    <li>Danach Schritt 4 wiederholen.</li>
                </ul>
            </li>
            <li><strong>Kein Profikonto:</strong> Persönliche Instagram-Konten bekommen kein Token. Vorher umstellen (siehe oben).</li>
            <li><strong>„Token nicht gültig“ beim Speichern:</strong> Das Token wurde nicht vollständig kopiert, oder es war ein Token einer anderen Meta-Schnittstelle (Facebook-Login). Es muss aus <em>API-Einrichtung mit Instagram-Login</em> stammen.</li>
            <li><strong>Veröffentlichen, Prüfung durch Meta:</strong> Nicht nötig. Die App darf im Entwicklungsmodus bleiben, solange das Konto eine Rolle in der App hat.</li>
        </ul>
    </div>

    <p class="text-gray-500 dark:text-gray-400">Das Token ist wie ein Passwort: nur hier ins Formular einfügen, nicht per Mail oder Chat weitergeben. Meta benennt Menüpunkte gelegentlich um. Die Anleitung von Meta: <a href="https://developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login/" target="_blank" rel="noopener noreferrer" class="{{ $link }}">Instagram API mit Instagram-Login</a>.</p>
</div>
