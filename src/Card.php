<?php

declare(strict_types=1);

namespace Awcodes\Focus;

use Awcodes\Focus\Contracts\HasDimensions;
use Awcodes\Focus\Enums\Theme;
use Awcodes\Focus\Support\Dimensions;
use InvalidArgumentException;
use Stringable;

/**
 * A share image: a page from the suite's template directory, filled with package metadata and screenshots.
 */
class Card
{
    public const TEMPLATE_PATTERN = '#^[a-z0-9]+(-[a-z0-9]+)*(/[a-z0-9]+(-[a-z0-9]+)*)*$#';

    /**
     * Kebab-case starting with a letter, so a list passed to `with()` is rejected rather than keyed `0`, `1`, ….
     */
    public const KEY_PATTERN = '/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/';

    /**
     * Keys Focus fills itself. `screenshot` is reserved so custom keys cannot be confused with `screenshot.N`.
     */
    public const RESERVED_KEYS = ['title', 'description', 'package', 'screenshot'];

    protected ?string $template = null;

    /** @var list<string> */
    protected array $screenshots = [];

    /** @var list<HasDimensions>|null */
    protected ?array $sizes = null;

    /** @var list<Theme>|null */
    protected ?array $themes = null;

    protected ?float $scale = null;

    protected ?string $title = null;

    protected ?string $description = null;

    /** @var array<string, string> */
    protected array $values = [];

    final public function __construct(
        protected string $name,
    ) {}

    public static function make(string $name): static
    {
        return new static($name);
    }

    /**
     * A page in the template directory: `{name}.html` or `{name}/index.html`.
     */
    public function template(string $name): static
    {
        if (preg_match(self::TEMPLATE_PATTERN, $name) !== 1) {
            throw new InvalidArgumentException("template() expects a lowercase kebab-case name without an extension (e.g. [two-up] or [social/two-up]), [{$name}] given.");
        }

        $this->template = $name;

        return $this;
    }

    /**
     * Screenshots from the same suite, in slot order: the first fills `screenshot.1`.
     *
     * @param  array<string>  $names
     */
    public function screenshots(array $names): static
    {
        foreach ($names as $name) {
            if (! is_string($name) || trim($name) === '') {
                throw new InvalidArgumentException('screenshots() only accepts screenshot names.');
            }
        }

        $this->screenshots = array_values($names);

        return $this;
    }

    /**
     * @param  array<HasDimensions|array{int, int}>  $sizes
     */
    public function sizes(array $sizes): static
    {
        if ($sizes === []) {
            throw new InvalidArgumentException('sizes() requires at least one size.');
        }

        $normalized = [];

        foreach ($sizes as $size) {
            $size = $this->normalizeSize($size);
            $key = "{$size->width()}x{$size->height()}";

            if (isset($normalized[$key])) {
                throw new InvalidArgumentException("sizes() lists [{$key}] more than once.");
            }

            $normalized[$key] = $size;
        }

        $this->sizes = array_values($normalized);

        return $this;
    }

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

    public function scale(int | float $scale): static
    {
        if ($scale <= 0) {
            throw new InvalidArgumentException("scale() must be greater than zero, [{$scale}] given.");
        }

        $this->scale = (float) $scale;

        return $this;
    }

    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Custom values for `data-focus` keys in the template.
     *
     * @param  array<string, string|int|float|Stringable>  $values
     */
    public function with(array $values): static
    {
        foreach ($values as $key => $value) {
            $key = (string) $key;

            if (preg_match(self::KEY_PATTERN, $key) !== 1) {
                throw new InvalidArgumentException("with() keys must be lowercase kebab-case, [{$key}] given.");
            }

            if (in_array($key, self::RESERVED_KEYS, true)) {
                $hint = match ($key) {
                    'title', 'description' => " Use {$key}() instead.",
                    'package' => ' It comes from composer.json.',
                    default => ' Pass screenshots with screenshots().',
                };

                throw new InvalidArgumentException("with() cannot set [{$key}], which Focus fills itself.{$hint}");
            }

            if (! is_string($value) && ! is_int($value) && ! is_float($value) && ! $value instanceof Stringable) {
                throw new InvalidArgumentException("with() value for [{$key}] must be a string, number, or Stringable, " . get_debug_type($value) . ' given.');
            }

            $this->values[$key] = (string) $value;
        }

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    /**
     * @return list<string>
     */
    public function getScreenshots(): array
    {
        return $this->screenshots;
    }

    /**
     * @return list<HasDimensions>|null
     */
    public function getSizes(): ?array
    {
        return $this->sizes;
    }

    /**
     * @return list<Theme>|null
     */
    public function getThemes(): ?array
    {
        return $this->themes;
    }

    public function getScale(): ?float
    {
        return $this->scale;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return array<string, string>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    private function normalizeSize(mixed $size): HasDimensions
    {
        if ($size instanceof HasDimensions) {
            return Dimensions::from($size, null, 'sizes');
        }

        if (is_array($size) && array_is_list($size) && count($size) === 2 && is_int($size[0]) && is_int($size[1])) {
            return Dimensions::from($size[0], $size[1], 'sizes');
        }

        throw new InvalidArgumentException('sizes() accepts presets such as Size::OpenGraph or [width, height] pairs.');
    }
}
