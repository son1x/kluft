<?php

/**
 * Kluft-Backup (.dpsgbak) ohne Website entschlüsseln – z. B. wenn der Server
 * weg ist. Braucht nur PHP 8 mit libsodium (Standard), nicht Laravel.
 *
 *   php scripts/backup-entschluesseln.php backup-2026-11-11-020000.dpsgbak [ziel.zip]
 *
 * Das Passwort (BACKUP_PASSWORD der Website) wird abgefragt; alternativ als
 * Umgebungsvariable BACKUP_PASSWORD. Ergebnis ist ein normales ZIP mit der
 * Datenbank (database/…) und den Dateien (storage/app/private, public/uploads).
 * Format: siehe App\Services\Backup\BackupService::encryptFile().
 */
const MAGIC = 'DPSGBAK1';
const CHUNK = 65536;

if (PHP_SAPI !== 'cli') {
    exit("Nur auf der Kommandozeile.\n");
}
if (! function_exists('sodium_crypto_secretstream_xchacha20poly1305_init_pull')) {
    fwrite(STDERR, "PHP ohne libsodium – bitte PHP 8 mit sodium verwenden.\n");
    exit(1);
}
$quelle = $argv[1] ?? null;
if ($quelle === null || ! is_file($quelle)) {
    fwrite(STDERR, "Aufruf: php backup-entschluesseln.php <datei.dpsgbak> [ziel.zip]\n");
    exit(1);
}
$ziel = $argv[2] ?? preg_replace('/\.dpsgbak$/', '', $quelle).'.zip';
if (file_exists($ziel)) {
    fwrite(STDERR, "Ziel existiert schon: {$ziel}\n");
    exit(1);
}

$passwort = getenv('BACKUP_PASSWORD') ?: null;
if ($passwort === null) {
    fwrite(STDOUT, 'Backup-Passwort: ');
    if (DIRECTORY_SEPARATOR === '/') {
        system('stty -echo');
    }
    $passwort = rtrim((string) fgets(STDIN), "\r\n");
    if (DIRECTORY_SEPARATOR === '/') {
        system('stty echo');
    }
    fwrite(STDOUT, "\n");
}

$in = fopen($quelle, 'rb');
if (fread($in, strlen(MAGIC)) !== MAGIC) {
    fwrite(STDERR, "Keine Kluft-Backup-Datei.\n");
    exit(1);
}
$salt = fread($in, SODIUM_CRYPTO_PWHASH_SALTBYTES);
['ops' => $ops, 'mem' => $mem] = unpack('Nops/Nmem', fread($in, 8));
$header = fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
$schluessel = sodium_crypto_pwhash(SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES, $passwort, $salt, $ops, $mem, SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
$state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $schluessel);
sodium_memzero($schluessel);

$out = fopen($ziel, 'wb');
$fertig = false;
while (! feof($in)) {
    $block = fread($in, CHUNK + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES);
    if ($block === '' || $block === false) {
        break;
    }
    $teil = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $block);
    if ($teil === false) {
        fclose($out);
        unlink($ziel);
        fwrite(STDERR, "Entschlüsselung fehlgeschlagen: falsches Passwort oder beschädigte Datei.\n");
        exit(1);
    }
    fwrite($out, $teil[0]);
    $fertig = $teil[1] === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
}
fclose($in);
fclose($out);
if (! $fertig) {
    unlink($ziel);
    fwrite(STDERR, "Backup-Datei unvollständig.\n");
    exit(1);
}
fwrite(STDOUT, "Entschlüsselt: {$ziel}\n");
