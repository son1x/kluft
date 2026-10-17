<?php

/*
| Kluft-Einrichtungsassistent für eine neue Stammes-Website
| (docs/einrichtung.md). Läuft nur, solange es keine .env gibt; danach
| zeigt er lediglich „bereits eingerichtet“. Logik in
| App\Support\Einrichtung\Installer.
*/

use App\Support\Einrichtung\Installer;

require __DIR__.'/../vendor/autoload.php';

$basis = dirname(__DIR__);
$installer = new Installer($basis);
$h = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

session_name('kluft_einrichtung');
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Strict', 'cookie_secure' => ! empty($_SERVER['HTTPS'])]);
$_SESSION['token'] ??= bin2hex(random_bytes(16));

$schritt = 'formular';
$fehler = [];
$meldung = null;
$backupPasswort = null;
$e = [];

if ($installer->eingerichtet()) {
    $schritt = 'fertig-vorher';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        http_response_code(403);
    }
} else {
    $voraussetzungen = $installer->voraussetzungen();
    $installer->code();
    // Vorbereitete Angaben (falls hochgeladen) füllen das Formular vor.
    $vorbelegt = $_SERVER['REQUEST_METHOD'] !== 'POST' && ($e = $installer->vorbelegung()) !== [];
    if (in_array(false, array_column($voraussetzungen, 'ok'), true)) {
        $schritt = 'voraussetzungen';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $e = $_POST;
        if (! hash_equals($_SESSION['token'], (string) ($e['_token'] ?? ''))) {
            $fehler['code'] = 'Die Seite ist abgelaufen. Bitte noch einmal absenden.';
        } elseif (! $installer->codeStimmt((string) ($e['code'] ?? ''))) {
            $fehler['code'] = 'Der Einrichtungscode stimmt nicht. Du findest ihn per FTP in der Datei '.Installer::CODE_DATEI.'.';
        } else {
            $ergebnis = $installer->pruefen($e, $_SERVER['HTTP_HOST'] ?? null);
            $fehler = $ergebnis['fehler'];
            if ($fehler === []) {
                @set_time_limit(300);
                try {
                    $logo = ($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK ? $_FILES['logo'] : null;
                    $backupPasswort = $installer->einrichten($ergebnis['angaben'], $logo);
                    $schritt = 'fertig';
                    $zugang = $ergebnis['angaben']['_zugang'];
                    $domain = $ergebnis['angaben']['domain'];
                } catch (Throwable $ex) {
                    $meldung = $ex->getMessage();
                }
            }
        }
    }
}

$feld = fn (string $name, string $label, string $typ = 'text', string $hinweis = '', bool $pflicht = false, string $standard = '') => '<label class="feld"><span>'.$h($label).($pflicht ? ' *' : '').'</span>'
    .'<input type="'.$typ.'" name="'.$name.'" value="'.$h($e[$name] ?? $standard).'"'.($pflicht ? ' required' : '').' autocomplete="off">'
    .($hinweis !== '' ? '<small>'.$h($hinweis).'</small>' : '').'</label>';
$fehlerText = fn (string $k) => isset($fehler[$k]) ? '<p class="fehler">'.$h($fehler[$k]).'</p>' : '';
$stufen = Installer::STUFEN;
$vorbelegt ??= false;
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Kluft einrichten</title>
<style>
:root{--blau:#003056;--rot:#810a1a;--beige:#f3ede2;--text:#1d2733;--grau:#5a6573;--linie:#d9d2c4}
*{box-sizing:border-box}
body{margin:0;background:var(--beige);color:var(--text);font:16px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
header{background:var(--blau);color:#fff;padding:1.5rem 1rem}
header div,main{max-width:46rem;margin:0 auto}
header h1{margin:0;font-size:1.6rem}
header p{margin:.25rem 0 0;opacity:.85}
main{padding:1.5rem 1rem 3rem}
section{background:#fff;border:1px solid var(--linie);border-radius:8px;padding:1.25rem;margin-bottom:1.25rem}
h2{margin:0 0 .75rem;font-size:1.15rem;color:var(--blau)}
.feld{display:block;margin-bottom:.9rem}
.feld span{display:block;font-weight:600;margin-bottom:.2rem}
.feld input,select{width:100%;padding:.55rem .65rem;border:1px solid #b9b2a4;border-radius:5px;font:inherit;background:#fff}
.feld small{display:block;color:var(--grau);font-size:.85rem;margin-top:.2rem}
.zwei{display:grid;gap:0 1rem;grid-template-columns:1fr 1fr}
@media (max-width:600px){.zwei{grid-template-columns:1fr}}
.stufen{display:flex;flex-wrap:wrap;gap:.5rem 1.25rem}
.fehler,.meldung{color:var(--rot);font-weight:600;margin:.25rem 0 .9rem}
.hinweis{background:#eef4fb;border-left:4px solid var(--blau);padding:.75rem 1rem;margin:0 0 1.25rem}
.klein{color:var(--grau);font-size:.9rem;margin:0 0 .5rem}
.meldung{background:#fbeeee;border-left:4px solid var(--rot);padding:.75rem 1rem}
button{background:var(--blau);color:#fff;border:0;border-radius:5px;padding:.8rem 1.4rem;font:inherit;font-weight:700;cursor:pointer}
button:focus-visible,input:focus-visible{outline:3px solid #e8a92a;outline-offset:2px}
ul.check{list-style:none;padding:0;margin:0}
ul.check li{padding:.3rem 0;border-bottom:1px solid #eee}
.ok{color:#1f6b3a;font-weight:700}.nein{color:var(--rot);font-weight:700}
code{background:#f1f1f1;padding:.1rem .35rem;border-radius:3px;word-break:break-all}
.geheim{font:1.1rem ui-monospace,Menlo,Consolas,monospace;background:#fff8e1;border:1px dashed #c9a227;padding:.6rem;word-break:break-all}
fieldset{border:0;padding:0;margin:0 0 .9rem}
legend{font-weight:600;margin-bottom:.3rem}
</style>
</head>
<body>
<header><div><h1>Kluft einrichten</h1><p>Die Website für deinen DPSG-Stamm in wenigen Minuten</p></div></header>
<main>
<?php if ($schritt === 'fertig-vorher') { ?>
    <section><h2>Bereits eingerichtet</h2><p>Diese Website ist schon eingerichtet. Zur <a href="/">Startseite</a> oder zur <a href="/redaktion/anmelden">Anmeldung im Redaktionsbereich</a>.</p></section>

<?php } elseif ($schritt === 'voraussetzungen') { ?>
    <section>
        <h2>Bevor es losgeht</h2>
        <p>Auf diesem Webspace fehlt noch etwas. Bitte im Hosting-Panel ändern und die Seite neu laden.</p>
        <ul class="check">
        <?php foreach ($voraussetzungen as $v) { ?>
            <li><?= $v['ok'] ? '<span class="ok">✓</span>' : '<span class="nein">✗</span>' ?> <?= $h($v['label']) ?><?= $v['ok'] ? '' : ' – '.$h($v['hinweis']) ?></li>
        <?php } ?>
        </ul>
    </section>

<?php } elseif ($schritt === 'fertig') { ?>
    <section>
        <h2>Fertig – die Website ist eingerichtet</h2>
        <p>Der erste Zugang „<?= $h($zugang['name']) ?>“ ist angelegt. Melde dich unter <a href="/redaktion/anmelden">/redaktion/anmelden</a> mit <strong><?= $h($zugang['email']) ?></strong> an – du bekommst einen Code per E-Mail.</p>
    </section>
    <section>
        <h2>Backup-Passwort – jetzt sichern</h2>
        <p>Damit werden die täglichen Backups verschlüsselt. Es wird nur dieses eine Mal angezeigt. Bitte im Passwortmanager des Stamms speichern – ohne es lässt sich kein Backup öffnen.</p>
        <p class="geheim"><?= $h($backupPasswort) ?></p>
    </section>
    <section>
        <h2>Noch zwei Dinge im Hosting-Panel</h2>
        <ol>
            <li>Cronjob jede Minute: <code>php <?= $h($basis) ?>/artisan schedule:run</code><br><small>Ohne ihn gehen keine Mails raus und es gibt keine Backups und Updates.</small></li>
            <li>SSL-Zertifikat (Let’s Encrypt) für <?= $h($domain) ?> einschalten, falls noch nicht geschehen.</li>
        </ol>
        <p>Danach im Redaktionsbereich: Impressum und Datenschutz prüfen (markierte Stellen ergänzen), Gruppenstunden bei den Stufen prüfen, Titelbild der Startseite wählen.</p>
    </section>

<?php } else { ?>
    <?php if ($meldung) { ?><p class="meldung"><?= $h($meldung) ?></p><?php } ?>
    <?php if ($vorbelegt) { ?><p class="hinweis">Die Angaben für <?= $h(($e['stamm'] ?? '') !== '' ? 'Stamm '.$e['stamm'] : 'euren Stamm') ?> sind schon vorbereitet. Bitte prüfen und die Passwörter für Datenbank und E-Mail ergänzen.</p><?php } ?>
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="_token" value="<?= $h($_SESSION['token']) ?>">
        <section>
            <h2>1. Einrichtungscode</h2>
            <p>Damit niemand Fremdes deine Website einrichtet: Öffne per FTP oder im Dateimanager die Datei <code><?= $h(Installer::CODE_DATEI) ?></code> und trage den Code hier ein.</p>
            <?= $fehlerText('code') ?>
            <?= $feld('code', 'Einrichtungscode', 'text', '', true) ?>
        </section>
        <section>
            <h2>2. Euer Stamm</h2>
            <div class="zwei">
                <?= $fehlerText('stamm') ?>
                <?= $feld('stamm', 'Name des Stamms', 'text', 'ohne „Stamm“, z. B. Don Bosco', true) ?>
                <?= $feld('ort', 'Ort', 'text', 'z. B. Beispielstadt') ?>
                <?= $feld('nummer', 'DPSG-Nummer (optional)', 'text', 'sechs Ziffern, für den NaMi-Abgleich') ?>
                <?= $feld('bezirk', 'Bezirk (optional)') ?>
                <?= $feld('dioezese', 'Diözesanverband (optional)', 'text', 'z. B. Trier') ?>
                <?= $feld('eltern_url', 'Website von Bezirk oder DV (optional)', 'text', 'erscheint unten unter „DPSG im Netz“') ?>
            </div>
            <?= $fehlerText('nummer') ?><?= $fehlerText('eltern_url') ?>
            <fieldset><legend>Welche Stufen gibt es bei euch? *</legend>
                <div class="stufen">
                <?php foreach ($stufen as $k => $label) { ?>
                    <label><input type="checkbox" name="stufen[]" value="<?= $h($k) ?>" <?= in_array($k, (array) ($e['stufen'] ?? ['woelflinge', 'jupfi', 'pfadi', 'rover']), true) ? 'checked' : '' ?>> <?= $h($label) ?></label>
                <?php } ?>
                </div>
            </fieldset>
            <?= $fehlerText('stufen') ?>
            <fieldset><legend>Gruppenstunden (optional)</legend>
                <p class="klein">Wann trifft sich welche Gruppe? Erscheint auf der Seite der Stufe; später im Redaktionsbereich änderbar.</p>
                <div class="zwei">
                <?php foreach ($stufen as $k => $label) { ?>
                    <label class="feld"><span><?= $h($label) ?></span><input type="text" name="gruppenstunden[<?= $h($k) ?>]" value="<?= $h(((array) ($e['gruppenstunden'] ?? []))[$k] ?? '') ?>" placeholder="z. B. dienstags, 16:00–17:30 Uhr" maxlength="120" autocomplete="off"></label>
                <?php } ?>
                </div>
            </fieldset>
            <fieldset><legend>Anmeldungen zu Lagern und Aktionen</legend>
                <label><input type="checkbox" name="nami" value="1" <?= ! empty($e['nami']) ? 'checked' : '' ?>> Mitgliedschaft mit NaMi abgleichen</label>
                <p class="klein">Wer im Redaktionsbereich die Anmeldungen prüft, meldet sich dafür mit dem eigenen NaMi-Konto an. Zugangsdaten werden nicht gespeichert; je Veranstaltung einschaltbar. Am besten mit DPSG-Nummer.</p>
            </fieldset>
            <label class="feld"><span>Logo (optional)</span><input type="file" name="logo" accept="image/png,image/jpeg,image/webp"><small>PNG, JPG oder WebP, höchstens 2 MB. Ohne Logo erzeugt Kluft ein schlichtes Schriftlogo.</small></label>
        </section>
        <section>
            <h2>3. Kontakt und Impressum</h2>
            <?= $fehlerText('domain') ?><?= $fehlerText('email') ?>
            <div class="zwei">
                <?= $feld('domain', 'Adresse der Website', 'text', 'ohne https://', true, (string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''))) ?>
                <?= $feld('email', 'Kontakt-E-Mail', 'email', 'z. B. vorstand@…', true) ?>
                <?= $feld('telefon', 'Telefon (optional)') ?>
                <?= $feld('vorstand', 'Stammesvorstand', 'text', 'Namen für das Impressum') ?>
                <?= $feld('strasse', 'Straße und Hausnummer') ?>
                <?= $feld('plz_ort', 'PLZ und Ort') ?>
            </div>
        </section>
        <section>
            <h2>4. Datenbank</h2>
            <?= $fehlerText('db') ?>
            <label class="feld"><span>Art</span>
                <select name="db_art">
                    <option value="sqlite" <?= ($e['db_art'] ?? 'sqlite') === 'sqlite' ? 'selected' : '' ?>>Einfach: Datei auf dem Webspace (SQLite)</option>
                    <option value="mariadb" <?= ($e['db_art'] ?? '') === 'mariadb' ? 'selected' : '' ?>>MariaDB/MySQL aus dem Hosting-Panel</option>
                </select>
                <small>SQLite reicht für einen Stamm. Für MariaDB eine neue, leere Datenbank anlegen.</small>
            </label>
            <div class="zwei">
                <?= $feld('db_host', 'MariaDB: Server', 'text', '', false, 'localhost') ?>
                <?= $feld('db_port', 'Port', 'text', '', false, '3306') ?>
                <?= $feld('db_name', 'Datenbank') ?>
                <?= $feld('db_user', 'Benutzer') ?>
                <?= $feld('db_pass', 'Passwort', 'password') ?>
            </div>
        </section>
        <section>
            <h2>5. E-Mail-Versand</h2>
            <p>Über dieses Postfach verschickt die Website Anmeldecodes, Bestätigungen und Kontaktanfragen. Die Daten stehen im Hosting-Panel beim E-Mail-Postfach.</p>
            <?= $fehlerText('mail') ?>
            <div class="zwei">
                <?= $feld('mail_host', 'SMTP-Server', 'text', 'z. B. mail.stamm-beispiel.de', true) ?>
                <?= $feld('mail_port', 'Port', 'text', '587 (STARTTLS) oder 465 (SSL)', false, '587') ?>
                <?= $feld('mail_user', 'Benutzername') ?>
                <?= $feld('mail_pass', 'Passwort', 'password') ?>
                <?= $feld('mail_from', 'Absenderadresse', 'email', 'z. B. website@stamm-beispiel.de', true) ?>
            </div>
        </section>
        <section>
            <h2>6. Erster Zugang zur Redaktion</h2>
            <p>Dieser Zugang darf alles, auch weitere Zugänge anlegen. Ein Rollen-Zugang hängt an einem Funktionspostfach (z. B. vorstand@…) und kann beim Amtswechsel einfach weitergegeben werden.</p>
            <?= $fehlerText('admin') ?>
            <label class="feld"><span>Art</span>
                <select name="admin_kind">
                    <option value="rolle" <?= ($e['admin_kind'] ?? 'rolle') === 'rolle' ? 'selected' : '' ?>>Rollen-Zugang (Funktionspostfach)</option>
                    <option value="person" <?= ($e['admin_kind'] ?? '') === 'person' ? 'selected' : '' ?>>Person (namentlich)</option>
                </select>
            </label>
            <div class="zwei">
                <?= $feld('admin_name', 'Name bzw. Rolle', 'text', 'z. B. Stammesvorstand', true) ?>
                <?= $feld('admin_email', 'E-Mail-Adresse', 'email', 'hierhin geht der Anmeldecode', true) ?>
            </div>
        </section>
        <button type="submit">Website einrichten</button>
    </form>
<?php } ?>
</main>
</body>
</html>
