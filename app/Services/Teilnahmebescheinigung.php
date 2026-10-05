<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventRegistration;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Teilnahmebescheinigung als PDF im Corporate Design, mit Prüfcode.
 *
 * Der Prüfcode ist eine Prüfsumme (HMAC) über Veranstaltung und Namen, dazu
 * eine Prüfziffer gegen Tippfehler. Zum Prüfen gibt man Code und Namen ein;
 * gespeichert wird dafür nichts. So bleibt eine Bescheinigung prüfbar, auch
 * wenn die Anmeldung nach der Löschfrist längst gelöscht ist.
 *
 * Format: TB-<Veranstaltung>-XXXX-XXXX-P (Crockford-Base32, P = Prüfziffer).
 */
class Teilnahmebescheinigung
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public function code(EventRegistration $registration): string
    {
        return $this->codeFor($registration->event_id, $registration->fullName());
    }

    public function codeFor(int $eventId, string $name): string
    {
        $event = self::base32($eventId);
        $signatur = substr(self::base32FromBytes(substr($this->hmac($eventId, $name), 0, 5)), 0, 8);
        $kern = $event.$signatur;

        return 'TB-'.$event.'-'.substr($signatur, 0, 4).'-'.substr($signatur, 4, 4).'-'.self::pruefziffer($kern);
    }

    /**
     * Prüft Code und Namen. Liefert die Veranstaltung, wenn beides passt;
     * 'tippfehler', wenn die Prüfziffer nicht stimmt; sonst null.
     */
    public function verify(string $code, string $name): Event|string|null
    {
        // Trennzeichen und Leerzeichen egal; O/I/L wie 0/1 (Crockford).
        $rest = strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', $code));
        if (! str_starts_with($rest, 'TB') || strlen($rest) < 12) {
            return null;
        }
        $rest = strtr(substr($rest, 2), ['O' => '0', 'I' => '1', 'L' => '1']);
        if (strspn($rest, self::ALPHABET) !== strlen($rest)) {
            return null;
        }
        $event = substr($rest, 0, -9);
        $teil1 = substr($rest, -9, 4);
        $teil2 = substr($rest, -5, 4);
        $ziffer = substr($rest, -1);
        if (self::pruefziffer($event.$teil1.$teil2) !== $ziffer) {
            return 'tippfehler';
        }

        $eventId = self::fromBase32($event);
        $model = Event::query()->find($eventId);
        if (! $model || ! $model->certificate_enabled || trim($name) === '') {
            return null;
        }

        return hash_equals($this->codeFor($eventId, $name), 'TB-'.$event.'-'.$teil1.'-'.$teil2.'-'.$ziffer) ? $model : null;
    }

    public function verifyUrl(string $code): string
    {
        return route('certificates.verify', ['code' => $code]);
    }

    public function filename(EventRegistration $registration): string
    {
        return 'Teilnahmebescheinigung – '.str_replace(['/', '\\', '"'], '-', $registration->event->title).'.pdf';
    }

    public function pdf(EventRegistration $registration): string
    {
        $code = $this->code($registration);
        $html = view('pdf.teilnahmebescheinigung', [
            'registration' => $registration,
            'event' => $registration->event,
            'code' => $code,
            'url' => $this->verifyUrl($code),
            'qr' => $this->qr($this->verifyUrl($code)),
            'logo' => $this->logo(),
            'fonts' => resource_path('fonts/pdf'),
            'ort' => trim((string) preg_replace('/^\d{4,5}\s*/', '', (string) config('dpsg.contact.city'))),
            'datum' => ($registration->certificate_approved_at ?? now())->locale('de')->isoFormat('D. MMMM YYYY'),
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
        $dompdf->getOptions()->set('title', 'Teilnahmebescheinigung');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();
        $dompdf->addInfo('Title', 'Teilnahmebescheinigung – '.$registration->event->title);
        $dompdf->addInfo('Author', (string) config('dpsg.site.name'));

        return (string) $dompdf->output();
    }

    private function qr(string $url): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'outputBase64' => true,
            'scale' => 6,
            'quietzoneSize' => 1,
        ]);

        return (new QRCode($options))->render($url);
    }

    private function logo(): ?string
    {
        $pfad = public_path((string) config('dpsg.site.logo'));

        return is_file($pfad) ? $pfad : null;
    }

    private function hmac(int $eventId, string $name): string
    {
        $schluessel = hash_hmac('sha256', 'teilnahmebescheinigung', (string) config('app.key'), true);

        return hash_hmac('sha256', $eventId.'|'.self::normalize($name), $schluessel, true);
    }

    /** Groß-/Kleinschreibung, Umlaute (ä = ae) und Leerzeichen spielen keine Rolle. */
    public static function normalize(string $name): string
    {
        return Str::of(Str::ascii($name, 'de'))->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->value();
    }

    private static function base32(int $zahl): string
    {
        $text = '';
        do {
            $text = self::ALPHABET[$zahl % 32].$text;
            $zahl = intdiv($zahl, 32);
        } while ($zahl > 0);

        return $text;
    }

    private static function fromBase32(string $text): int
    {
        $zahl = 0;
        foreach (str_split($text) as $zeichen) {
            $zahl = $zahl * 32 + (int) strpos(self::ALPHABET, $zeichen);
        }

        return $zahl;
    }

    private static function base32FromBytes(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        return implode('', array_map(fn (string $fuenf) => self::ALPHABET[bindec(str_pad($fuenf, 5, '0'))], str_split($bits, 5)));
    }

    /** Luhn mod 32: erkennt Tippfehler in einem Zeichen und vertauschte Nachbarn. */
    private static function pruefziffer(string $text): string
    {
        $faktor = 2;
        $summe = 0;
        foreach (array_reverse(str_split($text)) as $zeichen) {
            $wert = $faktor * (int) strpos(self::ALPHABET, $zeichen);
            $faktor = $faktor === 2 ? 1 : 2;
            $summe += intdiv($wert, 32) + ($wert % 32);
        }

        return self::ALPHABET[(32 - ($summe % 32)) % 32];
    }
}
