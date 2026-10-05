<?php

declare(strict_types=1);

namespace Awcodes\Focus\Runtime;

use Awcodes\Focus\Capture;
use Awcodes\Focus\Exceptions\CaptureException;

/**
 * @internal
 */
final readonly class CaptureResult
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public Capture $capture,
        public ?CaptureException $error,
        public array $warnings,
        public float $seconds,
    ) {}

    public function succeeded(): bool
    {
        return ! $this->error instanceof CaptureException;
    }
}
