<?php

namespace App\Services\Sammelbestellung;

use App\Models\Shop\Bestellung;
use App\Models\Shop\Position;
use App\Models\Shop\Runde;
use App\Support\SammelbestellungAblauf;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;

/**
 * Ausgabeliste je Stufe und Ausgabescheine je Bestellung als PDF, jeweils
 * mit Unterschriftsfeld (SB-42). Nur bezahlte Positionen.
 */
class Ausgabe
{
    /** @param 'liste'|'scheine' $art */
    public function pdf(Runde $runde, string $art = 'liste', ?Bestellung $nur = null): string
    {
        $gruppen = SammelbestellungAblauf::ausgabeliste($runde, $nur);
        $html = view('pdf.sammelbestellung-ausgabe', [
            'runde' => $runde,
            'art' => $art,
            'gruppen' => $gruppen,
            // Für Scheine: je Bestellung alle Positionen, nach Name der bestellenden Person.
            'scheine' => $gruppen->flatten(1)->groupBy('bestellung_id')
                ->sortBy(fn ($positionen) => mb_strtolower((string) $positionen->first()->bestellung->name)),
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
        $dompdf->setPaper('A4', $art === 'liste' ? 'landscape' : 'portrait');
        $dompdf->render();
        $dompdf->addInfo('Title', ($art === 'liste' ? 'Ausgabeliste' : 'Ausgabescheine').' – '.$runde->name);
        $dompdf->addInfo('Author', (string) config('dpsg.site.name'));

        return (string) $dompdf->output();
    }

    public static function artikelText(Position $p): string
    {
        return $p->bezeichnung.($p->groesse ? ' ('.$p->groesse.')' : '');
    }
}
