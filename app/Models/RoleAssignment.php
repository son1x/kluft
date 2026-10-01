<?php

namespace App\Models;

use App\Support\AuditLogger;
use App\Support\RoleCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleAssignment extends Model
{
    protected $fillable = [
        'user_id',
        'role',
        'scope_type',
        'scope_key',
        'granted_by',
    ];

    /**
     * Jede Rollenvergabe/-änderung landet im Audit-Log (5.5).
     */
    protected static function booted(): void
    {
        $log = fn (string $action) => fn (RoleAssignment $assignment) => AuditLogger::log(
            "{$action}:".RoleCatalog::encode($assignment),
            auth()->user(),
            'user',
            $assignment->user_id,
            request(),
        );

        static::created($log('role.granted'));
        static::deleted($log('role.revoked'));
        static::updated($log('role.changed'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
