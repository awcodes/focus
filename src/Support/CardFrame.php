<?php

declare(strict_types=1);

namespace Awcodes\Focus\Support;

use Awcodes\Focus\Contracts\HasDimensions;

/**
 * How a card is laid out and captured. A template with a fixed canvas is laid out at that size and drawn at the
 * device scale factor that makes it cover the card, then the centre is clipped to the card's aspect ratio. Chromium
 * draws at the output resolution, so nothing is resampled afterwards. A template without a canvas is laid out at the
 * card size itself.
 */
final readonly class CardFrame
{
    /**
     * Cropping less than this share of the canvas in either direction is not worth a warning.
     */
    private const CROP_TOLERANCE = 0.01;

    /**
     * @param  array{x: float, y: float, width: float, height: float}|null  $clip  in CSS pixels
     */
    public function __construct(
        public int $viewportWidth,
        public int $viewportHeight,
        public float $deviceScaleFactor,
        public ?array $clip,
        public ?string $warning,
    ) {}

    public static function for(HasDimensions $size, float $scale, ?HasDimensions $canvas): self
    {
        if (! $canvas instanceof HasDimensions) {
            return new self($size->width(), $size->height(), $scale, null, null);
        }

        $outputWidth = $size->width() * $scale;
        $outputHeight = $size->height() * $scale;
        $factor = max($outputWidth / $canvas->width(), $outputHeight / $canvas->height());

        $width = $outputWidth / $factor;
        $height = $outputHeight / $factor;
        $croppedX = $canvas->width() - $width;
        $croppedY = $canvas->height() - $height;

        return new self(
            $canvas->width(),
            $canvas->height(),
            $factor,
            ['x' => $croppedX / 2, 'y' => $croppedY / 2, 'width' => $width, 'height' => $height],
            self::warning($canvas, $size, $croppedX, $croppedY),
        );
    }

    private static function warning(HasDimensions $canvas, HasDimensions $size, float $croppedX, float $croppedY): ?string
    {
        $edges = match (true) {
            $croppedY / $canvas->height() > self::CROP_TOLERANCE => [(int) round($croppedY / 2), 'top and bottom'],
            $croppedX / $canvas->width() > self::CROP_TOLERANCE => [(int) round($croppedX / 2), 'left and right'],
            default => null,
        };

        if ($edges === null) {
            return null;
        }

        return "The {$canvas->width()}x{$canvas->height()} template was cropped to fit {$size->width()}x{$size->height()}: {$edges[0]}px from the {$edges[1]}. Keep important content away from those edges.";
    }
}
