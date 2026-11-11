<?php

namespace App\Support;

use App\Models\Rolle;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Eigene Rollen je Anfrage einmal laden (im Container als „scoped“
 * registriert, also je Anfrage bzw. Job frisch).
 */
class RollenRegister
{
    /** @var array<string, Rolle>|null */
    private ?array $rollen = null;

    /** @return array<string, Rolle> */
    public function alle(): array
    {
        if ($this->rollen === null) {
            try {
                $this->rollen = Schema::hasTable('rollen') ? Rolle::query()->orderBy('name')->get()->keyBy('schluessel')->all() : [];
            } catch (Throwable) {
                $this->rollen = [];
            }
        }

        return $this->rollen;
    }

    public function vergessen(): void
    {
        $this->rollen = null;
    }
}
