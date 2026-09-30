<?php

declare(strict_types=1);

use Awcodes\Focus\Screenshot;
use Awcodes\Focus\ScreenshotSuite;

return ScreenshotSuite::make()
    // Share images, rendered from a directory of built HTML templates such as an Astro dist/.
    // To enable, uncomment these lines and import Awcodes\Focus\Card.
    // ->cardTemplates('../card-templates/dist')
    // ->cards([
    //     Card::make('social')->template('default')->screenshots(['dashboard']),
    // ])
    ->screenshots([
        Screenshot::make('dashboard')
            ->visit('/admin')
            ->viewport(),

        // Screenshot::make('editor')
        //     ->visit('/admin/posts/1/edit')
        //     ->focus('[data-focus="editor"]'),
    ]);
