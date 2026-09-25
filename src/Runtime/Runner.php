<?php

declare(strict_types=1);

namespace Awcodes\Focus\Runtime;

use Awcodes\Focus\Authentication\FormLogin;
use Awcodes\Focus\Authentication\SessionCache;
use Awcodes\Focus\Capture;
use Awcodes\Focus\Enums\CaptureMode;
use Awcodes\Focus\Enums\FailureReason;
use Awcodes\Focus\Exceptions\CaptureException;
use Awcodes\Focus\ScreenshotSuite;
use Awcodes\Focus\Steps\Click;
use Awcodes\Focus\Steps\Hover;
use Awcodes\Focus\Steps\InFrame;
use Awcodes\Focus\Steps\Step;
use Closure;
use DateTimeImmutable;
use Playwright\Browser\BrowserContextInterface;
use Playwright\Browser\BrowserInterface;
use Playwright\Configuration\PlaywrightConfig;
use Playwright\Exception\PlaywrightExceptionInterface;
use Playwright\Page\PageInterface;
use Playwright\PlaywrightClient;
use Playwright\PlaywrightFactory;
use Throwable;

final readonly class Runner
{
    /**
     * Consecutive animation frames with no DOM or network activity before the UI counts as settled.
     */
    public const QUIET_FRAMES = 6;

    public const READY_TIMEOUT = 10_000;

    public const INSTALL_HINT = 'Run `vendor/bin/focus init` (or `vendor/bin/playwright-install chromium`) to install the Playwright server and browser.';

    /** @var array{width: int, height: int} */
    private const AUTH_VIEWPORT = ['width' => 1440, 'height' => 1000];

    public function __construct(
        private ?RunObserver $observer = null,
        private ?SessionCache $sessions = null,
    ) {}

    /**
     * @param  list<Capture>  $captures
     * @return list<CaptureResult>
     */
    public function run(ScreenshotSuite $suite, array $captures, string $baseUrl, bool $headed = false): array
    {
        $client = $this->client($headed, $suite->getTimeout());

        try {
            $browser = $this->launch($client, $headed);
            $storageState = $this->authenticate($browser, $suite, $baseUrl);

            $results = [];

            foreach ($captures as $capture) {
                $this->observer?->captureStarting($capture);
                $results[] = $result = $this->capture($browser, $suite, $capture, $baseUrl, $storageState);
                $this->observer?->captureFinished($result);
            }

            return $results;
        } finally {
            try {
                $client->close();
            } catch (Throwable) {
                // The browser process may already be gone; there is nothing left to clean up.
            }
        }
    }

    private function client(bool $headed, int $timeout): PlaywrightClient
    {
        try {
            return PlaywrightFactory::create(new PlaywrightConfig(headless: ! $headed, timeoutMs: max(30_000, $timeout)));
        } catch (Throwable $e) {
            throw new CaptureException(FailureReason::Browser, "Could not start Playwright: {$e->getMessage()}" . PHP_EOL . self::INSTALL_HINT, previous: $e);
        }
    }

    private function launch(PlaywrightClient $client, bool $headed): BrowserInterface
    {
        try {
            return $client->chromium()->withHeadless(! $headed)->launch();
        } catch (Throwable $e) {
            throw new CaptureException(FailureReason::Browser, "Could not launch Chromium: {$e->getMessage()}" . PHP_EOL . self::INSTALL_HINT, previous: $e);
        }
    }

    /**
     * Sign in once and return the resulting storage state for every capture context.
     *
     * @return array<string, mixed>|null
     */
    private function authenticate(BrowserInterface $browser, ScreenshotSuite $suite, string $baseUrl): ?array
    {
        $authenticator = $suite->getAuthenticator();

        if (! $authenticator instanceof \Awcodes\Focus\Authentication\Authenticator) {
            return null;
        }

        // Only form logins reuse a session: FormLogin checks whether the app still considers it signed in
        // before touching the form, so a stale session costs nothing. A custom callback may not.
        $sessions = $authenticator instanceof FormLogin ? $this->sessions : null;

        $context = $browser->newContext(array_filter([
            'viewport' => self::AUTH_VIEWPORT,
            'storageState' => $sessions?->load(),
        ]));

        try {
            $this->configureTimeouts($context, $suite->getTimeout());

            $page = $context->newPage();
            $authenticator->authenticate($page, $baseUrl);
            $this->settle($page);

            $state = $context->storageState();
            $sessions?->save($state);

            return $state;
        } catch (CaptureException $e) {
            $sessions?->forget();

            throw $e;
        } catch (Throwable $e) {
            $sessions?->forget();

            throw new CaptureException(FailureReason::Authentication, $e->getMessage(), url: $baseUrl, previous: $e);
        } finally {
            $context->close();
        }
    }

    /**
     * @param  array<string, mixed>|null  $storageState
     */
    private function capture(BrowserInterface $browser, ScreenshotSuite $suite, Capture $capture, string $baseUrl, ?array $storageState): CaptureResult
    {
        $started = microtime(true);
        $warnings = [];
        $url = $capture->screenshot->getUrl();

        $context = $browser->newContext(array_filter([
            'viewport' => ['width' => $capture->viewport->width(), 'height' => $capture->viewport->height()],
            'deviceScaleFactor' => $capture->scale,
            'colorScheme' => $capture->theme->value,
            'reducedMotion' => $capture->animations ? null : 'reduce',
            'locale' => $capture->locale,
            'timezoneId' => $capture->timezone,
            'storageState' => $storageState,
        ], fn (mixed $value): bool => $value !== null));

        try {
            $this->configureTimeouts($context, $suite->getTimeout());
            $context->addInitScript(Scripts::init($capture->theme, $capture->animations));

            if ($capture->frozenTime instanceof DateTimeImmutable) {
                $context->clock()->setFixedTime($capture->frozenTime);
            }

            $page = $context->newPage();

            $this->callbacks('beforeEach()', $suite->getBeforeEachCallbacks(), $page);
            $this->callbacks('before()', $capture->screenshot->getBeforeCallbacks(), $page);

            foreach ($capture->screenshot->getSteps() as $step) {
                $this->step($step, $page, $baseUrl);
                $url = $page->url();

                if (! $this->settle($page)) {
                    $warnings[] = "The page was still changing {$this->seconds(self::READY_TIMEOUT)}s after {$step->describe()}; captured anyway. Add ->waitFor() or ->ready() if the result is incomplete.";
                }
            }

            $page->evaluate(Scripts::eagerImages());

            if (! $this->settle($page)) {
                $warnings[] = "Images were still loading {$this->seconds(self::READY_TIMEOUT)}s before capture; captured anyway. A remote image, such as an avatar, may be slow or unreachable; hide() or mask() it.";
            }

            if (! $capture->keepInteractionState) {
                $this->resetInteractionState($page, $capture);
            }

            $this->callbacks('beforeCapture()', $capture->screenshot->getBeforeCaptureCallbacks(), $page);

            $this->shoot($page, $capture, $warnings);

            $this->callbacks('after()', $capture->screenshot->getAfterCallbacks(), $page);

            return new CaptureResult($capture, null, array_values(array_unique($warnings)), microtime(true) - $started);
        } catch (Throwable $e) {
            return new CaptureResult($capture, $this->classify($e, $url), $warnings, microtime(true) - $started);
        } finally {
            try {
                $context->close();
            } catch (Throwable) {
                // A crashed context cannot be closed; the browser is torn down at the end of the run.
            }
        }
    }

    private function step(Step $step, PageInterface $page, string $baseUrl): void
    {
        try {
            $step->run($page, $baseUrl);
        } catch (CaptureException $e) {
            throw $e;
        } catch (PlaywrightExceptionInterface $e) {
            if (Timeouts::is($e)) {
                throw new CaptureException(FailureReason::Timeout, "{$step->describe()} timed out: {$this->firstLine($e)}", previous: $e);
            }

            $reason = str_contains($e->getMessage(), 'strict mode violation') ? FailureReason::SelectorAmbiguous : FailureReason::Browser;

            throw new CaptureException($reason, "{$step->describe()} failed: {$this->firstLine($e)}", previous: $e);
        } catch (Throwable $e) {
            throw new CaptureException(FailureReason::Callback, "{$step->describe()} threw: {$e->getMessage()}", previous: $e);
        }
    }

    /**
     * @param  list<Closure(PageInterface): mixed>  $callbacks
     */
    private function callbacks(string $name, array $callbacks, PageInterface $page): void
    {
        foreach ($callbacks as $callback) {
            try {
                $callback($page);
            } catch (Throwable $e) {
                throw new CaptureException(
                    Timeouts::is($e) ? FailureReason::Timeout : FailureReason::Callback,
                    "{$name} callback failed: {$this->firstLine($e)}",
                    previous: $e,
                );
            }
        }
    }

    /**
     * @param  list<string>  $warnings
     */
    private function shoot(PageInterface $page, Capture $capture, array &$warnings): void
    {
        $options = [
            'animations' => $capture->animations ? 'allow' : 'disabled',
            'caret' => 'hide',
            'scale' => 'device',
        ];

        if ($capture->mode === CaptureMode::FullPage) {
            $options['fullPage'] = true;
        }

        if ($capture->mode === CaptureMode::Focus) {
            $framed = $this->frame($page, $capture);

            // Clip is in document coordinates when combined with fullPage, so regions taller than the viewport still work.
            $options['fullPage'] = true;
            $options['clip'] = $framed['clip'];

            if ($framed['clamped']) {
                $warnings[] = 'The subject or the minimum size is larger than the document, so the capture was clamped to the document bounds.';
            }
        }

        if ($capture->hidden !== []) {
            $page->evaluate(Scripts::hide(), ['selectors' => $capture->hidden, 'id' => Scripts::HIDE_STYLE_ID]);
        }

        $masked = $capture->masks !== [] && $page->evaluate(Scripts::mask(), [
            'selectors' => $capture->masks,
            'color' => $capture->maskColor,
            'tag' => Scripts::MASK_TAG,
        ]) > 0;

        try {
            AssetWriter::write($capture->path, fn (string $path): string => $page->screenshot($path, $options));
        } finally {
            if ($masked) {
                $page->evaluate(Scripts::unmask(), Scripts::MASK_TAG);
            }

            if ($capture->hidden !== []) {
                $page->evaluate(Scripts::unhide(), Scripts::HIDE_STYLE_ID);
            }
        }
    }

    /**
     * @return array{clip: array{x: int, y: int, width: int, height: int}, clamped: bool}
     */
    private function frame(PageInterface $page, Capture $capture): array
    {
        $selector = (string) $capture->screenshot->getFocusSelector();
        $frame = $capture->screenshot->getFocusFrame();
        $locator = Elements::visible($page, $selector, InFrame::describe("focus({$selector})", $frame), $frame);
        $locator->scrollIntoViewIfNeeded();

        $this->settle($page);

        $box = $locator->boundingBox();

        if ($box === null || $box['width'] <= 0 || $box['height'] <= 0) {
            throw new CaptureException(FailureReason::SelectorHidden, "focus({$selector}) matched an element with no size.", $selector);
        }

        /** @var array{width: float, height: float, scrollX: float, scrollY: float} $document */
        $document = $page->evaluate(Scripts::documentSize());

        return Framer::frame(
            [
                'x' => (float) $box['x'] + $document['scrollX'],
                'y' => (float) $box['y'] + $document['scrollY'],
                'width' => (float) $box['width'],
                'height' => (float) $box['height'],
            ],
            $capture->padding,
            $capture->minSize,
            $document['width'],
            $document['height'],
        );
    }

    /**
     * Clear what interactions leave behind: hover styles and tooltips under the pointer, and focus rings.
     * A `hover()` after the last `click()` is deliberate, so the pointer stays put.
     */
    private function resetInteractionState(PageInterface $page, Capture $capture): void
    {
        if (! $this->endsWithHover($capture)) {
            $page->mouse()->move(-1, -1);
        }

        $page->evaluate(Scripts::blur());
        $this->settle($page);
    }

    private function endsWithHover(Capture $capture): bool
    {
        $last = null;

        foreach ($capture->screenshot->getSteps() as $step) {
            if ($step instanceof Click || $step instanceof Hover) {
                $last = $step;
            }
        }

        return $last instanceof Hover;
    }

    /**
     * Wait for the UI to settle. Returns false when it did not settle in time, which is a warning, not a failure.
     */
    private function settle(PageInterface $page): bool
    {
        try {
            $page->waitForFunction(Scripts::ready(), self::QUIET_FRAMES, ['timeout' => self::READY_TIMEOUT]);

            return true;
        } catch (PlaywrightExceptionInterface $e) {
            if (Timeouts::is($e)) {
                return false;
            }

            throw $e;
        }
    }

    private function configureTimeouts(BrowserContextInterface $context, int $timeout): void
    {
        $context->setDefaultTimeout($timeout);
        $context->setDefaultNavigationTimeout(max(30_000, $timeout));
    }

    private function classify(Throwable $e, ?string $url): CaptureException
    {
        if ($e instanceof CaptureException) {
            return $e->url === null && $url !== null
                ? new CaptureException($e->reason, $e->getMessage(), $e->selector, $url, $e->getPrevious() ?? $e)
                : $e;
        }

        $reason = match (true) {
            Timeouts::is($e) => FailureReason::Timeout,
            str_contains($e->getMessage(), 'strict mode violation') => FailureReason::SelectorAmbiguous,
            $e instanceof PlaywrightExceptionInterface => FailureReason::Browser,
            default => FailureReason::Callback,
        };

        return new CaptureException($reason, $this->firstLine($e), url: $url, previous: $e);
    }

    private function firstLine(Throwable $e): string
    {
        return strtok($e->getMessage(), "\n") ?: $e::class;
    }

    private function seconds(int $milliseconds): string
    {
        return rtrim(rtrim(number_format($milliseconds / 1000, 1), '0'), '.');
    }
}
