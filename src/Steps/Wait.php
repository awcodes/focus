<?php

declare(strict_types=1);

namespace Awcodes\Focus\Steps;

use Playwright\Page\PageInterface;

/**
 * @internal
 */
final readonly class Wait implements Step
{
    public function __construct(
        public int $milliseconds,
    ) {}

    public function run(PageInterface $page, string $baseUrl): void
    {
        usleep($this->milliseconds * 1000);
    }

    public function describe(): string
    {
        return "wait({$this->milliseconds}ms)";
    }
}
