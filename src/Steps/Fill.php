<?php

declare(strict_types=1);

namespace Awcodes\Focus\Steps;

use Playwright\Page\PageInterface;

final readonly class Fill implements Step
{
    public function __construct(
        public string $selector,
        public string $value,
    ) {}

    public function run(PageInterface $page, string $baseUrl): void
    {
        $page->locator($this->selector)->fill($this->value);
    }

    public function describe(): string
    {
        return "fill({$this->selector})";
    }
}
