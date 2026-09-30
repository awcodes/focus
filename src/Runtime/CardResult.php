<?php

declare(strict_types=1);

namespace Awcodes\Focus\Runtime;

use Awcodes\Focus\CardRender;
use Awcodes\Focus\Exceptions\CaptureException;

final readonly class CardResult
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public CardRender $render,
        public ?CaptureException $error,
        public array $warnings,
        public float $seconds,
    ) {}

    public function succeeded(): bool
    {
        return ! $this->error instanceof CaptureException;
    }
}
