<?php

namespace Meva\Entities\Catalogue;

use Lunar\Base\StandardMediaDefinitions;
use Spatie\Image\Enums\BorderType;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Lunar's own picture sizes, plus two of ours.
 *
 * Lunar keeps every conversion in the format it was given, so a product
 * photographed as a PNG stays a PNG -- and at 800 pixels square that is over
 * six hundred kilobytes for some of this catalogue. Messengers give up on an
 * image that heavy and draw the card without a picture, which is the whole
 * reason the card exists. Hence `share`: a JPEG, whatever the original was,
 * at a quality that holds up in a preview and weighs a tenth as much.
 *
 * The other addition is for the cutouts -- the photographs with the
 * background removed. Every conversion Lunar registers fills the frame and
 * paints it white, which is exactly what a cutout must not have: the
 * storefront stands them on tinted circles and open white, and a white square
 * behind the bottle would show. So a cutout gets its own two sizes, fitted
 * inside the frame, written as WebP, and left transparent.
 */
class MediaDefinitions extends StandardMediaDefinitions
{
    protected function registerCollectionConversions(MediaCollection $collection, HasMedia $model): void
    {
        parent::registerCollectionConversions($collection, $model);

        $collection->registerMediaConversions(function (Media $media) use ($model): void {
            $model->addMediaConversion('share')
                ->fit(Fit::Contain, 1200, 1200)
                ->border(0, BorderType::Overlay, color: '#FFF')
                ->background('#FFF')
                ->format('jpg')
                ->quality(80);

            if (! $media->getCustomProperty('cutout')) {
                return;
            }

            // Transparent, and in the two sizes the storefront asks for: the
            // product page shows one large, a grid shows a dozen small.
            $model->addMediaConversion('cutout')
                ->fit(Fit::Contain, 900, 900)
                ->format('webp')
                ->quality(82);

            $model->addMediaConversion('cutout-sm')
                ->fit(Fit::Contain, 480, 480)
                ->format('webp')
                ->quality(80);
        });
    }
}
