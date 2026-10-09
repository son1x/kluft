<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Einmalig: Schlüsselpaar zum Signieren der Releases erzeugen. Der geheime
 * Schlüssel gehört NUR in das GitHub-Secret KLUFT_RELEASE_SIGNING_KEY, der
 * öffentliche in KLUFT_UPDATE_PUBLIC_KEY jeder Installation.
 */
class KluftUpdateSchluesselCommand extends Command
{
    protected $signature = 'kluft:update-schluessel';

    protected $description = 'Schlüsselpaar zum Signieren von Kluft-Releases erzeugen (einmalig, lokal ausführen)';

    public function handle(): int
    {
        $paar = sodium_crypto_sign_keypair();

        $this->warn('Den GEHEIMEN Schlüssel nirgends speichern außer im GitHub-Secret KLUFT_RELEASE_SIGNING_KEY – nicht ins Repository, nicht in den Chat.');
        $this->line('');
        $this->line('Geheimer Schlüssel (GitHub-Secret KLUFT_RELEASE_SIGNING_KEY):');
        $this->line(base64_encode(sodium_crypto_sign_secretkey($paar)));
        $this->line('');
        $this->line('Öffentlicher Schlüssel (KLUFT_UPDATE_PUBLIC_KEY in jeder Installation):');
        $this->line(base64_encode(sodium_crypto_sign_publickey($paar)));

        return self::SUCCESS;
    }
}
