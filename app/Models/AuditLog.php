<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Keine Inhalte/Personendaten – nur wer/was/wann. IP wird gekürzt
 * gespeichert (s. App\Support\AuditLogger). Aufbewahrung 12 Monate,
 * Bereinigung über den geplanten Job `audit:prune` (Pflichtenheft 11.4).
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'ip_truncated',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
