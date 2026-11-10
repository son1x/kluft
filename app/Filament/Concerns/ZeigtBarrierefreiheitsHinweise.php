<?php

namespace App\Filament\Concerns;

use App\Support\Barrierefreiheit;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

/**
 * Nach dem Speichern von Seiten und Beiträgen: Hinweise zur
 * Barrierefreiheit als Mitteilung (App\Support\Barrierefreiheit). Speichern
 * klappt immer.
 */
trait ZeigtBarrierefreiheitsHinweise
{
    protected function barrierefreiheitPruefen(): void
    {
        $hinweise = Barrierefreiheit::pruefen((string) ($this->record->body ?? ''));
        if ($hinweise === []) {
            return;
        }

        Notification::make()
            ->title('Hinweise zur Barrierefreiheit')
            ->body(new HtmlString('<ul style="list-style: disc; padding-left: 1.1rem; display: grid; gap: .35rem;">'
                .implode('', array_map(fn (string $h) => '<li>'.e($h).'</li>', $hinweise)).'</ul>'
                .'<p style="margin-top: .5rem; opacity: .8;">Gespeichert ist trotzdem alles.</p>'))
            ->warning()
            ->persistent()
            ->send();
    }

    protected function afterSave(): void
    {
        $this->barrierefreiheitPruefen();
    }

    protected function afterCreate(): void
    {
        $this->barrierefreiheitPruefen();
    }
}
