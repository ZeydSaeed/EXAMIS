<?php

namespace App\Services\Documents;

/**
 * Re-encodes an uploaded photo as a fresh JPEG: upright, at most MAX_SIDE
 * pixels on its longer side, without EXIF or anything appended to the image.
 * Only the decoded pixels survive, which removes metadata (including GPS)
 * and payloads hidden in the file, and keeps stored photos small.
 */
class PhotoNormalizer
{
    /** The printed form uses 420×540 px, so this leaves room to spare. */
    public const MAX_SIDE = 1200;

    private const QUALITY = 85;

    /**
     * Decoding costs about 5 bytes a pixel, so 16 MP stays near 80 MB, well
     * inside memory_limit. A larger photo is stored as uploaded after the
     * scanner's checks; the browser shrinks photos first, so this is rare.
     */
    private const MAX_PIXELS = 16_000_000;

    /**
     * The normalized JPEG, or null when the bytes are not an image GD can
     * safely decode; the caller then keeps the original for the scanner to
     * judge.
     */
    public function normalize(string $contents): ?string
    {
        $size = @getimagesizefromstring($contents);

        // Check the declared size before decoding, so a small file that
        // claims huge dimensions cannot exhaust memory.
        if ($size === false || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > self::MAX_PIXELS) {
            return null;
        }

        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            return null;
        }

        $source = $this->upright($source, $contents, $size[2]);
        [$width, $height] = [imagesx($source), imagesy($source)];
        $scale = min(1, self::MAX_SIDE / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        // A white canvas: transparent PNG areas print white, not black.
        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagefill($target, 0, 0, (int) imagecolorallocate($target, 255, 255, 255));
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagejpeg($target, null, self::QUALITY);

        return (string) ob_get_clean();
    }

    /**
     * Phones store the camera's orientation in EXIF instead of rotating the
     * pixels. Re-encoding drops EXIF, so apply the rotation first.
     */
    private function upright(\GdImage $image, string $contents, int $type): \GdImage
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($contents));
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated === false ? $image : $rotated;
    }
}
