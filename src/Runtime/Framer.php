<?php

declare(strict_types=1);

namespace Awcodes\Focus\Runtime;

use Awcodes\Focus\Contracts\HasDimensions;

/**
 * Computes the crop around a focus subject. All values are CSS pixels in document coordinates.
 */
final class Framer
{
    /**
     * `clamped` is true when the subject itself or the requested minimum size does not fit in the document.
     * Padding running past a document edge is expected, especially at narrow viewports, and is not reported.
     *
     * @param  array{x: float, y: float, width: float, height: float}  $subject
     * @return array{clip: array{x: int, y: int, width: int, height: int}, clamped: bool}
     */
    public static function frame(array $subject, int $padding, ?HasDimensions $minSize, float $documentWidth, float $documentHeight): array
    {
        [$x, $width, $clampedX] = self::axis($subject['x'], $subject['width'], $padding, $minSize?->width() ?? 0, $documentWidth);
        [$y, $height, $clampedY] = self::axis($subject['y'], $subject['height'], $padding, $minSize?->height() ?? 0, $documentHeight);

        return [
            'clip' => ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height],
            'clamped' => $clampedX || $clampedY,
        ];
    }

    /**
     * Expand one axis by padding, grow it (centered) to the minimum, then shift it inside the document,
     * shrinking only when the region is larger than the document itself.
     *
     * @return array{int, int, bool}
     */
    private static function axis(float $start, float $length, int $padding, int $minimum, float $documentLength): array
    {
        $documentLength = (int) floor($documentLength);
        $overflows = ceil($length) > $documentLength || $minimum > $documentLength;

        $start -= $padding;
        $length += $padding * 2;

        if ($length < $minimum) {
            $start -= ($minimum - $length) / 2;
            $length = $minimum;
        }

        // Round the length up and the position to nearest, so a minimum size is produced exactly.
        $length = (int) ceil($length);
        $start = (int) round($start);

        if ($length > $documentLength) {
            return [0, max(1, $documentLength), $overflows];
        }

        $start = max(0, min($start, $documentLength - $length));

        return [$start, $length, $overflows];
    }
}
