<?php

declare(strict_types=1);

use Awcodes\Focus\Screenshot;
use Awcodes\Focus\ScreenshotSuite;

return ScreenshotSuite::make()
    ->screenshots([
        Screenshot::make('dashboard')
            ->visit('/admin')
            ->viewport(),

        // Screenshot::make('editor')
        //     ->visit('/admin/posts/1/edit')
        //     ->focus('[data-focus="editor"]'),
    ]);
