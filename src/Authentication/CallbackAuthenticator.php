<?php

declare(strict_types=1);

namespace Awcodes\Focus\Authentication;

use Closure;
use Playwright\Page\PageInterface;

final readonly class CallbackAuthenticator implements Authenticator
{
    /**
     * @param  Closure(PageInterface, string): mixed  $callback
     */
    public function __construct(
        private Closure $callback,
    ) {}

    public function authenticate(PageInterface $page, string $baseUrl): void
    {
        ($this->callback)($page, $baseUrl);
    }
}
