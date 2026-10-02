<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ein Lauf von „php artisan wp:import“ als Bericht (F-MIG-08).
 */
class WpImportRun extends Model
{
    protected $fillable = ['dry_run', 'options', 'counts', 'log', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return [
            'dry_run' => 'boolean',
            'options' => 'array',
            'counts' => 'array',
            'log' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
