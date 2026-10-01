<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Calendar extends Model
{
    protected $fillable = ['label', 'ics_url', 'color', 'stage', 'active', 'last_synced_at', 'last_hash', 'last_error'];

    protected $hidden = ['ics_url'];

    protected function casts(): array
    {
        return [
            'ics_url' => 'encrypted',
            'active' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * Lesbare Schriftfarbe auf der Kalenderfarbe (WCAG-Kontrast, 7.2).
     */
    public function textColor(): string
    {
        [$r, $g, $b] = array_map(
            fn (string $hex) => (($c = hexdec($hex) / 255) <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4),
            str_split(ltrim($this->color, '#'), 2)
        );
        $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

        // Kontrast zu Weiß vs. zu Dunkelgrau (#1c1f26, L ≈ 0.014)
        return (1.05 / ($luminance + 0.05)) >= (($luminance + 0.05) / 0.064) ? '#ffffff' : '#1c1f26';
    }

    /**
     * @return HasMany<CalendarEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(CalendarEvent::class);
    }
}
