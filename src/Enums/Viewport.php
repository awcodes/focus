<?php

declare(strict_types=1);

namespace Awcodes\Focus\Enums;

use Awcodes\Focus\Contracts\HasDimensions;

/**
 * Browser viewport presets, in logical (CSS) pixels.
 */
enum Viewport: string implements HasDimensions
{
    case Desktop = 'desktop';
    case Tablet = 'tablet';
    case Mobile = 'mobile';

    public function width(): int
    {
        return match ($this) {
            self::Desktop => 1440,
            self::Tablet => 768,
            self::Mobile => 390,
        };
    }

    public function height(): int
    {
        return match ($this) {
            self::Desktop => 1000,
            self::Tablet => 1024,
            self::Mobile => 844,
        };
    }
}
