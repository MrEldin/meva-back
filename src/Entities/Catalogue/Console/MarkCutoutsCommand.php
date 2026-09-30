<?php

namespace Meva\Entities\Catalogue\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Meva\Entities\Catalogue\CatalogueCache;
use Meva\Entities\Catalogue\Transparency;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Find the product images that were uploaded with a see-through background
 * before the desk knew to mark them, mark them as cutouts, and make their
 * transparent sizes. Safe to run again; it only touches what is unmarked.
 */
class MarkCutoutsCommand extends Command
{
    protected $signature = 'meva:cutouts:mark {--dry : Only say what would change}';

    protected $description = 'Mark uploaded product images with a transparent background as cutouts';

    public function handle(): int
    {
        $images = Media::query()
            ->where('collection_name', 'images')
            ->whereIn('mime_type', ['image/png', 'image/webp'])
            ->get();

        $candidates = $images->filter(fn (Media $media): bool => ! $media->getCustomProperty('cutout'));

        // Marked earlier but never given their transparent sizes.
        $marked = $images
            ->filter(fn (Media $media): bool => $media->getCustomProperty('cutout') && ! $media->hasGeneratedConversion('cutout'))
            ->pluck('id')
            ->all();

        foreach ($candidates as $media) {
            $path = $media->getPath();

            if (! is_file($path) || ! Transparency::has($path, $media->mime_type)) {
                continue;
            }

            $this->line(($this->option('dry') ? 'would mark ' : 'marking ').$media->file_name.' (media #'.$media->id.', product #'.$media->model_id.')');

            if ($this->option('dry')) {
                continue;
            }

            $media->setCustomProperty('cutout', true)->save();
            $marked[] = $media->id;
        }

        if ($marked !== [] && ! $this->option('dry')) {
            Artisan::call('media-library:regenerate', ['--ids' => implode(',', $marked), '--force' => true], $this->output);
            CatalogueCache::bump();
        }

        $this->info(count($marked).' cutouts made, '.$candidates->count().' looked at.');

        return self::SUCCESS;
    }
}
