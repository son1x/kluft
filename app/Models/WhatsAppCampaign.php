<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Eine WhatsApp-Aussendung: Vorlage + Werte, Freigabe, Versand, Summen.
 *
 * @property string $title
 * @property int|null $template_id
 * @property array{body?: list<string>, url?: string|null}|null $variables
 * @property int|null $post_id
 * @property string $status
 * @property Carbon|null $scheduled_at
 * @property int|null $created_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property int $recipients
 * @property int $sent
 * @property int $delivered
 * @property int $failed
 * @property string|null $estimated_cost_eur
 * @property string|null $last_error
 */
class WhatsAppCampaign extends Model
{
    public const STATUSES = [
        'draft' => 'Entwurf',
        'review' => 'Zur Freigabe',
        'approved' => 'Freigegeben',
        'sending' => 'Wird versendet',
        'scheduled' => 'Geplant',
        'paused' => 'Angehalten',
        'sent' => 'Versendet',
        'cancelled' => 'Abgebrochen',
    ];

    protected $table = 'whatsapp_campaigns';

    protected $fillable = [
        'title', 'template_id', 'variables', 'post_id', 'status', 'scheduled_at', 'created_by', 'approved_by',
        'approved_at', 'started_at', 'finished_at', 'recipients', 'sent', 'delivered', 'failed', 'estimated_cost_eur', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'scheduled_at' => 'datetime',
            'approved_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<WhatsAppTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(WhatsAppTemplate::class, 'template_id');
    }

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return HasMany<WhatsAppDelivery, $this> */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WhatsAppDelivery::class, 'campaign_id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'review'], true);
    }

    /** Vorschau des Nachrichtentextes mit den eingesetzten Werten. */
    public function preview(): string
    {
        return $this->template?->render($this->variables['body'] ?? []) ?? '';
    }

    /** Summen aus den Zustellungen neu berechnen. */
    public function refreshCounts(): void
    {
        $counts = $this->deliveries()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $this->forceFill([
            'sent' => (int) ($counts['sent'] ?? 0) + (int) ($counts['delivered'] ?? 0),
            'delivered' => (int) ($counts['delivered'] ?? 0),
            'failed' => (int) ($counts['failed'] ?? 0),
        ]);
        if ($this->status === 'sending' && ! $this->deliveries()->where('status', 'queued')->exists()) {
            $this->forceFill(['status' => 'sent', 'finished_at' => now()]);
        }
        $this->save();
    }
}
