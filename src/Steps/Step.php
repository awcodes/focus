<?php

declare(strict_types=1);

namespace Awcodes\Focus\Steps;

use Playwright\Page\PageInterface;

interface Step
{
    public function run(PageInterface $page, string $baseUrl): void;

    /**
     * A short human-readable description used in diagnostics.
     */
    public function describe(): string;
}
