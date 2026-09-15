<?php

namespace Meva\Entities\Catalogue;

use Lunar\Base\StandardMediaDefinitions;
use Spatie\Image\Enums\BorderType;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Lunar's own picture sizes, plus one for sharing.
 *
 * Lunar keeps every conversion in the format it was given, so a product
 * photographed as a PNG stays a PNG -- and at 800 pixels square that is over
 * six hundred kilobytes for some of this catalogue. Messengers give up on an
 * image that heavy and draw the card without a picture, which is the whole
 * reason the card exists.
 *
 * So there is one more size, meant only for that: a JPEG, whatever the
 * original was, at a quality that holds up in a preview and weighs a tenth as
 * much.
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
        });
    }
}
