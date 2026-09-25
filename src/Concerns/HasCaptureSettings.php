<?php

declare(strict_types=1);

namespace Awcodes\Focus\Concerns;

use Awcodes\Focus\Contracts\HasDimensions;
use Awcodes\Focus\Enums\Theme;
use Awcodes\Focus\Support\Dimensions;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use InvalidArgumentException;

/**
 * Settings that resolve screenshot → suite → package default. A null value means "not set here".
 */
trait HasCaptureSettings
{
    /** @var list<Theme>|null */
    protected ?array $themes = null;

    protected ?HasDimensions $viewportSize = null;

    protected ?int $padding = null;

    protected ?HasDimensions $minSize = null;

    protected ?float $scale = null;

    protected ?bool $animations = null;

    protected ?string $locale = null;

    protected ?string $timezone = null;

    protected string | DateTimeInterface | false | null $frozenTime = null;

    protected ?string $maskColor = null;

    protected ?bool $keepInteractionState = null;

    /**
     * @param  array<Theme>  $themes
     */
    public function themes(array $themes): static
    {
        if ($themes === []) {
            throw new InvalidArgumentException('themes() requires at least one theme.');
        }

        foreach ($themes as $theme) {
            if (! $theme instanceof Theme) {
                throw new InvalidArgumentException('themes() only accepts ' . Theme::class . ' cases.');
            }
        }

        $this->themes = array_values(array_unique($themes, SORT_REGULAR));

        return $this;
    }

    public function viewportSize(int | HasDimensions $width, ?int $height = null): static
    {
        $this->viewportSize = Dimensions::from($width, $height, 'viewportSize');

        return $this;
    }

    public function padding(int $padding): static
    {
        if ($padding < 0) {
            throw new InvalidArgumentException("padding() must be zero or greater, [{$padding}] given.");
        }

        $this->padding = $padding;

        return $this;
    }

    public function minSize(int | HasDimensions $width, ?int $height = null): static
    {
        $this->minSize = Dimensions::from($width, $height, 'minSize');

        return $this;
    }

    public function scale(int | float $scale): static
    {
        if ($scale <= 0) {
            throw new InvalidArgumentException("scale() must be greater than zero, [{$scale}] given.");
        }

        $this->scale = (float) $scale;

        return $this;
    }

    public function allowAnimations(bool $condition = true): static
    {
        $this->animations = $condition;

        return $this;
    }

    public function locale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function timezone(string $timezone): static
    {
        try {
            new DateTimeZone($timezone);
        } catch (Exception) {
            throw new InvalidArgumentException("timezone() received an unknown timezone [{$timezone}].");
        }

        $this->timezone = $timezone;

        return $this;
    }

    /**
     * Freeze the browser clock. Strings are interpreted in the resolved timezone. Pass `false` to use the real clock.
     */
    public function freezeTime(string | DateTimeInterface | false $time): static
    {
        if (is_string($time)) {
            try {
                new DateTimeImmutable($time);
            } catch (Exception) {
                throw new InvalidArgumentException("freezeTime() could not parse [{$time}].");
            }
        }

        $this->frozenTime = $time;

        return $this;
    }

    /**
     * Keep the pointer position and keyboard focus left by interactions, instead of clearing them before capture.
     */
    public function keepInteractionState(bool $condition = true): static
    {
        $this->keepInteractionState = $condition;

        return $this;
    }

    public function maskColor(string $color): static
    {
        $this->maskColor = $color;

        return $this;
    }

    /**
     * @return list<Theme>|null
     */
    public function getThemes(): ?array
    {
        return $this->themes;
    }

    public function getViewportSize(): ?HasDimensions
    {
        return $this->viewportSize;
    }

    public function getPadding(): ?int
    {
        return $this->padding;
    }

    public function getMinSize(): ?HasDimensions
    {
        return $this->minSize;
    }

    public function getScale(): ?float
    {
        return $this->scale;
    }

    public function getAnimations(): ?bool
    {
        return $this->animations;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function getFrozenTime(): string | DateTimeInterface | false | null
    {
        return $this->frozenTime;
    }

    public function getKeepInteractionState(): ?bool
    {
        return $this->keepInteractionState;
    }

    public function getMaskColor(): ?string
    {
        return $this->maskColor;
    }
}
