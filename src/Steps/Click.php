<?php

declare(strict_types=1);

namespace Awcodes\Focus\Steps;

use Playwright\Page\PageInterface;

final readonly class Click implements Step
{
    public function __construct(
        public string $selector,
    ) {}

    public function run(PageInterface $page, string $baseUrl): void
    {
        $page->locator($this->selector)->click();
    }

    public function describe(): string
    {
        return "click({$this->selector})";
    }
}
