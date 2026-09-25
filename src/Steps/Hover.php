<?php

declare(strict_types=1);

namespace Awcodes\Focus\Steps;

use Playwright\Page\PageInterface;

final readonly class Hover implements Step
{
    public function __construct(
        public string $selector,
    ) {}

    public function run(PageInterface $page, string $baseUrl): void
    {
        $page->locator($this->selector)->hover();
    }

    public function describe(): string
    {
        return "hover({$this->selector})";
    }
}
