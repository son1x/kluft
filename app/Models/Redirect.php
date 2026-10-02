<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Weiterleitung einer alten Adresse (z. B. WordPress-Beitrag) auf die neue
 * (Pflichtenheft F-MIG-05).
 */
class Redirect extends Model
{
    public const CACHE_KEY = 'redirects:map';

    protected $fillable = ['from_path', 'to_path', 'status_code', 'source', 'hits', 'last_hit_at'];

    protected function casts(): array
    {
        return ['status_code' => 'integer', 'hits' => 'integer', 'last_hit_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(fn (Redirect $r) => $r->from_path = self::normalize($r->from_path));
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * Einheitliche Form: führender Schrägstrich, kein abschließender,
     * Kleinbuchstaben, ohne Domain und Query.
     */
    public static function normalize(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?: '/';
        $path = '/'.trim(rawurldecode($path), '/');

        return mb_strtolower($path);
    }
}
