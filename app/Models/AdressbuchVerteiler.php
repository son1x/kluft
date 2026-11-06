<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Verteiler im Adressbuch (docs/adressbuch.md): gemeinsam für alle mit
 * Zugriff oder privat für die Person, die ihn angelegt hat.
 *
 * @property int $id
 * @property string $name
 * @property bool $privat
 * @property int|null $user_id
 */
class AdressbuchVerteiler extends Model
{
    protected $table = 'adressbuch_verteiler';

    protected $fillable = ['name', 'beschreibung', 'privat', 'user_id'];

    protected function casts(): array
    {
        return ['privat' => 'boolean'];
    }

    /**
     * @return BelongsToMany<AdressbuchKontakt, $this>
     */
    public function kontakte(): BelongsToMany
    {
        return $this->belongsToMany(AdressbuchKontakt::class, 'adressbuch_kontakt_verteiler', 'verteiler_id', 'kontakt_id')->orderByRaw('COALESCE(adressbuch_kontakte.name, adressbuch_kontakte.email)');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return list<string>
     */
    public function adressen(): array
    {
        return $this->kontakte->pluck('email')->values()->all();
    }
}
