<?php

declare(strict_types=1);

namespace Awcodes\Focus\Runtime;

/**
 * @internal
 */
final readonly class RunResults
{
    /**
     * @param  list<CaptureResult>  $captures
     * @param  list<CardResult>  $cards
     */
    public function __construct(
        public array $captures,
        public array $cards,
    ) {}

    public function failed(): int
    {
        return count(array_filter($this->captures, fn (CaptureResult $result): bool => ! $result->succeeded()))
            + count(array_filter($this->cards, fn (CardResult $result): bool => ! $result->succeeded()));
    }
}
