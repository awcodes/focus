<?php

declare(strict_types=1);

namespace Awcodes\Focus\Steps;

use Awcodes\Focus\Runtime\Elements;
use Playwright\Page\PageInterface;

/**
 * @internal
 */
final readonly class Select implements Step
{
    /**
     * @param  string|list<string>  $values
     */
    public function __construct(
        public string $selector,
        public string | array $values,
        public ?string $frame = null,
    ) {}

    public function run(PageInterface $page, string $baseUrl): void
    {
        Elements::visible($page, $this->selector, $this->describe(), $this->frame)->selectOption($this->values);
    }

    public function describe(): string
    {
        return InFrame::describe("select({$this->selector})", $this->frame);
    }
}
