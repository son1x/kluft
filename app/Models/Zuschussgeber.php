<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Zuschussgeber einer Instanz: eingebaute Vorlage (feste Regeln und eigener
 * Vordruck) oder eigene Regeln (Sätze, Gebiet, Frist, allgemeiner Antrag).
 *
 * @property array<string, mixed>|null $regeln
 */
class Zuschussgeber extends Model
{
    protected $table = 'zuschussgeber';

    protected $fillable = ['key', 'name', 'vorlage', 'aktiv', 'regeln', 'sort'];

    protected function casts(): array
    {
        return ['aktiv' => 'boolean', 'regeln' => 'array'];
    }

    public function eigen(): bool
    {
        return $this->vorlage === null;
    }

    /** Vordruck: formular_a (Saarland), stadt_sls, bistum_trier oder allgemein. */
    public function vordruck(): string
    {
        return match ($this->vorlage) {
            'land', 'kreis_sls' => 'formular_a',
            'stadt_sls' => 'stadt_sls',
            'bistum_trier' => 'bistum_trier',
            default => (string) ($this->regeln['vordruck'] ?? 'allgemein'),
        };
    }
}
