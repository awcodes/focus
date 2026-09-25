<?php

declare(strict_types=1);

namespace Awcodes\Focus\Steps;

use Playwright\Page\PageInterface;

final readonly class WaitFor implements Step
{
    public function __construct(
        public string $selector,
        public ?int $timeout = null,
    ) {}

    public function run(PageInterface $page, string $baseUrl): void
    {
        $page->waitForSelector($this->selector, array_filter([
            'state' => 'visible',
            'timeout' => $this->timeout,
        ], fn (mixed $value): bool => $value !== null));
    }

    public function describe(): string
    {
        return "waitFor({$this->selector})";
    }
}
