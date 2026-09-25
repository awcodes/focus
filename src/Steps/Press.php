<?php

declare(strict_types=1);

namespace Awcodes\Focus\Steps;

use Awcodes\Focus\Runtime\Elements;
use Playwright\Page\PageInterface;

final readonly class Press implements Step
{
    public function __construct(
        public string $key,
        public ?string $selector = null,
    ) {}

    public function run(PageInterface $page, string $baseUrl): void
    {
        if ($this->selector === null) {
            $page->keyboard()->press($this->key);

            return;
        }

        Elements::visible($page, $this->selector, $this->describe())->press($this->key);
    }

    public function describe(): string
    {
        return $this->selector === null
            ? "press({$this->key})"
            : "press({$this->key}, {$this->selector})";
    }
}
