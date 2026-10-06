<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Schalter einer Funktion im Backend; überstimmt den Standard der Instanz.
 *
 * @property string $key
 * @property bool $enabled
 */
class FeatureSwitch extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'enabled', 'updated_by'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
