<?php

declare(strict_types=1);

use Awcodes\Focus\Screenshot;
use Awcodes\Focus\ScreenshotSuite;

return ScreenshotSuite::make()
    ->screenshots([
        Screenshot::make('Editor')->visit('/admin'),
        Screenshot::make('twice')->visit('/admin'),
        Screenshot::make('twice')->visit('/admin'),
        Screenshot::make('modes')->visit('/admin')->focus('#a')->fullPage(),
        Screenshot::make('padded')->visit('/admin')->viewport()->padding(10),
        Screenshot::make('nowhere'),
    ]);
