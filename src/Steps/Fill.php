<?php

declare(strict_types=1);

namespace Awcodes\Focus\Steps;

use Awcodes\Focus\Runtime\Elements;
use Playwright\Page\PageInterface;

final readonly class Fill implements Step
{
    public function __construct(
        public string $selector,
        public string $value,
        public ?string $frame = null,
    ) {}

    public function run(PageInterface $page, string $baseUrl): void
    {
        Elements::visible($page, $this->selector, $this->describe(), $this->frame)->fill($this->value);
    }

    public function describe(): string
    {
        return InFrame::describe("fill({$this->selector})", $this->frame);
    }
}
