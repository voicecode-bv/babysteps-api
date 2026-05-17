<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Cap width × height van een geüploade afbeelding. Komt overeen met
 * Laravels ingebouwde `dimensions:max_width=...,max_height=...`, maar
 * negeert non-image uploads (video's) zodat hetzelfde `media`-veld
 * gemengd kan blijven. De ingebouwde regel rejected video's omdat
 * getimagesize() faalt op niet-afbeeldingen.
 *
 * Schermt server-side image-processing (GD/Imagick/Intervention) af van
 * decompression-bomb uploads: een 50000×50000 PNG van enkele MB pakt
 * uit naar GB's RAM tijdens thumbnail-generatie.
 */
class MaxImageDimensions implements ValidationRule
{
    public function __construct(
        protected int $maxWidth,
        protected int $maxHeight,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $mimeType = (string) $value->getMimeType();

        if (! str_starts_with($mimeType, 'image/')) {
            return;
        }

        // PHP's getimagesize() ondersteunt geen SVG of HEIC/HEIF; voor die
        // formaten kunnen we hier geen size meten zonder een zwaardere
        // library te laden. Sla over — de serverside conversiestap (HEIC →
        // JPEG via Imagick, SVG → bitmap) heeft zijn eigen resource-limits.
        if (in_array($mimeType, ['image/svg+xml', 'image/svg', 'image/heic', 'image/heif'], true)) {
            return;
        }

        $size = @getimagesize($value->getRealPath());

        if ($size === false) {
            $fail(__('validation.dimensions'));

            return;
        }

        [$width, $height] = $size;

        if ($width > $this->maxWidth || $height > $this->maxHeight) {
            $fail(__('validation.dimensions'));
        }
    }
}
