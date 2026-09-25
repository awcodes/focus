<?php

declare(strict_types=1);

namespace Awcodes\Focus\Steps;

use Awcodes\Focus\Runtime\Elements;
use Playwright\Page\PageInterface;

final readonly class Hover implements Step
{
    public function __construct(
        public string $selector,
    ) {}

    public function run(PageInterface $page, string $baseUrl): void
    {
        Elements::visible($page, $this->selector, $this->describe())->hover();
    }

    public function describe(): string
    {
        return "hover({$this->selector})";
    }
}
