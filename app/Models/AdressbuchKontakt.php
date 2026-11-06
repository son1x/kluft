<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Kontakt im Adressbuch (docs/adressbuch.md).
 *
 * @property int $id
 * @property string|null $name
 * @property string $email
 * @property string|null $organisation
 */
class AdressbuchKontakt extends Model
{
    protected $table = 'adressbuch_kontakte';

    protected $fillable = ['name', 'email', 'organisation', 'notiz', 'user_id'];

    protected static function booted(): void
    {
        static::saving(function (AdressbuchKontakt $k) {
            $k->email = mb_strtolower(trim((string) $k->email));
            $k->name = filled($k->name) ? trim((string) $k->name) : null;
        });
    }

    /**
     * @return BelongsToMany<AdressbuchVerteiler, $this>
     */
    public function verteiler(): BelongsToMany
    {
        return $this->belongsToMany(AdressbuchVerteiler::class, 'adressbuch_kontakt_verteiler', 'kontakt_id', 'verteiler_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** „Name <mail>“ bzw. nur die Adresse. */
    public function anzeige(): string
    {
        return $this->name ? $this->name.' <'.$this->email.'>' : $this->email;
    }
}
