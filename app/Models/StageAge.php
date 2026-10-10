<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Altersangabe einer Stufe, von der Gruppierung festgelegt; überstimmt den
 * DPSG-Standard (config dpsg.stages) im Rahmen von App\Support\StufenAlter.
 *
 * @property string $stage
 * @property int $from
 * @property int $to
 */
class StageAge extends Model
{
    protected $primaryKey = 'stage';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['stage', 'from', 'to', 'updated_by'];

    protected function casts(): array
    {
        return ['from' => 'integer', 'to' => 'integer'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
