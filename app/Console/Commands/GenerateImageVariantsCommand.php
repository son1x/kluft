<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\ImageVariants;
use Illuminate\Console\Command;

/**
 * WebP-Varianten für vorhandene Bilder nachträglich erzeugen (F-CMS-04).
 */
class GenerateImageVariantsCommand extends Command
{
    protected $signature = 'media:variants {--all : Auch Bilder neu berechnen, die schon Varianten haben}';

    protected $description = 'Erzeugt WebP-Varianten für Bilder der Mediathek';

    public function handle(ImageVariants $variants): int
    {
        $query = Media::query()->where('mime', 'like', 'image/%')->where('mime', '!=', 'image/svg+xml');
        if (! $this->option('all')) {
            $query->whereNull('variants');
        }

        $count = 0;
        $query->each(function (Media $media) use ($variants, &$count) {
            $variants->generate($media);
            $count++;
        });

        $this->info("Bearbeitet: {$count} Bild(er).");

        return self::SUCCESS;
    }
}
