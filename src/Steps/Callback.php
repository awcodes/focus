<?php

declare(strict_types=1);

namespace Awcodes\Focus\Steps;

use Closure;
use Playwright\Page\PageInterface;

/**
 * @internal
 */
final readonly class Callback implements Step
{
    /**
     * @param  Closure(PageInterface): mixed  $callback
     */
    public function __construct(
        public Closure $callback,
        public string $label = 'callback',
    ) {}

    public function run(PageInterface $page, string $baseUrl): void
    {
        ($this->callback)($page);
    }

    public function describe(): string
    {
        return $this->label;
    }
}
