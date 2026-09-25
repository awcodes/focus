<?php

declare(strict_types=1);

namespace Awcodes\Focus;

use Awcodes\Focus\Concerns\HasCaptureSettings;
use Awcodes\Focus\Enums\Theme;
use Awcodes\Focus\Exceptions\FocusException;
use Closure;
use InvalidArgumentException;
use Playwright\Page\PageInterface;

class ScreenshotSuite
{
    use HasCaptureSettings;

    /** @var list<Screenshot> */
    protected array $screenshots = [];

    protected ?string $outputPath = null;

    protected ?string $baseUrl = null;

    /** @var list<string> */
    protected array $masks = [];

    /** @var list<Closure(PageInterface): mixed> */
    protected array $beforeEach = [];

    final public function __construct() {}

    public static function make(): static
    {
        return new static;
    }

    /**
     * @param  array<Screenshot>  $screenshots
     */
    public function screenshots(array $screenshots): static
    {
        foreach ($screenshots as $screenshot) {
            if (! $screenshot instanceof Screenshot) {
                throw new InvalidArgumentException('screenshots() only accepts ' . Screenshot::class . ' instances.');
            }

            $this->screenshots[] = $screenshot;
        }

        return $this;
    }

    /**
     * Where captures are written, relative to the repository root unless absolute.
     */
    public function outputPath(string $path): static
    {
        $this->outputPath = $path;

        return $this;
    }

    /**
     * Connect to an already-running application instead of starting Workbench.
     */
    public function baseUrl(string $url): static
    {
        $this->baseUrl = $url;

        return $this;
    }

    /**
     * Mask matching elements in every screenshot, in addition to each screenshot's own masks.
     */
    public function mask(string ...$selectors): static
    {
        array_push($this->masks, ...$selectors);

        return $this;
    }

    /**
     * Run before every screenshot, after authentication and before the screenshot's own `before()`.
     *
     * @param  Closure(PageInterface): mixed  $callback
     */
    public function beforeEach(Closure $callback): static
    {
        $this->beforeEach[] = $callback;

        return $this;
    }

    /**
     * @return list<Screenshot>
     */
    public function getScreenshots(): array
    {
        return $this->screenshots;
    }

    public function getOutputPath(): string
    {
        return $this->outputPath ?? Defaults::OUTPUT_PATH;
    }

    public function getBaseUrl(): ?string
    {
        return $this->baseUrl;
    }

    /**
     * @return list<string>
     */
    public function getMasks(): array
    {
        return $this->masks;
    }

    /**
     * @return list<Closure(PageInterface): mixed>
     */
    public function getBeforeEachCallbacks(): array
    {
        return $this->beforeEach;
    }

    /**
     * Resolve every screenshot × theme into a capture, applying CLI filters. Filters only narrow what the manifest defines.
     *
     * @param  list<string>  $only
     * @return list<Capture>
     */
    public function plan(string $rootPath, array $only = [], ?Theme $theme = null): array
    {
        $names = array_map(fn (Screenshot $screenshot): string => $screenshot->getName(), $this->screenshots);

        if ($unknown = array_values(array_diff($only, $names))) {
            throw new FocusException('Unknown screenshot(s) passed to --only: ' . implode(', ', $unknown) . '.');
        }

        $captures = [];

        foreach ($this->screenshots as $screenshot) {
            if ($only !== [] && ! in_array($screenshot->getName(), $only, true)) {
                continue;
            }

            foreach ($screenshot->getThemes() ?? $this->getThemes() ?? Defaults::THEMES as $screenshotTheme) {
                if ($theme instanceof Theme && $screenshotTheme !== $theme) {
                    continue;
                }

                $captures[] = Capture::resolve($this, $screenshot, $screenshotTheme, $this->resolveOutputDirectory($rootPath));
            }
        }

        return $captures;
    }

    public function resolveOutputDirectory(string $rootPath): string
    {
        $path = $this->getOutputPath();

        if (str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1) {
            return rtrim($path, '/\\');
        }

        return rtrim($rootPath, '/\\') . DIRECTORY_SEPARATOR . trim($path, '/\\');
    }
}
