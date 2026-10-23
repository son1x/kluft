<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SetTeil extends Model
{
    protected $table = 'shop_set_teile';

    public $timestamps = false;

    protected $fillable = ['set_id', 'artikel_id', 'menge', 'optional', 'vorgewaehlt', 'sort'];

    protected function casts(): array
    {
        return ['menge' => 'integer', 'optional' => 'boolean', 'vorgewaehlt' => 'boolean', 'sort' => 'integer'];
    }

    /** @return BelongsTo<Set, $this> */
    public function set(): BelongsTo
    {
        return $this->belongsTo(Set::class, 'set_id');
    }

    /** @return BelongsTo<Artikel, $this> */
    public function artikel(): BelongsTo
    {
        return $this->belongsTo(Artikel::class, 'artikel_id');
    }
}
