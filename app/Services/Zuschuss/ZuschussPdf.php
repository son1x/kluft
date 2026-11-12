<?php

namespace App\Services\Zuschuss;

use App\Models\Event;
use App\Support\Unterschriften;
use App\Support\Zuschuesse;
use App\Support\Zuschuesse\Antrag;
use App\Support\Zuschuesse\Foerderrechner;
use App\Support\Zuschuesse\Geber;
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

    /**
     * Unterlagen zu den gewählten Zuschussgebern: Vordrucke der Vorlagen,
     * dazu je eigenem Zuschussgeber mit allgemeinem Vordruck „antrag_<key>“.
     *
     * @return array<string, string> Schlüssel => Bezeichnung
     */
    public static function unterlagen(Event $event): array
    {
        $vordrucke = collect(Geber::vonEvent($event))->mapWithKeys(fn (string $key) => [$key => Geber::vordruck($key)]);
        $formularA = $vordrucke->contains('formular_a');

        $liste = collect(self::UNTERLAGEN)->map(fn (array $u) => $u[0])->filter(fn (string $label, string $key) => match ($key) {
            'a', 'bericht' => $formularA,
            'c' => $formularA && Zuschuesse::brauchtAufenthaltsbestaetigung($event),
            'stadt' => $vordrucke->contains('stadt_sls'),
            'bistum' => $vordrucke->contains('bistum_trier'),
            default => true,
        })->all();
        foreach ($vordrucke->filter(fn (string $v) => $v === 'allgemein')->keys() as $key) {
            $liste['antrag_'.$key] = 'Antrag '.Geber::name($key);
        }

        return $liste;
    }

    public static function bezeichnung(Event $event, string $art): string
    {
        return self::unterlagen($event)[$art] ?? (self::UNTERLAGEN[$art][0] ?? $art);
    }

    public static function dateiname(Event $event, string $art): string
    {
        $basis = str_starts_with($art, 'antrag_') ? 'antrag-'.str(Geber::name(substr($art, 7)))->slug() : (self::UNTERLAGEN[$art][1] ?? $art);

        return $basis.'-'.str($event->title)->slug().'.pdf';
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
            // Bewilligt, sonst beantragt, sonst geschätzt (Maske „Zuschussantrag“).
            $andere = collect(Antrag::uebersicht($event))->reject(fn (array $z) => $z['key'] === 'bistum_trier')
                ->map(fn (array $z) => ['name' => $z['name'], 'betrag' => Antrag::betrag($z)])->filter(fn (array $z) => $z['betrag'] > 0);
            $daten['andereZuschuesse'] = (int) $andere->sum('betrag');
            $daten['andereGeber'] = $andere->pluck('name')->all();
        }
        if ($art === 'stadt') {
            $daten['dritte'] = Antrag::bewilligteDritte($event, 'stadt_sls');
        }
        if (str_starts_with($art, 'antrag_') && array_key_exists($art, self::unterlagen($event))) {
            $key = substr($art, 7);
            $daten['geber'] = ['key' => $key, 'name' => Geber::name($key), 'regeln' => Geber::regeln($key),
                'schaetzung' => collect(Foerderrechner::rechnen($event)['geber'])->firstWhere('key', $key)];
            $daten['stand'] = collect(Antrag::uebersicht($event))->firstWhere('key', $key);
            $daten['dritte'] = Antrag::bewilligteDritte($event, $key);

            return $this->render('pdf.zuschuss-allgemein', $daten, 'portrait', self::bezeichnung($event, $art).' – '.$event->title);
        }
        $daten['jugendaemter'] = Geber::jugendaemterFormularA($event);
        $view = ['a' => 'pdf.zuschuss-formular-a', 'bericht' => 'pdf.zuschuss-bericht', 'c' => 'pdf.zuschuss-formular-c',
            'stadt' => 'pdf.zuschuss-stadt-sls', 'bistum' => 'pdf.zuschuss-bistum'][$art] ?? abort(404);

        return $this->render($view, $daten, 'portrait', self::bezeichnung($event, $art).' – '.$event->title);
    }

    /** @param list<array<string, mixed>> $personen */
    public function proKopf(array $personen, Carbon $stichtag, Event $vorlage): string
    {
        return $this->render('pdf.zuschuss-pro-kopf', ['personen' => $personen, 'stichtag' => $stichtag, 'as' => Zuschuesse::antragsteller($vorlage)],
            'portrait', 'Pro-Kopf-Zuschuss '.$stichtag->year);
    }

    /**
     * Teilnahmeliste. Ist sie festgeschrieben (Vor Ort, docs/vor-ort.md),
     * kommt die abgelegte Fassung; sonst neu erzeugt mit den vorhandenen
     * digitalen Unterschriften.
     */
    public function teilnahmeliste(Event $event, bool $festschreiben = false): string
    {
        if (! $festschreiben && ($abgelegt = Unterschriften::festgeschrieben($event)) !== null) {
            return $abgelegt;
        }
        $personen = Zuschuesse::personen($event);

        return $this->render('pdf.zuschuss-teilnahmeliste', [
            'unterschriften' => Unterschriften::fuerListe($event),
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
