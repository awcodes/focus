<?php

declare(strict_types=1);

namespace Awcodes\Focus\Enums;

use Awcodes\Focus\Contracts\HasDimensions;

/**
 * Sizes of common sharing surfaces, in logical (CSS) pixels, for card sizes and minimum crops.
 */
enum Size: string implements HasDimensions
{
    case OpenGraph = 'open-graph';
    case Twitter = 'twitter';
    case YouTube = 'youtube';
    case GitHubSocial = 'github-social';
    case Filament = 'filament';

    public function width(): int
    {
        return match ($this) {
            self::OpenGraph, self::Twitter => 1200,
            self::YouTube, self::GitHubSocial => 1280,
            self::Filament => 2560,
        };
    }

    public function height(): int
    {
        return match ($this) {
            self::OpenGraph => 630,
            self::Twitter => 675,
            self::YouTube => 720,
            self::GitHubSocial => 640,
            self::Filament => 1440,
        };
    }
}
