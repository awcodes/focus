<?php

declare(strict_types=1);

namespace Awcodes\Focus\Steps;

use Awcodes\Focus\Runtime\Elements;
use Playwright\Page\PageInterface;

/**
 * @internal
 */
final readonly class Hover implements Step
{
    public function __construct(
        public string $selector,
        public ?string $frame = null,
    ) {}

    public function run(PageInterface $page, string $baseUrl): void
    {
        Elements::visible($page, $this->selector, $this->describe(), $this->frame)->hover();
    }

    public function describe(): string
    {
        return InFrame::describe("hover({$this->selector})", $this->frame);
    }
}
