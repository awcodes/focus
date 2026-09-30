<?php

declare(strict_types=1);

use Awcodes\Focus\Card;
use Awcodes\Focus\CardRender;
use Awcodes\Focus\Enums\Size;
use Awcodes\Focus\Enums\Theme;
use Awcodes\Focus\Enums\Viewport;
use Awcodes\Focus\Exceptions\FocusException;
use Awcodes\Focus\Screenshot;
use Awcodes\Focus\ScreenshotSuite;
use Awcodes\Focus\Support\Dimensions;

function cardRepository(array $composer = ['name' => 'awcodes/filament-curator', 'description' => 'A media library.']): string
{
    $root = tempDirectory();

    file_put_contents($root . '/composer.json', json_encode($composer));

    return $root;
}

it('renders one dark Open Graph image per card by default', function (): void {
    $root = cardRepository();

    $renders = ScreenshotSuite::make()
        ->cardTemplates('templates')
        ->cards([Card::make('social')])
        ->planCards($root);

    expect($renders)->toHaveCount(1)
        ->and($renders[0]->path)->toBe($root . '/art/social-open-graph-dark.png')
        ->and($renders[0]->template)->toBe('default')
        ->and($renders[0]->theme)->toBe(Theme::Dark)
        ->and($renders[0]->size)->toBe(Size::OpenGraph)
        ->and($renders[0]->scale)->toBe(2.0);
});

it('expands every theme and size', function (): void {
    $renders = ScreenshotSuite::make()
        ->cardTemplates('templates')
        ->cardOutputPath('build/cards')
        ->cards([
            Card::make('social')
                ->title('Mason')
                ->themes([Theme::Light, Theme::Dark])
                ->sizes([Size::OpenGraph, [1920, 1080]]),
        ])
        ->planCards('/repo');

    expect(array_map(fn (CardRender $render): string => $render->path, $renders))->toBe([
        '/repo/build/cards/social-open-graph-light.png',
        '/repo/build/cards/social-1920x1080-light.png',
        '/repo/build/cards/social-open-graph-dark.png',
        '/repo/build/cards/social-1920x1080-dark.png',
    ]);
});

it('names sizes by preset value or dimensions', function (): void {
    expect(CardRender::sizeSegment(Size::GitHubSocial))->toBe('github-social')
        ->and(CardRender::sizeSegment(Viewport::Mobile))->toBe('mobile')
        ->and(CardRender::sizeSegment(new Dimensions(1920, 1080)))->toBe('1920x1080')
        ->and(CardRender::filename('social', Size::Twitter, Theme::Light))->toBe('social-twitter-light.png');
});

it('fills values from composer.json', function (): void {
    [$render] = ScreenshotSuite::make()
        ->cardTemplates('templates')
        ->cards([Card::make('social')->with(['tagline' => 'Pick media'])])
        ->planCards(cardRepository());

    expect($render->values)->toBe([
        'tagline' => 'Pick media',
        'title' => 'Filament Curator',
        'description' => 'A media library.',
        'package' => 'awcodes/filament-curator',
    ]);
});

it('prefers the card title and description', function (): void {
    [$render] = ScreenshotSuite::make()
        ->cardTemplates('templates')
        ->cards([Card::make('social')->title('Curator')->description('Media, sorted.')])
        ->planCards(cardRepository());

    expect($render->values['title'])->toBe('Curator')
        ->and($render->values['description'])->toBe('Media, sorted.');
});

it('requires a title when composer.json has no name', function (): void {
    ScreenshotSuite::make()
        ->cardTemplates('templates')
        ->cards([Card::make('social')])
        ->planCards(tempDirectory());
})->throws(FocusException::class, 'Card [social] needs a title: composer.json has no package name. Add ->title().');

it('renders without composer.json when the card has a title', function (): void {
    [$render] = ScreenshotSuite::make()
        ->cardTemplates('templates')
        ->cards([Card::make('social')->title('Focus')])
        ->planCards(tempDirectory());

    expect($render->values)->toBe(['title' => 'Focus', 'description' => '', 'package' => '']);
});

it('maps screenshots to slots with every captured theme', function (): void {
    [$render] = ScreenshotSuite::make()
        ->outputPath('docs/img')
        ->cardTemplates('templates')
        ->screenshots([
            Screenshot::make('editor')->visit('/admin'),
            Screenshot::make('picker')->visit('/admin')->themes([Theme::Dark]),
        ])
        ->cards([Card::make('social')->title('Mason')->screenshots(['picker', 'editor'])])
        ->planCards('/repo');

    expect($render->screenshots)->toBe([
        1 => ['dark' => '/repo/docs/img/picker-dark.png'],
        2 => ['light' => '/repo/docs/img/editor-light.png', 'dark' => '/repo/docs/img/editor-dark.png'],
    ]);
});

it('takes locale, timezone, and frozen time from the suite', function (): void {
    [$render] = ScreenshotSuite::make()
        ->locale('de-DE')
        ->timezone('Europe/Berlin')
        ->freezeTime('2030-06-01 12:00:00')
        ->cardTemplates('templates')
        ->cards([Card::make('social')->title('Mason')])
        ->planCards('/repo');

    expect($render->locale)->toBe('de-DE')
        ->and($render->timezone)->toBe('Europe/Berlin')
        ->and($render->frozenTime?->format(DATE_ATOM))->toBe('2030-06-01T12:00:00+02:00');
});

it('filters cards by name and theme', function (): void {
    $suite = ScreenshotSuite::make()
        ->cardTemplates('templates')
        ->screenshots([Screenshot::make('editor')->visit('/admin')])
        ->cards([
            Card::make('social')->title('Mason')->themes([Theme::Light, Theme::Dark]),
            Card::make('banner')->title('Mason'),
        ]);

    expect(array_map(fn (CardRender $render): string => $render->label(), $suite->planCards('/repo', ['social'], Theme::Light)))
        ->toBe(['social (open-graph, light)'])
        ->and($suite->planCards('/repo', ['editor']))->toBe([])
        ->and($suite->plan('/repo', ['social']))->toBe([]);
});

it('rejects unknown names passed to --only', function (): void {
    ScreenshotSuite::make()
        ->cardTemplates('templates')
        ->cards([Card::make('social')->title('Mason')])
        ->planCards('/repo', ['social', 'nope']);
})->throws(FocusException::class, 'Unknown screenshot(s) or card(s) passed to --only: nope.');

it('resolves the template directory against the repository root', function (): void {
    expect(ScreenshotSuite::make()->resolveCardTemplatesDirectory('/repo'))->toBeNull()
        ->and(ScreenshotSuite::make()->cardTemplates('../templates/dist/')->resolveCardTemplatesDirectory('/repo'))->toBe('/repo/../templates/dist')
        ->and(ScreenshotSuite::make()->cardTemplates('/srv/templates')->resolveCardTemplatesDirectory('/repo'))->toBe('/srv/templates');
});
