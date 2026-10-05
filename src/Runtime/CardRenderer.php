<?php

declare(strict_types=1);

namespace Awcodes\Focus\Runtime;

use Awcodes\Focus\CardRender;
use Awcodes\Focus\Enums\FailureReason;
use Awcodes\Focus\Enums\Theme;
use Awcodes\Focus\Exceptions\CaptureException;
use Awcodes\Focus\Exceptions\FocusException;
use Awcodes\Focus\Support\CardFrame;
use Awcodes\Focus\Support\TemplateCanvas;
use Awcodes\Focus\Support\TemplateDirectory;
use DateTimeImmutable;
use Playwright\Browser\BrowserContextInterface;
use Playwright\Browser\BrowserInterface;
use Playwright\Exception\PlaywrightExceptionInterface;
use Playwright\Network\RouteInterface;
use Playwright\Page\PageInterface;
use Throwable;

/**
 * Renders a card: serves the template directory from a virtual origin, fills its `data-focus` elements, and captures
 * the viewport. The browser never reaches the network or the file system directly; every request is routed here.
 *
 * @internal
 */
final readonly class CardRenderer
{
    public const string ORIGIN = 'http://focus.localhost';

    public const array CONTENT_TYPES = [
        'html' => 'text/html; charset=utf-8',
        'htm' => 'text/html; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'js' => 'text/javascript; charset=utf-8',
        'mjs' => 'text/javascript; charset=utf-8',
        'json' => 'application/json',
        'map' => 'application/json',
        'txt' => 'text/plain; charset=utf-8',
        'xml' => 'application/xml',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
    ];

    private const string SCREENSHOT_PATH = '/__focus/screenshots/';

    public function __construct(
        private TemplateDirectory $templates,
        private int $timeout,
    ) {}

    public function render(BrowserInterface $browser, CardRender $render): CardResult
    {
        $started = microtime(true);
        $warnings = [];
        $context = null;

        try {
            $entry = $this->templates->find($render->template);
            $frame = CardFrame::for($render->size, $render->scale, TemplateCanvas::read((string) file_get_contents($entry)));
            [$screenshots, $files, $missing] = $this->screenshots($render);

            $context = $browser->newContext([
                'viewport' => ['width' => $frame->viewportWidth, 'height' => $frame->viewportHeight],
                'deviceScaleFactor' => $frame->deviceScaleFactor,
                'colorScheme' => $render->theme->value,
                'reducedMotion' => 'reduce',
                'locale' => $render->locale,
                'timezoneId' => $render->timezone,
            ]);

            $context->setDefaultTimeout($this->timeout);
            $context->setDefaultNavigationTimeout(max(30_000, $this->timeout));
            $context->addInitScript(Scripts::init($render->theme, animations: false));
            $context->addInitScript(Scripts::cardInit([
                'theme' => $render->theme->value,
                'size' => CardRender::sizeSegment($render->size),
                'values' => $render->values,
                'screenshots' => $screenshots,
                'missing' => $missing,
            ]));

            if ($render->frozenTime instanceof DateTimeImmutable) {
                $context->clock()->setFixedTime($render->frozenTime);
            }

            $requests = new RequestLog;
            $context->route('**/*', fn (RouteInterface $route) => $this->serve($route, $files, $requests));

            $page = $context->newPage();
            $page->goto(self::ORIGIN . $this->entryPath($entry));
            $page->evaluate(Scripts::eagerImages());

            if (! $this->settle($page)) {
                $warnings[] = 'The template was still changing ' . (Runner::READY_TIMEOUT / 1000) . 's after loading; captured anyway.';
            }

            /** @var array{used: list<string>, unknown: list<string>, invalid: list<string>, missing: list<string>, cssSlots: list<int>, width: int, height: int, viewportWidth: int, viewportHeight: int} $report */
            $report = $page->evaluate(Scripts::cardReport(), count($render->screenshots));

            $this->ensureValid($render, $report);

            array_push($warnings, ...$this->unused($render, $report), ...$requests->warnings());

            if ($report['width'] > $report['viewportWidth'] || $report['height'] > $report['viewportHeight']) {
                $warnings[] = "The template is {$report['width']}x{$report['height']}, larger than its {$report['viewportWidth']}x{$report['viewportHeight']} " . ($frame->clip === null ? 'card' : 'canvas') . ', so content overflows. Long text is the usual cause.';
            }

            if ($frame->warning !== null) {
                $warnings[] = $frame->warning;
            }

            AssetWriter::write($render->path, fn (string $path): string => $page->screenshot($path, array_filter([
                'animations' => 'disabled',
                'caret' => 'hide',
                'scale' => 'device',
                'clip' => $frame->clip,
            ])));

            return new CardResult($render, null, $warnings, microtime(true) - $started);
        } catch (Throwable $e) {
            return new CardResult($render, $this->classify($e), $warnings, microtime(true) - $started);
        } finally {
            if ($context instanceof BrowserContextInterface) {
                try {
                    $context->close();
                } catch (Throwable) {
                    // A crashed context cannot be closed; the browser is torn down at the end of the run.
                }
            }
        }
    }

    /**
     * Map every screenshot key the template may use to a URL on the virtual origin. Keys whose image cannot be served
     * map to an explanation instead, which fails the card only if the template actually uses that key.
     *
     * @return array{array<string, string>, array<string, string>, array<string, string>} urls by key, files by URL path, messages by key
     */
    private function screenshots(CardRender $render): array
    {
        $urls = [];
        $files = [];
        $missing = [];
        $names = $render->card->getScreenshots();

        foreach ($render->screenshots as $slot => $variants) {
            $name = $names[$slot - 1];

            foreach (Theme::cases() as $theme) {
                $keys = ["screenshot.{$slot}.{$theme->value}"];

                if ($theme === $render->theme) {
                    $keys[] = "screenshot.{$slot}";
                }

                $path = $variants[$theme->value] ?? null;
                $message = match (true) {
                    $path === null => "screenshot [{$name}] is not captured in {$theme->value}. Add Theme::" . ucfirst($theme->value) . ' to its themes().',
                    ! is_file($path) => "screenshot [{$name}] has no {$theme->value} capture at [{$path}]. Capture it first.",
                    default => null,
                };

                foreach ($keys as $key) {
                    if ($message !== null) {
                        $missing[$key] = "[{$key}]: {$message}";

                        continue;
                    }

                    $urlPath = self::SCREENSHOT_PATH . "{$slot}/{$theme->value}.png";
                    $urls[$key] = self::ORIGIN . $urlPath;
                    $files[$urlPath] = (string) $path;
                }
            }
        }

        return [$urls, $files, $missing];
    }

    /**
     * @param  array<string, string>  $screenshots  files by URL path
     */
    private function serve(RouteInterface $route, array $screenshots, RequestLog $requests): void
    {
        $url = $route->request()->url();

        if (! str_starts_with($url, self::ORIGIN . '/')) {
            $requests->blocked($url);
            $route->abort();

            return;
        }

        $path = rawurldecode((string) parse_url($url, PHP_URL_PATH));
        $file = $screenshots[$path] ?? $this->templateFile($path);

        // Playwright never answers a fulfill() for a missing file, which would hang the page, so answer 404 here.
        if ($file === null) {
            $requests->notFound($path);
            $route->fulfill(['status' => 404, 'contentType' => 'text/plain; charset=utf-8', 'body' => 'Not found']);

            return;
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        $route->fulfill([
            'status' => 200,
            'path' => $file,
            'contentType' => self::CONTENT_TYPES[$extension] ?? 'application/octet-stream',
        ]);
    }

    /**
     * A file inside the template directory, with `/` and extensionless directory paths served as `index.html`.
     */
    private function templateFile(string $path): ?string
    {
        $root = realpath($this->templates->path);

        if ($root === false || str_contains($path, "\0")) {
            return null;
        }

        $candidate = $root . str_replace('/', DIRECTORY_SEPARATOR, $path);

        if (is_dir($candidate)) {
            $candidate = rtrim($candidate, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index.html';
        }

        $real = realpath($candidate);

        if ($real === false || ! is_file($real) || ! str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $real;
    }

    /**
     * `two-up/index.html` is served at `/two-up/`, as a static host would, so relative URLs resolve the same way.
     */
    private function entryPath(string $entry): string
    {
        $relative = substr($entry, strlen(rtrim($this->templates->path, '/\\')) + 1);
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);

        if (str_ends_with($relative, '/index.html')) {
            $relative = substr($relative, 0, -strlen('index.html'));
        }

        return '/' . implode('/', array_map(rawurlencode(...), explode('/', $relative)));
    }

    private function settle(PageInterface $page): bool
    {
        try {
            $page->waitForFunction(Scripts::ready(), Runner::QUIET_FRAMES, ['timeout' => Runner::READY_TIMEOUT]);

            return true;
        } catch (PlaywrightExceptionInterface $e) {
            if (Timeouts::is($e)) {
                return false;
            }

            throw $e;
        }
    }

    /**
     * @param  array{unknown: list<string>, invalid: list<string>, missing: list<string>}  $report
     */
    private function ensureValid(CardRender $render, array $report): void
    {
        $problems = [];
        $slots = count($render->screenshots);

        foreach ($report['unknown'] as $key) {
            $problems[] = preg_match('/^screenshot\.(\d+)(\.(light|dark))?$/', $key, $matches) === 1
                ? "[{$key}]: the card passes " . ($slots === 1 ? '1 screenshot' : "{$slots} screenshots") . ", so there is no screenshot {$matches[1]}."
                : "[{$key}] is not a value this card provides (" . implode(', ', array_keys($render->values)) . ').';
        }

        foreach ($report['invalid'] as $usage) {
            $problems[] = "{$usage}: screenshot keys only work on <img>. Use the --focus-screenshot-N CSS variables for backgrounds.";
        }

        array_push($problems, ...$report['missing']);

        if ($problems !== []) {
            throw new CaptureException(FailureReason::Template, "Template [{$render->template}] has unusable data-focus keys:" . PHP_EOL . '  - ' . implode(PHP_EOL . '  - ', $problems));
        }
    }

    /**
     * @param  array{used: list<string>, cssSlots: list<int>}  $report
     * @return list<string>
     */
    private function unused(CardRender $render, array $report): array
    {
        $warnings = [];

        foreach (array_keys($render->card->getValues()) as $key) {
            if (! in_array($key, $report['used'], true)) {
                $warnings[] = "with() value [{$key}] is not used by template [{$render->template}].";
            }
        }

        foreach ($render->card->getScreenshots() as $index => $name) {
            $slot = $index + 1;
            $used = in_array($slot, $report['cssSlots'], true)
                || array_intersect(["screenshot.{$slot}", "screenshot.{$slot}.light", "screenshot.{$slot}.dark"], $report['used']) !== [];

            if (! $used) {
                $warnings[] = "Screenshot [{$name}] (screenshot.{$slot}) is not used by template [{$render->template}].";
            }
        }

        return $warnings;
    }

    private function classify(Throwable $e): CaptureException
    {
        if ($e instanceof CaptureException) {
            return $e;
        }

        $reason = match (true) {
            $e instanceof FocusException => FailureReason::Template,
            Timeouts::is($e) => FailureReason::Timeout,
            default => FailureReason::Browser,
        };

        return new CaptureException($reason, strtok($e->getMessage(), "\n") ?: $e::class, previous: $e);
    }
}
