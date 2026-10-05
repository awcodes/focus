<?php

declare(strict_types=1);

use Awcodes\Focus\Card;
use Awcodes\Focus\Enums\Size;
use Awcodes\Focus\ScreenshotSuite;

/*
 * Share cards for Focus itself, rendered by this repository's own bin/focus with `composer focus`. Focus has no UI
 * to capture, so the cards use the screenshot-free templates.
 */

return ScreenshotSuite::make()
    ->cardTemplates('https://github.com/awcodes/focus-templates/tree/v2.1.0/dist')
    ->cards([
        // Open Graph and the GitHub social preview share one 2400x1260 template; GitHub crops 30px top and bottom.
        Card::make('social')
            ->template('default-wide')
            ->with(['install' => 'composer require --dev awcodes/focus'])
            ->sizes([Size::OpenGraph, Size::GitHubSocial]),

        // Unbranded 16:9 image for aw.codes, which adds its own heading: a usage snippet and the install
        // command. code-plain shows up to 7 lines.
        Card::make('plain')
            ->template('code-plain')
            ->with([
                'install' => 'composer require --dev awcodes/focus',
                'code' => <<<'CODE'
                    return ScreenshotSuite::make()
                        ->screenshots([
                            Screenshot::make('editor')
                                ->visit('/admin/pages/1/edit')
                                ->focus('[data-focus="editor"]'),
                        ]);
                    CODE,
            ])
            ->sizes([[2560, 1440]])
            ->scale(1),
    ]);
