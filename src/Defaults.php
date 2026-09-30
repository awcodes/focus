<?php

declare(strict_types=1);

namespace Awcodes\Focus;

use Awcodes\Focus\Enums\Size;
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

    public const TIMEOUT = 15_000;

    public const KEEP_INTERACTION_STATE = false;

    public const CARD_OUTPUT_PATH = 'art';

    public const CARD_TEMPLATE = 'default';

    /**
     * Sharing surfaces show one image regardless of color scheme, so cards render dark only unless asked.
     */
    public const CARD_THEMES = [Theme::Dark];

    public const CARD_SIZES = [Size::OpenGraph];
}
