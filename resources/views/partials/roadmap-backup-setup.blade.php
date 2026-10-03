{{-- Anleitung „Backups einschalten“ – verschwindet von selbst, sobald ein
     Backup gelaufen ist und die Wiederherstellungsprobe geklappt hat. --}}
@php
    $check = fn (bool $ok) => $ok ? '✅' : '⬜';
@endphp
<section aria-labelledby="backup-heading" class="mt-8 border-l-4 border-dpsg-rot bg-white p-6 shadow">
    <h2 id="backup-heading" class="font-serif text-2xl font-bold">Wichtig: Backups einschalten</h2>
    <p class="mt-2">Die Backups sind fertig gebaut und der Server kann sie (Datenbank-Export, Verschlüsselung, ZIP vorhanden). Es fehlt nur noch das Passwort. Bis dahin gibt es <strong>keine Sicherung</strong>.</p>

    <ul class="mt-4 space-y-1 text-sm">
        <li>{{ $check($backup['password']) }} Backup-Passwort gesetzt</li>
        <li>{{ $check($backup['webdav']) }} Kopie extern in Nextcloud (empfohlen)</li>
        <li>{{ $check($backup['lastRun'] !== null) }} Erstes Backup gelaufen @if ($backup['lastRun']) ({{ \Illuminate\Support\Carbon::parse($backup['lastRun'])->timezone(config('app.timezone'))->format('d.m.Y H:i') }} Uhr) @endif</li>
    </ul>

    <h3 class="mt-6 font-bold">1. Pflicht: BACKUP_PASSWORD</h3>
    <ul class="mt-1 list-disc space-y-1 pl-5 text-sm">
        <li>In GitHub: Repository → Settings → Secrets and variables → Actions → „New repository secret“, Name <code>BACKUP_PASSWORD</code>.</li>
        <li>Mindestens 16 Zeichen, am besten vom Passwortmanager erzeugt.</li>
        <li><strong>Zusätzlich offline im Passwortmanager ablegen.</strong> Ohne dieses Passwort ist kein Backup lesbar – auch nicht für uns. Nicht in Chats oder Mails kopieren.</li>
    </ul>

    <h3 class="mt-5 font-bold">2. Empfohlen: Kopie in Nextcloud</h3>
    <p class="mt-1 text-sm">Sonst liegen die Backups nur auf dem Webspace selbst – fällt der aus, sind sie mit weg.</p>
    <ul class="mt-1 list-disc space-y-1 pl-5 text-sm">
        <li>In der Nextcloud einen Ordner anlegen, z. B. <code>Website-Backups</code>.</li>
        <li>Nextcloud → Persönliche Einstellungen → Sicherheit → <strong>App-Passwort</strong> erzeugen (nicht das normale Passwort).</li>
        <li>Drei weitere Secrets anlegen:
            <ul class="mt-1 list-[circle] space-y-1 pl-5">
                <li><code>BACKUP_WEBDAV_URL</code> = <code>https://&lt;eure-cloud&gt;/remote.php/dav/files/&lt;BENUTZERNAME&gt;/Website-Backups</code></li>
                <li><code>BACKUP_WEBDAV_USER</code> = Nextcloud-Benutzername</li>
                <li><code>BACKUP_WEBDAV_PASSWORD</code> = das App-Passwort</li>
            </ul>
        </li>
    </ul>

    <h3 class="mt-5 font-bold">3. Aktivieren und testen</h3>
    <ul class="mt-1 list-disc space-y-1 pl-5 text-sm">
        <li>Secrets wirken erst mit dem nächsten Deploy: Actions → „Deploy auf Netcup-Webspace“ → „Run workflow“ (oder nächster Push).</li>
        <li>Danach Actions → „Server-Prüfung“ → <code>backup:run</code>: erstellt sofort ein Backup, prüft die Wiederherstellung und zeigt den Stand.</li>
        <li>Ab dann automatisch jede Nacht um 2 Uhr, sonntags mit Wiederherstellungsprobe; aufbewahrt werden 14 Tage. Dieser Kasten verschwindet von selbst.</li>
    </ul>

    <p class="mt-5 text-sm text-text-secondary"><strong>Was drin ist:</strong> Datenbank (alle Inhalte, Anmeldungen, Benutzer), hochgeladene Medien, Georgsbote-PDFs, Rechnungen und Bescheinigungen zu Anmeldungen – alles mit eurem Passwort verschlüsselt.</p>
</section>
