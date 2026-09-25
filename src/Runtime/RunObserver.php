<?php

declare(strict_types=1);

namespace Awcodes\Focus\Runtime;

use Awcodes\Focus\Capture;

interface RunObserver
{
    public function captureStarting(Capture $capture): void;

    public function captureFinished(CaptureResult $result): void;
}
