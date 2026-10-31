<?php

namespace App\Services\Zuschuss;

use App\Models\Event;
use App\Support\Zuschuesse;
use App\Support\Zuschuesse\Foerderrechner;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;

/**
 * Teilnahmeliste (für Jugendamt, Landesjugendamt, Stadt und Bistum) und
 * Förderschätzung als PDF (docs/pflichtenheft-zuschuesse.md, ZU-11, ZU-18).
 * Erzeugt beim Abruf, nicht gespeichert.
 */
class ZuschussPdf
{
    public function teilnahmeliste(Event $event): string
    {
        $personen = Zuschuesse::personen($event);

        return $this->render('pdf.zuschuss-teilnahmeliste', [
            'event' => $event,
            'betreuung' => $personen->filter(fn (array $p) => $p['funktion'] !== 'TN')->values(),
            'teilnehmende' => $personen->where('funktion', 'TN')->values(),
            'zaehlung' => Zuschuesse::zaehlung($event),
        ], 'landscape', 'Teilnahmeliste – '.$event->title);
    }

    public function schaetzung(Event $event): string
    {
        return $this->render('pdf.zuschuss-schaetzung', [
            'event' => $event,
            'zaehlung' => Zuschuesse::zaehlung($event),
            'schaetzung' => Foerderrechner::rechnen($event),
        ], 'portrait', 'Förderschätzung – '.$event->title);
    }

    /** @param array<string, mixed> $daten */
    private function render(string $view, array $daten, string $ausrichtung, string $titel): string
    {
        $html = view($view, $daten + [
            'fonts' => resource_path('fonts/pdf'),
            'logo' => is_file($pfad = public_path((string) config('dpsg.site.logo'))) ? $pfad : null,
        ])->render();

        $cache = storage_path('app/private/dompdf');
        File::ensureDirectoryExists($cache);
        $options = new Options;
        $options->setChroot([base_path()]);
        $options->setFontDir($cache);
        $options->setFontCache($cache);
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('Carlito');
        $options->setDpi(96);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', $ausrichtung);
        $dompdf->render();
        $dompdf->addInfo('Title', $titel);
        $dompdf->addInfo('Author', (string) config('dpsg.site.name'));

        return (string) $dompdf->output();
    }
}
