<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Mitgliedsbeitrag eines Mitglieds für ein Halbjahr: Soll und Zahlung.
 *
 * @property int $id
 * @property int $beitrags_mitglied_id
 * @property string $halbjahr
 * @property ?string $beitragsart
 * @property int $soll_cent
 * @property ?int $bezahlt_cent
 * @property ?Carbon $bezahlt_am
 * @property ?string $zahlart
 * @property ?string $notiz
 */
class Beitrag extends Model
{
    protected $table = 'beitraege';

    public const ZAHLARTEN = ['ueberweisung' => 'Überweisung', 'lastschrift' => 'Lastschrift', 'bar' => 'bar', 'erlassen' => 'erlassen'];

    protected $fillable = ['beitrags_mitglied_id', 'halbjahr', 'beitragsart', 'soll_cent', 'bezahlt_cent', 'bezahlt_am', 'zahlart', 'notiz', 'erfasst_von'];

    protected function casts(): array
    {
        return ['soll_cent' => 'integer', 'bezahlt_cent' => 'integer', 'bezahlt_am' => 'date'];
    }

    /**
     * @return BelongsTo<BeitragsMitglied, $this>
     */
    public function mitglied(): BelongsTo
    {
        return $this->belongsTo(BeitragsMitglied::class, 'beitrags_mitglied_id');
    }

    /** offen, teilweise oder bezahlt (erlassen zählt als bezahlt). */
    public function status(): string
    {
        if ($this->zahlart === 'erlassen' || ($this->bezahlt_am && (int) $this->bezahlt_cent >= $this->soll_cent)) {
            return 'bezahlt';
        }

        return (int) $this->bezahlt_cent > 0 ? 'teilweise' : 'offen';
    }

    /**
     * @param  Builder<Beitrag>  $query
     * @return Builder<Beitrag>
     */
    public function scopeOffen(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('zahlart')->orWhere('zahlart', '!=', 'erlassen'))
            ->where(fn (Builder $q) => $q->whereNull('bezahlt_am')->orWhereColumn('bezahlt_cent', '<', 'soll_cent')->orWhereNull('bezahlt_cent'));
    }
}
