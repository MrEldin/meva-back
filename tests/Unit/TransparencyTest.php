<?php

use Meva\Entities\Catalogue\Transparency;

// A small PNG drawn with GD: see-through or painted, as the test needs.
function pngWithBackground(bool $transparent): string
{
    $image = imagecreatetruecolor(40, 40);
    imagesavealpha($image, true);
    imagealphablending($image, false);

    $background = $transparent ? imagecolorallocatealpha($image, 0, 0, 0, 127) : imagecolorallocate($image, 255, 255, 255);
    imagefill($image, 0, 0, $background);

    // The product in the middle, opaque either way.
    imagefilledrectangle($image, 10, 10, 30, 30, imagecolorallocate($image, 120, 60, 60));

    $path = tempnam(sys_get_temp_dir(), 'meva').'.png';
    imagepng($image, $path);

    return $path;
}

it('sees a transparent background as a cutout', function () {
    expect(Transparency::has(pngWithBackground(true), 'image/png'))->toBeTrue();
});

it('sees a painted background as a photograph', function () {
    expect(Transparency::has(pngWithBackground(false), 'image/png'))->toBeFalse();
});

it('never calls a jpeg a cutout', function () {
    expect(Transparency::has(pngWithBackground(true), 'image/jpeg'))->toBeFalse();
});
