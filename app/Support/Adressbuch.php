<?php

namespace App\Support;

use App\Models\AdressbuchKontakt;
use App\Models\AdressbuchVerteiler;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Adressbuch (docs/adressbuch.md): Zugriff wie bei Umfragen und
 * Videokonferenzen (ab Diözesanleitung). Kontakte sind gemeinsam;
 * Verteiler sind gemeinsam oder privat. Bearbeiten darf, wer einen
 * Verteiler angelegt hat, und die Verwaltung.
 */
class Adressbuch
{
    public static function zugriff(?User $user): bool
    {
        if (! Features::enabled(Features::ADRESSBUCH) || $user === null) {
            return false;
        }

        return $user->darf('adressbuch');
    }

    /**
     * @return Builder<AdressbuchVerteiler>
     */
    public static function sichtbareVerteiler(?User $user): Builder
    {
        $query = AdressbuchVerteiler::query();
        if ($user?->darf('adressbuch.alle')) {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q->where('privat', false)->orWhere('user_id', $user?->id));
    }

    public static function darfBearbeiten(?User $user, AdressbuchVerteiler $v): bool
    {
        return self::zugriff($user) && ($user->darf('adressbuch.alle') || $v->user_id === $user->id || ($v->user_id === null && ! $v->privat));
    }

    /**
     * Adressen aus Verteilern, die die Person sehen darf.
     *
     * @param  array<int, int|string>  $verteilerIds
     * @return list<string>
     */
    public static function adressen(array $verteilerIds, ?User $user): array
    {
        if ($verteilerIds === [] || ! self::zugriff($user)) {
            return [];
        }

        return self::sichtbareVerteiler($user)->whereIn('id', $verteilerIds)->with('kontakte')->get()
            ->flatMap(fn (AdressbuchVerteiler $v) => $v->adressen())->unique()->values()->all();
    }

    /**
     * Auswahl für Formulare: sichtbare Verteiler mit Anzahl.
     *
     * @return array<int, string>
     */
    public static function verteilerOptionen(?User $user): array
    {
        return self::sichtbareVerteiler($user)->withCount('kontakte')->orderBy('name')->get()
            ->mapWithKeys(fn (AdressbuchVerteiler $v) => [$v->id => $v->name.' ('.$v->kontakte_count.')'.($v->privat ? ' · privat' : '')])->all();
    }

    /**
     * Adressen als Vorschläge beim Tippen.
     *
     * @return list<string>
     */
    public static function vorschlaege(?User $user): array
    {
        return self::zugriff($user) ? AdressbuchKontakt::query()->orderBy('email')->limit(2000)->pluck('email')->all() : [];
    }

    /**
     * Freitext (aus Outlook, Excel, Mail) in Name und Adresse zerlegen:
     * „Name <mail>“, „mail“, „Name; mail“, „Name, mail“, Tab-getrennt.
     *
     * @return list<array{name: string|null, email: string}>
     */
    public static function zerlegen(string $text): array
    {
        $ergebnis = [];
        foreach (preg_split('/\r\n|\r|\n|;/', $text) ?: [] as $zeile) {
            if (! preg_match_all('/[A-Z0-9._%+\'-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $zeile, $treffer)) {
                continue;
            }
            foreach ($treffer[0] as $email) {
                $email = mb_strtolower($email);
                if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || isset($ergebnis[$email])) {
                    continue;
                }
                $name = count($treffer[0]) === 1
                    ? trim((string) preg_replace(['/<?[^\s<>]+@[^\s<>]+>?/', '/[\t;"\'()]+/', '/\s+/'], ['', ' ', ' '], $zeile), " ,\t")
                    : '';
                // Outlook: „Nachname, Vorname“ → „Vorname Nachname“
                if (preg_match('/^([^,]+),\s*([^,]+)$/u', $name, $teile)) {
                    $name = trim($teile[2]).' '.trim($teile[1]);
                }
                $ergebnis[$email] = ['name' => $name !== '' ? mb_substr($name, 0, 150) : null, 'email' => $email];
            }
        }

        return array_values($ergebnis);
    }

    /**
     * Kontakte anlegen bzw. finden und in den Verteiler übernehmen.
     *
     * @param  list<array{name: string|null, email: string}>  $eintraege
     * @return array{neu: int, uebernommen: int}
     */
    public static function uebernehmen(AdressbuchVerteiler $v, array $eintraege, ?User $user): array
    {
        $neu = 0;
        $ids = [];
        foreach ($eintraege as $e) {
            $kontakt = AdressbuchKontakt::query()->firstOrNew(['email' => $e['email']]);
            if (! $kontakt->exists) {
                $kontakt->fill(['name' => $e['name'], 'user_id' => $user?->id])->save();
                $neu++;
            } elseif (blank($kontakt->name) && filled($e['name'])) {
                $kontakt->update(['name' => $e['name']]);
            }
            $ids[] = $kontakt->id;
        }
        $vorher = $v->kontakte()->count();
        $v->kontakte()->syncWithoutDetaching($ids);

        return ['neu' => $neu, 'uebernommen' => $v->kontakte()->count() - $vorher];
    }
}
