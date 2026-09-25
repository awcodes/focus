<?php

declare(strict_types=1);

namespace Awcodes\Focus\Steps;

use Playwright\Page\PageInterface;

final readonly class Select implements Step
{
    /**
     * @param  string|list<string>  $values
     */
    public function __construct(
        public string $selector,
        public string | array $values,
    ) {}

    public function run(PageInterface $page, string $baseUrl): void
    {
        $page->locator($this->selector)->selectOption($this->values);
    }

    public function describe(): string
    {
        return "select({$this->selector})";
    }
}
