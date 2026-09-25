<?php

declare(strict_types=1);

namespace Awcodes\Focus;

use Awcodes\Focus\Concerns\HasCaptureSettings;
use Awcodes\Focus\Enums\CaptureMode;
use Awcodes\Focus\Steps\Callback;
use Awcodes\Focus\Steps\Click;
use Awcodes\Focus\Steps\Fill;
use Awcodes\Focus\Steps\Hover;
use Awcodes\Focus\Steps\Press;
use Awcodes\Focus\Steps\ScrollIntoView;
use Awcodes\Focus\Steps\Select;
use Awcodes\Focus\Steps\Step;
use Awcodes\Focus\Steps\Visit;
use Awcodes\Focus\Steps\Wait;
use Awcodes\Focus\Steps\WaitFor;
use Closure;
use InvalidArgumentException;
use Playwright\Page\PageInterface;

class Screenshot
{
    use HasCaptureSettings;

    /** @var list<Step> */
    protected array $steps = [];

    /**
     * Every capture mode requested, in call order. More than one distinct mode is a validation error.
     *
     * @var list<CaptureMode>
     */
    protected array $captureModes = [];

    protected ?string $focusSelector = null;

    /** @var list<string> */
    protected array $masks = [];

    /** @var list<Closure(PageInterface): mixed> */
    protected array $before = [];

    /** @var list<Closure(PageInterface): mixed> */
    protected array $beforeCapture = [];

    /** @var list<Closure(PageInterface): mixed> */
    protected array $after = [];

    final public function __construct(
        protected string $name,
    ) {}

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function visit(string $url): static
    {
        return $this->step(new Visit($url));
    }

    public function click(string $selector): static
    {
        return $this->step(new Click($selector));
    }

    public function fill(string $selector, string $value): static
    {
        return $this->step(new Fill($selector, $value));
    }

    /**
     * @param  string|list<string>  $values
     */
    public function select(string $selector, string | array $values): static
    {
        return $this->step(new Select($selector, $values));
    }

    public function hover(string $selector): static
    {
        return $this->step(new Hover($selector));
    }

    /**
     * Press a key, on the given element or on the page when no selector is given.
     */
    public function press(string $key, ?string $selector = null): static
    {
        return $this->step(new Press($key, $selector));
    }

    public function scrollIntoView(string $selector): static
    {
        return $this->step(new ScrollIntoView($selector));
    }

    public function waitFor(string $selector, ?int $timeout = null): static
    {
        return $this->step(new WaitFor($selector, $timeout));
    }

    /**
     * An explicit delay. Prefer `waitFor()` or `ready()`; this is an escape hatch.
     */
    public function wait(int $milliseconds): static
    {
        if ($milliseconds < 0) {
            throw new InvalidArgumentException("wait() must be zero or greater, [{$milliseconds}] given.");
        }

        return $this->step(new Wait($milliseconds));
    }

    /**
     * Wait for a custom readiness condition using the Playwright page directly.
     *
     * @param  Closure(PageInterface): mixed  $callback
     */
    public function ready(Closure $callback): static
    {
        return $this->step(new Callback($callback, 'ready()'));
    }

    public function step(Step $step): static
    {
        $this->steps[] = $step;

        return $this;
    }

    /**
     * Frame the capture around a subject: the selector identifies what matters, Focus handles the crop.
     */
    public function focus(string $selector): static
    {
        $this->captureModes[] = CaptureMode::Focus;
        $this->focusSelector = $selector;

        return $this;
    }

    /**
     * Capture the visible browser viewport. Use `viewportSize()` to change browser dimensions.
     */
    public function viewport(): static
    {
        $this->captureModes[] = CaptureMode::Viewport;

        return $this;
    }

    public function fullPage(): static
    {
        $this->captureModes[] = CaptureMode::FullPage;

        return $this;
    }

    /**
     * Cover matching elements with a solid block at capture time.
     */
    public function mask(string ...$selectors): static
    {
        array_push($this->masks, ...$selectors);

        return $this;
    }

    /**
     * Run after the suite's `beforeEach()` and before any steps.
     *
     * @param  Closure(PageInterface): mixed  $callback
     */
    public function before(Closure $callback): static
    {
        $this->before[] = $callback;

        return $this;
    }

    /**
     * Run after all steps and readiness checks, immediately before capture.
     *
     * @param  Closure(PageInterface): mixed  $callback
     */
    public function beforeCapture(Closure $callback): static
    {
        $this->beforeCapture[] = $callback;

        return $this;
    }

    /**
     * @param  Closure(PageInterface): mixed  $callback
     */
    public function after(Closure $callback): static
    {
        $this->after[] = $callback;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return list<Step>
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * The URL of the first `visit()`, used for diagnostics.
     */
    public function getUrl(): ?string
    {
        foreach ($this->steps as $step) {
            if ($step instanceof Visit) {
                return $step->url;
            }
        }

        return null;
    }

    /**
     * @return list<CaptureMode>
     */
    public function getCaptureModes(): array
    {
        return array_values(array_unique($this->captureModes, SORT_REGULAR));
    }

    public function getCaptureMode(): CaptureMode
    {
        return $this->captureModes[0] ?? CaptureMode::Viewport;
    }

    public function getFocusSelector(): ?string
    {
        return $this->focusSelector;
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
    public function getBeforeCallbacks(): array
    {
        return $this->before;
    }

    /**
     * @return list<Closure(PageInterface): mixed>
     */
    public function getBeforeCaptureCallbacks(): array
    {
        return $this->beforeCapture;
    }

    /**
     * @return list<Closure(PageInterface): mixed>
     */
    public function getAfterCallbacks(): array
    {
        return $this->after;
    }
}
