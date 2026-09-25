<?php

declare(strict_types=1);

namespace Awcodes\Focus\Runtime;

use Playwright\Exception\PlaywrightExceptionInterface;
use Playwright\Exception\TimeoutException;
use Throwable;

final class Timeouts
{
    /**
     * playwright-php surfaces most Playwright timeouts as a generic PlaywrightException, so match the message too.
     */
    public static function is(Throwable $e): bool
    {
        return $e instanceof TimeoutException
            || ($e instanceof PlaywrightExceptionInterface && preg_match('/Timeout \d+ms exceeded/', $e->getMessage()) === 1);
    }
}
