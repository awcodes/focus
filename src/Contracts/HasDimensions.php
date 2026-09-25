<?php

declare(strict_types=1);

namespace Awcodes\Focus\Contracts;

interface HasDimensions
{
    public function width(): int;

    public function height(): int;
}
