<?php

namespace App\Models;

use App\Support\AuditLogger;
use App\Support\Rechte;
use App\Support\RollenRegister;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Eigene Rolle aus der Rechte-Matrix (docs/rollen.md). Der Schlüssel steht
 * in role_assignments.role (Präfix „r_“).
 *
 * @property list<string> $rechte
 */
class Rolle extends Model
{
    protected $table = 'rollen';

    public const GELTUNG = ['global' => 'Ganze Website', 'bereich' => 'Je Bereich (Stufe oder Fachbereich)'];

    protected $fillable = ['schluessel', 'name', 'beschreibung', 'geltung', 'rechte', 'vorlage'];

    protected function casts(): array
    {
        return ['rechte' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (Rolle $rolle) {
            if (blank($rolle->schluessel)) {
                $basis = Rechte::EIGEN.Str::limit(Str::slug($rolle->name, '_'), 20, '');
                $schluessel = $basis;
                for ($i = 2; static::query()->where('schluessel', $schluessel)->exists(); $i++) {
                    $schluessel = Str::limit($basis, 26, '').'_'.$i;
                }
                $rolle->schluessel = $schluessel;
            }
        });
        static::saving(function (Rolle $rolle) {
            // Nur Rechte aus dem Katalog, passend zur Geltung, ohne Doppelte.
            $rolle->rechte = array_values(array_unique(array_filter((array) $rolle->rechte,
                fn ($r) => is_string($r) && isset(Rechte::KATALOG[$r]) && Rechte::passtZuGeltung($r, $rolle->geltung))));
        });
        static::saved(function (Rolle $rolle) {
            app(RollenRegister::class)->vergessen();
            if ($rolle->wasChanged('rechte') || $rolle->wasRecentlyCreated) {
                AuditLogger::log('rolle.rechte:'.$rolle->schluessel.'='.implode(',', $rolle->rechte), auth()->user());
            }
        });
        static::deleted(function (Rolle $rolle) {
            app(RollenRegister::class)->vergessen();
            AuditLogger::log('rolle.geloescht:'.$rolle->schluessel, auth()->user());
        });
    }

    public function zuweisungen(): int
    {
        return RoleAssignment::query()->where('role', $this->schluessel)->count();
    }
}
