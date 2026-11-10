<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Foto zu einer Veranstaltung: „eingereicht“ (privat, nur im Backend),
 * „freigegeben“ (als Medium öffentlich in der Galerie) oder
 * „ausgeblendet“. Freigeben darf nur eine zweite Person (Vier-Augen).
 */
class EventFoto extends Model
{
    protected $table = 'event_fotos';

    public const DISK = 'local';

    public const STATUS = ['eingereicht' => 'Wartet auf Freigabe', 'freigegeben' => 'Freigegeben', 'ausgeblendet' => 'Ausgeblendet'];

    public const FARBEN = ['eingereicht' => 'warning', 'freigegeben' => 'success', 'ausgeblendet' => 'gray'];

    protected $fillable = ['event_id', 'path', 'media_id', 'alt_text', 'credit', 'status', 'sort', 'hochgeladen_von', 'freigegeben_von', 'freigegeben_am'];

    protected function casts(): array
    {
        return ['freigegeben_am' => 'datetime', 'sort' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleted(function (EventFoto $foto) {
            if ($foto->path) {
                Storage::disk(self::DISK)->delete($foto->path);
            }
            $foto->media?->delete();
        });
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function hochgeladenVon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hochgeladen_von');
    }

    /** Vier-Augen: nicht die Person, die hochgeladen hat. */
    public function darfFreigeben(User $user): bool
    {
        return $this->status !== 'freigegeben' && $this->hochgeladen_von !== $user->id && $user->can('manageParticipants', $this->event);
    }

    /** Vorschau im Backend: privat oder schon öffentlich. */
    public function vorschauUrl(): string
    {
        return $this->media ? $this->media->url() : route('event-fotos.vorschau', $this);
    }

    /**
     * Freigeben: privates Bild als Medium öffentlich ablegen. Ausgeblendete
     * kommen einfach zurück.
     */
    public function freigeben(User $user): void
    {
        if ($this->media_id === null) {
            if (! $this->path || ! Storage::disk(self::DISK)->exists($this->path)) {
                throw new RuntimeException('Die Bilddatei fehlt.');
            }
            $ziel = 'media/event-fotos/'.Str::random(32).'.'.strtolower(pathinfo($this->path, PATHINFO_EXTENSION) ?: 'jpg');
            Storage::disk('public')->put($ziel, Storage::disk(self::DISK)->get($this->path));
            [$breite, $hoehe] = @getimagesize(Storage::disk('public')->path($ziel)) ?: [null, null];
            $media = Media::query()->create([
                'path' => $ziel,
                'mime' => Storage::disk('public')->mimeType($ziel) ?: 'image/jpeg',
                'size' => Storage::disk('public')->size($ziel),
                'alt_text' => $this->alt_text ?: 'Foto: '.$this->event->title,
                'credit' => $this->credit,
                'rights_confirmed' => true,
                'uploaded_by' => $this->hochgeladen_von,
                'width' => $breite,
                'height' => $hoehe,
            ]);
            Storage::disk(self::DISK)->delete($this->path);
            $this->path = null;
            $this->media_id = $media->id;
        }
        $this->status = 'freigegeben';
        $this->freigegeben_von = $user->id;
        $this->freigegeben_am = now();
        $this->save();
    }
}
