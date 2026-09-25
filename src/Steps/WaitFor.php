<?php

declare(strict_types=1);

namespace Awcodes\Focus\Steps;

use Awcodes\Focus\Runtime\Elements;
use Playwright\Page\PageInterface;

final readonly class WaitFor implements Step
{
    public function __construct(
        public string $selector,
        public ?int $timeout = null,
        public ?string $frame = null,
    ) {}

    public function run(PageInterface $page, string $baseUrl): void
    {
        Elements::locate($page, $this->selector, $this->frame)->first()->waitFor(array_filter([
            'state' => 'visible',
            'timeout' => $this->timeout,
        ], fn (mixed $value): bool => $value !== null));
    }

    public function describe(): string
    {
        return InFrame::describe("waitFor({$this->selector})", $this->frame);
    }
}
