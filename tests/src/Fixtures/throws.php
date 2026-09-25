<?php

declare(strict_types=1);

use Awcodes\Focus\Screenshot;
use Awcodes\Focus\ScreenshotSuite;

return ScreenshotSuite::make()
    ->screenshots([
        Screenshot::make('bad-size')->visit('/admin')->focus('#a')->minSize(400),
    ]);
