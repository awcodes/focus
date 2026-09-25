<?php

declare(strict_types=1);

namespace Awcodes\Focus;

use Awcodes\Focus\Enums\Theme;
use Awcodes\Focus\Enums\Viewport;

/**
 * Package defaults, used when neither a screenshot nor its suite configures a setting.
 */
final class Defaults
{
    public const OUTPUT_PATH = 'docs/assets';

    public const THEMES = [Theme::Light, Theme::Dark];

    public const VIEWPORT = Viewport::Desktop;

    public const SCALE = 2;

    public const PADDING = 32;

    public const ANIMATIONS = false;

    public const LOCALE = 'en-US';

    public const TIMEZONE = 'UTC';

    public const FROZEN_TIME = '2026-01-01 09:00:00';

    public const MASK_COLOR = '#FF00FF';
}
