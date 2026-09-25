<?php

declare(strict_types=1);

namespace Awcodes\Focus\Support;

use Awcodes\Focus\Contracts\HasDimensions;
use InvalidArgumentException;
use Stringable;

final readonly class Dimensions implements HasDimensions, Stringable
{
    public function __construct(
        private int $width,
        private int $height,
    ) {
        if ($width < 1 || $height < 1) {
            throw new InvalidArgumentException("Dimensions must be positive integers, [{$width}x{$height}] given.");
        }
    }

    public function __toString(): string
    {
        return "{$this->width}x{$this->height}";
    }

    /**
     * Normalize the `int | HasDimensions` argument pair accepted by `minSize()` and `viewportSize()`.
     */
    public static function from(int | HasDimensions $width, ?int $height, string $method): HasDimensions
    {
        if ($width instanceof HasDimensions) {
            if ($height !== null) {
                throw new InvalidArgumentException("{$method}() does not accept a height when given a preset.");
            }

            if ($width->width() < 1 || $width->height() < 1) {
                throw new InvalidArgumentException("{$method}() preset dimensions must be positive integers, [{$width->width()}x{$width->height()}] given.");
            }

            return $width;
        }

        if ($height === null) {
            throw new InvalidArgumentException("{$method}() requires a height when given an integer width.");
        }

        try {
            return new self($width, $height);
        } catch (InvalidArgumentException) {
            throw new InvalidArgumentException("{$method}() dimensions must be positive integers, [{$width}x{$height}] given.");
        }
    }

    public function width(): int
    {
        return $this->width;
    }

    public function height(): int
    {
        return $this->height;
    }
}
