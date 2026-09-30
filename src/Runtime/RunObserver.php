<?php

declare(strict_types=1);

namespace Awcodes\Focus\Runtime;

use Awcodes\Focus\Capture;
use Awcodes\Focus\CardRender;

interface RunObserver
{
    public function captureStarting(Capture $capture): void;

    public function captureFinished(CaptureResult $result): void;

    public function cardStarting(CardRender $render): void;

    public function cardFinished(CardResult $result): void;
}
