<?php

declare(strict_types=1);

namespace Awcodes\Focus;

use Awcodes\Focus\Contracts\HasDimensions;
use Awcodes\Focus\Enums\CaptureMode;
use Awcodes\Focus\Enums\Theme;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * One screenshot in one theme, with every setting resolved screenshot → suite → package default.
 */
final readonly class Capture
{
    /**
     * @param  list<string>  $masks
     */
    public function __construct(
        public Screenshot $screenshot,
        public Theme $theme,
        public CaptureMode $mode,
        public HasDimensions $viewport,
        public int $padding,
        public ?HasDimensions $minSize,
        public float $scale,
        public bool $animations,
        public string $locale,
        public string $timezone,
        public ?DateTimeImmutable $frozenTime,
        public array $masks,
        public string $maskColor,
        public string $path,
    ) {}

    public static function resolve(ScreenshotSuite $suite, Screenshot $screenshot, Theme $theme, string $outputDirectory): self
    {
        $timezone = $screenshot->getTimezone() ?? $suite->getTimezone() ?? Defaults::TIMEZONE;

        return new self(
            screenshot: $screenshot,
            theme: $theme,
            mode: $screenshot->getCaptureMode(),
            viewport: $screenshot->getViewportSize() ?? $suite->getViewportSize() ?? Defaults::VIEWPORT,
            padding: $screenshot->getPadding() ?? $suite->getPadding() ?? Defaults::PADDING,
            minSize: $screenshot->getMinSize() ?? $suite->getMinSize(),
            scale: (float) ($screenshot->getScale() ?? $suite->getScale() ?? Defaults::SCALE),
            animations: $screenshot->getAnimations() ?? $suite->getAnimations() ?? Defaults::ANIMATIONS,
            locale: $screenshot->getLocale() ?? $suite->getLocale() ?? Defaults::LOCALE,
            timezone: $timezone,
            frozenTime: self::resolveFrozenTime(
                $screenshot->getFrozenTime() ?? $suite->getFrozenTime() ?? Defaults::FROZEN_TIME,
                $timezone,
            ),
            masks: array_values(array_unique([...$suite->getMasks(), ...$screenshot->getMasks()])),
            maskColor: $screenshot->getMaskColor() ?? $suite->getMaskColor() ?? Defaults::MASK_COLOR,
            path: $outputDirectory . DIRECTORY_SEPARATOR . self::filename($screenshot->getName(), $theme),
        );
    }

    /**
     * `{name}-{theme}.png`. The `{name}-{viewport}-{theme}.png` form is reserved for multi-viewport expansion.
     */
    public static function filename(string $name, Theme $theme): string
    {
        return "{$name}-{$theme->value}.png";
    }

    public function name(): string
    {
        return $this->screenshot->getName();
    }

    public function label(): string
    {
        return "{$this->name()} ({$this->theme->value})";
    }

    private static function resolveFrozenTime(string | DateTimeInterface | false $time, string $timezone): ?DateTimeImmutable
    {
        if ($time === false) {
            return null;
        }

        if ($time instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($time);
        }

        return new DateTimeImmutable($time, new DateTimeZone($timezone));
    }
}
