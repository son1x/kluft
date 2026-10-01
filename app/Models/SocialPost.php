<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Veröffentlichung eines Blogbeitrags auf Instagram (F-IG-05).
 */
class SocialPost extends Model
{
    public const STATUSES = [
        'pending' => 'Ausstehend',
        'published' => 'Veröffentlicht',
        'failed' => 'Fehler',
    ];

    protected $fillable = [
        'post_id', 'platform', 'caption', 'image_path', 'status', 'external_id',
        'permalink', 'error', 'requested_by', 'posted_at',
    ];

    protected function casts(): array
    {
        return ['posted_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
