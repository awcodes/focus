<?php

declare(strict_types=1);

namespace Awcodes\Focus\Authentication;

use Playwright\Page\PageInterface;

/**
 * Establishes authenticated browser state once per run. The resulting cookies and storage are reused by every capture.
 */
interface Authenticator
{
    public function authenticate(PageInterface $page, string $baseUrl): void;
}
