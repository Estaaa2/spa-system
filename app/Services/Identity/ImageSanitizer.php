<?php

namespace App\Services\Identity;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Decodes an uploaded image with GD and encodes it again as JPEG.
 *
 * Why: a re-encoded JPEG carries no EXIF block, so GPS location, camera model
 * and timestamps are gone, and anything appended to the original file is
 * dropped. EXIF orientation is applied first (when ext-exif is installed) so
 * phone photos are not saved sideways.
 *
 * Requires ext-gd (already required by phpoffice/phpspreadsheet). ext-exif is
 * optional; without it, orientation is left as decoded.
 */
class ImageSanitizer
{
    /**
     * @param  string  $field  form field name, used for the error message
     * @return string  JPEG bytes
     *
     * @throws ValidationException when the file cannot be read as an image
     */
    public function toJpeg(UploadedFile $file, int $maxSide, string $field): string
    {
        $path = $file->getRealPath();
        $info = $path ? @getimagesize($path) : false;

        if (! $info) {
            $this->fail($field, 'This file could not be read as an image. Use a JPG, PNG or WebP photo.');
        }

        [$width, $height] = $info;
        $maxPixels = (int) config('identity.upload.max_megapixels', 40) * 1_000_000;
        $minSide = (int) config('identity.upload.min_side_px', 300);

        if ($width * $height > $maxPixels) {
            $this->fail($field, 'This image is too large. Use a photo under '.config('identity.upload.max_megapixels', 40).' megapixels.');
        }

        if (min($width, $height) < $minSide) {
            $this->fail($field, "This image is too small to review. Use a photo at least {$minSide} pixels on each side.");
        }

        $source = @imagecreatefromstring((string) file_get_contents($path));

        if ($source === false) {
            $this->fail($field, 'This image format is not supported here. Use a JPG, PNG or WebP photo.');
        }

        $source = $this->applyExifOrientation($source, $path, (int) ($info[2] ?? 0));
        $output = $this->resizeOntoWhite($source, $maxSide);

        ob_start();
        imagejpeg($output, null, (int) config('identity.output.jpeg_quality', 85));
        $bytes = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($output);

        if ($bytes === '') {
            $this->fail($field, 'This image could not be processed. Please try another photo.');
        }

        return $bytes;
    }

    private function applyExifOrientation(\GdImage $image, string $path, int $imageType): \GdImage
    {
        if ($imageType !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = (int) ($exif['Orientation'] ?? 1);

        $rotated = match ($orientation) {
            3       => imagerotate($image, 180, 0),
            6       => imagerotate($image, -90, 0),
            8       => imagerotate($image, 90, 0),
            default => null,
        };

        if ($rotated instanceof \GdImage) {
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }

    /** Scales down to $maxSide (never up) on a white canvas, so PNG transparency is not black. */
    private function resizeOntoWhite(\GdImage $source, int $maxSide): \GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, $maxSide / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $canvas;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
