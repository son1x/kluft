<?php

namespace App\Services\Zuschuss;

use App\Models\Event;
use App\Support\Zuschuesse;
use App\Support\Zuschuesse\Foerderrechner;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

/**
 * Teilnahmeliste (für Jugendamt, Landesjugendamt, Stadt und Bistum) und
 * Förderschätzung als PDF (docs/pflichtenheft-zuschuesse.md, ZU-11, ZU-18).
 * Erzeugt beim Abruf, nicht gespeichert.
 */
class ZuschussPdf
{
    /** Unterlagen, die zu den gewählten Zuschussgebern gehören. */
    public const UNTERLAGEN = [
        'liste' => ['Teilnahmeliste', 'teilnahmeliste'],
        'schaetzung' => ['Förderschätzung', 'foerderschaetzung'],
        'a' => ['Antrag Land/Kreis (Formular A)', 'formular-a-antrag'],
        'bericht' => ['Bericht (Formular D/E)', 'bericht'],
        'c' => ['Aufenthaltsbestätigung (Formular C)', 'formular-c-aufenthaltsbestaetigung'],
        'stadt' => ['Antrag Stadt Saarlouis', 'antrag-stadt-saarlouis'],
        'bistum' => ['Antrag Bistum Trier (KJP)', 'antrag-bistum-trier'],
    ];

    /** @return array<string, string> Schlüssel => Bezeichnung */
    public static function unterlagen(Event $event): array
    {
        $geber = (array) ($event->zuschuss_geber ?: array_keys(Zuschuesse::GEBER));
        $landKreis = in_array('land', $geber, true) || in_array('kreis_sls', $geber, true);

        return collect(self::UNTERLAGEN)->map(fn (array $u) => $u[0])->filter(fn (string $label, string $key) => match ($key) {
            'a', 'bericht' => $landKreis,
            'c' => $landKreis && Zuschuesse::brauchtAufenthaltsbestaetigung($event),
            'stadt' => in_array('stadt_sls', $geber, true),
            'bistum' => in_array('bistum_trier', $geber, true),
            default => true,
        })->all();
    }

    public static function dateiname(Event $event, string $art): string
    {
        return (self::UNTERLAGEN[$art][1] ?? $art).'-'.str($event->title)->slug().'.pdf';
    }

    public function unterlage(Event $event, string $art): string
    {
        if (in_array($art, ['liste', 'schaetzung'], true)) {
            return $art === 'liste' ? $this->teilnahmeliste($event) : $this->schaetzung($event);
        }
        $personen = Zuschuesse::personen($event);
        $daten = [
            'event' => $event,
            'as' => Zuschuesse::antragsteller($event),
            'zaehlung' => Zuschuesse::zaehlung($event),
            'finanzen' => Foerderrechner::finanzen($event),
            'personen' => $personen,
            'betreuung' => $personen->filter(fn (array $p) => $p['funktion'] !== 'TN')->values(),
            'programm' => Zuschuesse::programm($event),
        ];
        if ($art === 'bistum') {
            $andere = collect(Foerderrechner::rechnen($event)['geber'])->reject(fn (array $g) => $g['key'] === 'bistum_trier' || $g['betrag'] === 0);
            $daten['andereZuschuesse'] = (int) $andere->sum('betrag');
            $daten['andereGeber'] = $andere->pluck('name')->all();
        }
        $view = ['a' => 'pdf.zuschuss-formular-a', 'bericht' => 'pdf.zuschuss-bericht', 'c' => 'pdf.zuschuss-formular-c',
            'stadt' => 'pdf.zuschuss-stadt-sls', 'bistum' => 'pdf.zuschuss-bistum'][$art] ?? abort(404);

        return $this->render($view, $daten, 'portrait', (self::UNTERLAGEN[$art][0] ?? $art).' – '.$event->title);
    }

    /** @param list<array<string, mixed>> $personen */
    public function proKopf(array $personen, Carbon $stichtag, Event $vorlage): string
    {
        return $this->render('pdf.zuschuss-pro-kopf', ['personen' => $personen, 'stichtag' => $stichtag, 'as' => Zuschuesse::antragsteller($vorlage)],
            'portrait', 'Pro-Kopf-Zuschuss '.$stichtag->year);
    }

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
    public function render(string $view, array $daten, string $ausrichtung, string $titel): string
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
