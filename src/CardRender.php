<?php

declare(strict_types=1);

namespace Awcodes\Focus;

use Awcodes\Focus\Contracts\HasDimensions;
use Awcodes\Focus\Enums\Theme;
use Awcodes\Focus\Exceptions\FocusException;
use Awcodes\Focus\Support\PackageMetadata;
use BackedEnum;
use DateTimeImmutable;

/**
 * One card at one size in one theme, with every setting and value resolved.
 */
final readonly class CardRender
{
    /**
     * @param  array<string, string>  $values  text values by `data-focus` key
     * @param  array<int, array<string, string>>  $screenshots  slot (from 1) → theme value → screenshot path
     */
    public function __construct(
        public Card $card,
        public string $template,
        public Theme $theme,
        public HasDimensions $size,
        public float $scale,
        public string $locale,
        public string $timezone,
        public ?DateTimeImmutable $frozenTime,
        public array $values,
        public array $screenshots,
        public string $path,
    ) {}

    public static function resolve(
        ScreenshotSuite $suite,
        Card $card,
        Theme $theme,
        HasDimensions $size,
        string $outputDirectory,
        string $screenshotDirectory,
        PackageMetadata $metadata,
    ): self {
        $timezone = $suite->getTimezone() ?? Defaults::TIMEZONE;

        return new self(
            card: $card,
            template: $card->getTemplate() ?? Defaults::CARD_TEMPLATE,
            theme: $theme,
            size: $size,
            scale: $card->getScale() ?? Defaults::SCALE,
            locale: $suite->getLocale() ?? Defaults::LOCALE,
            timezone: $timezone,
            frozenTime: Capture::resolveFrozenTime($suite->getFrozenTime() ?? Defaults::FROZEN_TIME, $timezone),
            values: self::values($card, $metadata),
            screenshots: self::screenshots($suite, $card, $screenshotDirectory),
            path: $outputDirectory . DIRECTORY_SEPARATOR . self::filename($card->getName(), $size, $theme),
        );
    }

    /**
     * `{name}-{size}-{theme}.png`. The theme segment is always present so adding a theme never renames existing files.
     */
    public static function filename(string $name, HasDimensions $size, Theme $theme): string
    {
        return "{$name}-" . self::sizeSegment($size) . "-{$theme->value}.png";
    }

    /**
     * A preset's backed value (`open-graph`), or `{width}x{height}` for custom dimensions.
     */
    public static function sizeSegment(HasDimensions $size): string
    {
        return $size instanceof BackedEnum ? (string) $size->value : "{$size->width()}x{$size->height()}";
    }

    public function name(): string
    {
        return $this->card->getName();
    }

    public function label(): string
    {
        return "{$this->name()} (" . self::sizeSegment($this->size) . ", {$this->theme->value})";
    }

    /**
     * @return array<string, string>
     */
    private static function values(Card $card, PackageMetadata $metadata): array
    {
        $title = $card->getTitle() ?? $metadata->title()
            ?? throw new FocusException("Card [{$card->getName()}] needs a title: composer.json has no package name. Add ->title().");

        // `install` is a default that with() may replace; a dev tool, for one, is installed with --dev.
        return [
            'install' => $metadata->name === null ? '' : "composer require {$metadata->name}",
            ...$card->getValues(),
            'title' => $title,
            'description' => $card->getDescription() ?? $metadata->description ?? '',
            'package' => $metadata->name ?? '',
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private static function screenshots(ScreenshotSuite $suite, Card $card, string $screenshotDirectory): array
    {
        $slots = [];

        foreach ($card->getScreenshots() as $index => $name) {
            $screenshot = $suite->findScreenshot($name)
                ?? throw new FocusException("Card [{$card->getName()}] uses unknown screenshot [{$name}].");

            foreach ($suite->themesFor($screenshot) as $theme) {
                $slots[$index + 1][$theme->value] = $screenshotDirectory . DIRECTORY_SEPARATOR . Capture::filename($name, $theme);
            }
        }

        return $slots;
    }
}
