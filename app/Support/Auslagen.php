<?php

namespace App\Support;

use App\Models\Auslage;
use App\Models\SiteSetting;
use App\Models\User;
use DOMDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Auslagen und Fahrtkosten erstatten (docs/auslagen.md): Einreichen darf
 * jede Person mit aktivem Konto; prüfen und auszahlen die Kasse (im Stamm)
 * bzw. Kasse und Verwaltung (DV, Bezirk).
 */
class Auslagen
{
    public const KM_SATZ = 'auslagen.km_satz_cent';

    public const KM_SATZ_STANDARD = 30;

    /** Ausgezahlte Auslagen samt Belegen: 10 Jahre (Aufbewahrung nach Steuerrecht). */
    public const AUFBEWAHRUNG_JAHRE = 10;

    /** Abgelehnte Auslagen: nach einem Jahr gelöscht. */
    public const ABGELEHNT_MONATE = 12;

    public static function aktiv(): bool
    {
        return Features::enabled(Features::AUSLAGEN);
    }

    public static function zugriff(?User $user): bool
    {
        return self::aktiv() && $user !== null && $user->isActive();
    }

    public static function kasse(?User $user): bool
    {
        if (! self::zugriff($user)) {
            return false;
        }

        return $user->isKasse() || $user->darf('kasse');
    }

    public static function kmSatzCent(): int
    {
        $wert = Cache::rememberForever(self::KM_SATZ, fn () => (string) SiteSetting::query()->whereKey(self::KM_SATZ)->value('value'));

        return ctype_digit($wert) && (int) $wert > 0 ? (int) $wert : self::KM_SATZ_STANDARD;
    }

    public static function kmSatzSetzen(int $cent, ?int $userId): void
    {
        SiteSetting::query()->updateOrCreate(['key' => self::KM_SATZ], ['value' => (string) max(1, $cent), 'updated_by' => $userId]);
        Cache::forget(self::KM_SATZ);
    }

    /**
     * @return Collection<int, User>
     */
    public static function kassenteam(): Collection
    {
        return User::query()->where('status', 'active')->with('roleAssignments')->get()
            ->filter(fn (User $u) => self::kasse($u) && ($u->hasRole('kasse') || $u->hasRole('verwaltung')))->values();
    }

    /**
     * SEPA-Sammelüberweisung (pain.001.001.03) für freigegebene Auslagen,
     * Auftraggeber ist die Bankverbindung unter Kasse → Zahlungen.
     *
     * @param  Collection<int, Auslage>  $auslagen
     */
    public static function sepaXml(Collection $auslagen, ?string $ausfuehrung = null): string
    {
        $konto = Zahlungen::konto();
        if ($konto === null) {
            throw new RuntimeException('Keine Bankverbindung hinterlegt (Kasse → Zahlungen → Bankverbindung).');
        }
        $auslagen = $auslagen->filter(fn (Auslage $a) => $a->status === 'freigegeben' && filled($a->konto['iban'] ?? null))->values();
        if ($auslagen->isEmpty()) {
            throw new RuntimeException('Keine freigegebenen Auslagen mit Bankverbindung.');
        }

        $summe = $auslagen->sum('summe_cent');
        $id = 'KLUFT-'.now()->format('YmdHis');
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;
        $root = $doc->appendChild($doc->createElementNS('urn:iso:std:iso:20022:tech:xsd:pain.001.001.03', 'Document'));
        $init = $root->appendChild($doc->createElement('CstmrCdtTrfInitn'));

        $kopf = $init->appendChild($doc->createElement('GrpHdr'));
        self::el($doc, $kopf, 'MsgId', $id);
        self::el($doc, $kopf, 'CreDtTm', now()->format('Y-m-d\TH:i:s'));
        self::el($doc, $kopf, 'NbOfTxs', (string) $auslagen->count());
        self::el($doc, $kopf, 'CtrlSum', self::betrag($summe));
        self::el($doc, $kopf->appendChild($doc->createElement('InitgPty')), 'Nm', self::text($konto['inhaber'], 70));

        $pmt = $init->appendChild($doc->createElement('PmtInf'));
        self::el($doc, $pmt, 'PmtInfId', $id.'-1');
        self::el($doc, $pmt, 'PmtMtd', 'TRF');
        self::el($doc, $pmt, 'NbOfTxs', (string) $auslagen->count());
        self::el($doc, $pmt, 'CtrlSum', self::betrag($summe));
        self::el($doc, $pmt->appendChild($doc->createElement('PmtTpInf'))->appendChild($doc->createElement('SvcLvl')), 'Cd', 'SEPA');
        self::el($doc, $pmt, 'ReqdExctnDt', $ausfuehrung ?? now()->toDateString());
        self::el($doc, $pmt->appendChild($doc->createElement('Dbtr')), 'Nm', self::text($konto['inhaber'], 70));
        self::el($doc, $pmt->appendChild($doc->createElement('DbtrAcct'))->appendChild($doc->createElement('Id')), 'IBAN', $konto['iban']);
        $bic = $pmt->appendChild($doc->createElement('DbtrAgt'))->appendChild($doc->createElement('FinInstnId'));
        filled($konto['bic']) ? self::el($doc, $bic, 'BIC', $konto['bic']) : self::el($doc, $bic->appendChild($doc->createElement('Othr')), 'Id', 'NOTPROVIDED');
        self::el($doc, $pmt, 'ChrgBr', 'SLEV');

        foreach ($auslagen as $a) {
            $tx = $pmt->appendChild($doc->createElement('CdtTrfTxInf'));
            self::el($doc, $tx->appendChild($doc->createElement('PmtId')), 'EndToEndId', 'AUSLAGE-'.$a->id);
            $amt = $tx->appendChild($doc->createElement('Amt'));
            $wert = self::el($doc, $amt, 'InstdAmt', self::betrag($a->summe_cent));
            $wert->setAttribute('Ccy', 'EUR');
            self::el($doc, $tx->appendChild($doc->createElement('Cdtr')), 'Nm', self::text($a->konto['inhaber'] ?? $a->name, 70));
            self::el($doc, $tx->appendChild($doc->createElement('CdtrAcct'))->appendChild($doc->createElement('Id')), 'IBAN', Zahlungen::ibanNormal($a->konto['iban']));
            self::el($doc, $tx->appendChild($doc->createElement('RmtInf')), 'Ustrd', self::zweck($a));
        }

        return (string) $doc->saveXML();
    }

    public static function zweck(Auslage $a): string
    {
        return self::text('Erstattung '.$a->anlass.' '.$a->datum->format('d.m.Y').' Nr '.$a->id, 140);
    }

    private static function el(DOMDocument $doc, \DOMNode $parent, string $name, string $value): \DOMElement
    {
        $el = $doc->createElement($name);
        $el->appendChild($doc->createTextNode($value));
        $parent->appendChild($el);

        return $el;
    }

    private static function betrag(int $cent): string
    {
        return number_format($cent / 100, 2, '.', '');
    }

    /** SEPA-Zeichensatz: Umlaute umschreiben, Unbekanntes weglassen. */
    public static function text(string $text, int $max): string
    {
        $text = strtr($text, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss', '„' => '', '“' => '', '–' => '-']);
        $text = (string) preg_replace("/[^A-Za-z0-9\/\-?:().,'+ ]/", '', $text);

        return mb_substr(trim((string) preg_replace('/\s+/', ' ', $text)), 0, $max);
    }
}
