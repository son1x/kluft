<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zustellung einer Aussendung an eine Person – kurzlebig (30 Tage), nur
 * für Statuszuordnung und Wiederholung. Lesebestätigungen werden ignoriert.
 *
 * @property int $campaign_id
 * @property int|null $subscriber_id
 * @property string|null $wamid
 * @property string $status
 * @property int|null $error_code
 */
class WhatsAppDelivery extends Model
{
    protected $table = 'whatsapp_deliveries';

    protected $fillable = ['campaign_id', 'subscriber_id', 'wamid', 'status', 'error_code'];

    /** @return BelongsTo<WhatsAppCampaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(WhatsAppCampaign::class, 'campaign_id');
    }

    /** @return BelongsTo<WhatsAppSubscriber, $this> */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(WhatsAppSubscriber::class, 'subscriber_id');
    }
}
