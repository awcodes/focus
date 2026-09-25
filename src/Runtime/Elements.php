<?php

declare(strict_types=1);

namespace Awcodes\Focus\Runtime;

use Awcodes\Focus\Enums\FailureReason;
use Awcodes\Focus\Exceptions\CaptureException;
use Playwright\Exception\PlaywrightExceptionInterface;
use Playwright\Locator\LocatorInterface;
use Playwright\Page\PageInterface;

final class Elements
{
    /**
     * Resolve a selector to exactly one visible element, distinguishing not found, ambiguous, and hidden.
     * Waiting here (within the context timeout) also sidesteps playwright-php's fixed 30s actionability poll.
     *
     * @param  string  $action  how the selector was used, for diagnostics, e.g. `click([data-focus-action="add"])`
     */
    public static function visible(PageInterface $page, string $selector, string $action): LocatorInterface
    {
        $locator = $page->locator($selector);

        try {
            $locator->first()->waitFor(['state' => 'attached']);
        } catch (PlaywrightExceptionInterface $e) {
            throw Timeouts::is($e)
                ? new CaptureException(FailureReason::SelectorNotFound, "{$action} matched no elements.", $selector, previous: $e)
                : $e;
        }

        if (($count = $locator->count()) > 1) {
            throw new CaptureException(FailureReason::SelectorAmbiguous, "{$action} matched {$count} elements. Add a more specific data-focus hook.", $selector);
        }

        try {
            $locator->waitFor(['state' => 'visible']);
        } catch (PlaywrightExceptionInterface $e) {
            throw Timeouts::is($e)
                ? new CaptureException(FailureReason::SelectorHidden, "{$action} matched an element that is not visible.", $selector, previous: $e)
                : $e;
        }

        return $locator;
    }
}
