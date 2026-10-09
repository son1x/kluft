<?php

namespace App\Services\Mitglieder;

use App\Models\User;

/**
 * Prüft für die Benutzerverwaltung, ob eine Person aktives DPSG-Mitglied
 * ist (Meilenstein Stämme, „namentlicher Zugang mit NaMi-Abgleich“).
 * Gesucht wird über Vor- und Nachname; ist eine Mitgliedsnummer
 * angegeben, muss sie passen. Bei persönlicher NaMi-Anmeldung braucht die
 * prüfende Person ihren gespeicherten NaMi-Zugang.
 */
class MitgliedPruefung
{
    public function __construct(private Mitgliederverzeichnis $verzeichnis) {}

    /**
     * @return array{aktiv: bool, text: string, nummer: ?string}
     *
     * @throws MitgliederverzeichnisException
     */
    public function pruefe(User $pruefer, string $name, ?string $nummer): array
    {
        $teile = preg_split('/\s+/', trim($name)) ?: [];
        if (count($teile) < 2) {
            throw new MitgliederverzeichnisException('Bitte Vor- und Nachname eintragen.');
        }
        $nummer = filled($nummer) ? (string) preg_replace('/\D/', '', (string) $nummer) : null;

        $treffer = $this->verzeichnis($pruefer)->suche($teile[0], (string) end($teile));
        if ($nummer !== null) {
            $treffer = array_values(array_filter($treffer, fn (Mitglied $m) => ltrim($m->nummer, '0') === ltrim($nummer, '0')));
        }

        if ($treffer === []) {
            return ['aktiv' => false, 'text' => 'Nicht in '.$this->verzeichnis->name().' gefunden'.($nummer !== null ? ' (mit dieser Mitgliedsnummer)' : ''), 'nummer' => null];
        }
        if (count($treffer) > 1) {
            return ['aktiv' => false, 'text' => 'Mehrere Treffer – bitte die Mitgliedsnummer eintragen und noch einmal prüfen', 'nummer' => null];
        }

        $m = $treffer[0];
        $text = ($m->aktiv ? 'Aktives Mitglied' : 'Kein aktives Mitglied')
            .($m->gruppierung ? ', '.$m->gruppierung : '')
            .' (Nr. '.$m->nummer.')';

        return ['aktiv' => $m->aktiv, 'text' => mb_substr($text, 0, 255), 'nummer' => $m->nummer];
    }

    /**
     * @throws MitgliederverzeichnisException
     */
    private function verzeichnis(User $pruefer): Mitgliederverzeichnis
    {
        if (! NamiVerzeichnis::persoenlich()) {
            return $this->verzeichnis;
        }
        if (! $pruefer->hasNamiZugang()) {
            throw new MitgliederverzeichnisException('Für die Prüfung braucht es deinen NaMi-Zugang unter „Mein Konto → Mein NaMi-Zugang“.');
        }

        return $this->verzeichnis = (new NamiVerzeichnis)->mitZugang($pruefer);
    }
}
