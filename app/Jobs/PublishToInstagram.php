<?php

namespace App\Jobs;

use App\Models\SocialPost;
use App\Services\Instagram\InstagramClient;
use App\Services\Instagram\InstagramException;
use App\Services\Instagram\InstagramImage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Veröffentlicht einen Beitrag auf Instagram (F-IG-02): Bild aufbereiten →
 * Media-Container anlegen → warten, bis Instagram das Bild verarbeitet hat
 * → veröffentlichen → Permalink speichern. Kein automatisches Wiederholen,
 * damit nichts doppelt gepostet wird; im Backend gibt es „Erneut versuchen“.
 */
class PublishToInstagram implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public SocialPost $socialPost) {}

    public function handle(InstagramClient $instagram, InstagramImage $images): void
    {
        $social = $this->socialPost;
        if ($social->status === 'published') {
            return;
        }

        try {
            $media = $social->post->coverMedia ?? throw new InstagramException('Der Beitrag hat kein Titelbild.');
            $social->update(['image_path' => $path = $images->prepare($media), 'status' => 'pending', 'error' => null]);

            $container = $instagram->createImageContainer(Storage::disk('public')->url($path), $social->caption);
            $this->waitUntilReady($instagram, $container);

            $mediaId = $instagram->publish($container);
            $social->update([
                'status' => 'published',
                'external_id' => $mediaId,
                'permalink' => $instagram->permalink($mediaId),
                'posted_at' => now(),
            ]);

            // Instagram hat das Bild übernommen; die Kopie wird nicht mehr gebraucht.
            Storage::disk('public')->delete($path);
        } catch (InstagramException $e) {
            $social->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }
    }

    private function waitUntilReady(InstagramClient $instagram, string $container): void
    {
        for ($i = 0; $i < 10; $i++) {
            $status = $instagram->containerStatus($container);

            if ($status === 'FINISHED') {
                return;
            }
            if (in_array($status, ['ERROR', 'EXPIRED'], true)) {
                throw new InstagramException('Instagram konnte das Bild nicht verarbeiten (Status '.$status.').');
            }

            sleep(min(2 + $i, 5));
        }

        throw new InstagramException('Instagram hat das Bild nicht rechtzeitig verarbeitet. Bitte später erneut versuchen.');
    }
}
