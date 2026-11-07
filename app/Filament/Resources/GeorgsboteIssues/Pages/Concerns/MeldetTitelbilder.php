<?php

namespace App\Filament\Resources\GeorgsboteIssues\Pages\Concerns;

use Filament\Notifications\Notification;
use Livewire\Attributes\On;

/**
 * Nach dem Erzeugen der Titelbilder im Browser (resources/js/titelbild.js):
 * Ergebnis melden und die Seite neu laden, damit die Bilder erscheinen.
 */
trait MeldetTitelbilder
{
    /**
     * @param  list<string>  $fehler  Bezeichnungen der Ausgaben, bei denen es nicht geklappt hat
     */
    #[On('titelbilder-erzeugt')]
    public function titelbilderErzeugt(int $erzeugt = 0, array $fehler = []): void
    {
        $fehler = array_values(array_filter(array_map(fn ($label) => is_string($label) ? mb_substr($label, 0, 100) : null, $fehler)));

        $this->nachTitelbildern();

        Notification::make()
            ->title("Erzeugt: {$erzeugt}, Fehler: ".count($fehler))
            ->body($fehler ? 'Nicht geklappt: '.implode(', ', $fehler).'. Diese Titelbilder bitte von Hand hochladen oder später erneut versuchen.' : null)
            ->color($fehler ? 'warning' : 'success')
            ->send();
    }

    /** Hier nichts zu tun: Livewire rendert die Seite (und damit die Tabelle) ohnehin neu. */
    protected function nachTitelbildern(): void {}
}
