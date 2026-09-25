<?php

declare(strict_types=1);

namespace Awcodes\Focus\Exceptions;

use Awcodes\Focus\Enums\FailureReason;
use Throwable;

/**
 * A failure with a category, so diagnostics can distinguish e.g. a missing selector from a timeout.
 */
class CaptureException extends FocusException
{
    public function __construct(
        public readonly FailureReason $reason,
        string $message,
        public readonly ?string $selector = null,
        public readonly ?string $url = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
