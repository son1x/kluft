<?php

namespace App\Models;

use App\Services\Glossary;
use Illuminate\Database\Eloquent\Model;

/**
 * Begriff mit Erklärung für den Hover im Text.
 */
class GlossaryTerm extends Model
{
    protected $fillable = ['term', 'explanation'];

    protected static function booted(): void
    {
        static::saved(fn () => Glossary::flush());
        static::deleted(fn () => Glossary::flush());
    }
}
