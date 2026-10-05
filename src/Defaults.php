<?php

declare(strict_types=1);

namespace Awcodes\Focus;

use Awcodes\Focus\Enums\Size;
use Awcodes\Focus\Enums\Theme;
use Awcodes\Focus\Enums\Viewport;

/**
 * Package defaults, used when neither a screenshot nor its suite configures a setting.
 *
 * @internal
 */
final class Defaults
{
    public const string OUTPUT_PATH = 'docs/assets';

    public const array THEMES = [Theme::Light, Theme::Dark];

    public const VIEWPORT = Viewport::Desktop;

    public const int SCALE = 2;

    public const int PADDING = 32;

    public const bool ANIMATIONS = false;

    public const string LOCALE = 'en-US';

    public const string TIMEZONE = 'UTC';

    public const string FROZEN_TIME = '2026-01-01 09:00:00';

    public const string MASK_COLOR = '#FF00FF';

    public const int TIMEOUT = 15_000;

    public const bool KEEP_INTERACTION_STATE = false;

    public const string CARD_OUTPUT_PATH = 'art';

    public const string CARD_TEMPLATE = 'default';

    /**
     * Sharing surfaces show one image regardless of color scheme, so cards render dark only unless asked.
     */
    public const array CARD_THEMES = [Theme::Dark];

    public const array CARD_SIZES = [Size::OpenGraph];
}
