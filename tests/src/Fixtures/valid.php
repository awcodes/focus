<?php

declare(strict_types=1);

use Awcodes\Focus\Enums\Theme;
use Awcodes\Focus\Screenshot;
use Awcodes\Focus\ScreenshotSuite;

return ScreenshotSuite::make()
    ->screenshots([
        Screenshot::make('editor')
            ->visit('/admin/pages/1/edit')
            ->focus('[data-focus="editor"]'),

        Screenshot::make('full-editor-page')
            ->visit('/admin/pages/1/edit')
            ->fullPage()
            ->themes([Theme::Light]),
    ]);
