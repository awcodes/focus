<?php

declare(strict_types=1);

use Awcodes\Focus\Enums\CaptureMode;
use Awcodes\Focus\Enums\Size;
use Awcodes\Focus\Enums\Theme;
use Awcodes\Focus\Enums\Viewport;
use Awcodes\Focus\Exceptions\FocusException;
use Awcodes\Focus\Screenshot;
use Awcodes\Focus\ScreenshotSuite;

it('expands each screenshot into light and dark captures by default', function (): void {
    $captures = ScreenshotSuite::make()
        ->screenshots([
            Screenshot::make('editor')->visit('/admin'),
            Screenshot::make('picker')->visit('/admin'),
        ])
        ->plan('/repo');

    expect(array_map(fn ($capture) => $capture->path, $captures))->toBe([
        '/repo/docs/assets/editor-light.png',
        '/repo/docs/assets/editor-dark.png',
        '/repo/docs/assets/picker-light.png',
        '/repo/docs/assets/picker-dark.png',
    ]);
});

it('applies package defaults', function (): void {
    [$capture] = ScreenshotSuite::make()
        ->screenshots([Screenshot::make('editor')->visit('/admin')])
        ->plan('/repo');

    expect($capture->mode)->toBe(CaptureMode::Viewport)
        ->and($capture->viewport)->toBe(Viewport::Desktop)
        ->and($capture->scale)->toBe(2.0)
        ->and($capture->padding)->toBe(32)
        ->and($capture->minSize)->toBeNull()
        ->and($capture->animations)->toBeFalse()
        ->and($capture->locale)->toBe('en-US')
        ->and($capture->timezone)->toBe('UTC')
        ->and($capture->frozenTime?->format(DATE_ATOM))->toBe('2026-01-01T09:00:00+00:00')
        ->and($capture->maskColor)->toBe('#FF00FF');
});

it('resolves settings screenshot → suite → default', function (): void {
    $suite = ScreenshotSuite::make()
        ->themes([Theme::Dark])
        ->viewportSize(1280, 800)
        ->padding(16)
        ->minSize(Size::OpenGraph)
        ->scale(1)
        ->allowAnimations()
        ->locale('de-DE')
        ->timezone('Europe/Berlin')
        ->freezeTime('2030-06-01 12:00:00')
        ->maskColor('#000000')
        ->screenshots([
            Screenshot::make('inherits')->visit('/admin')->focus('#a'),
            Screenshot::make('overrides')
                ->visit('/admin')
                ->focus('#a')
                ->themes([Theme::Light])
                ->viewportSize(Viewport::Mobile)
                ->padding(8)
                ->minSize(300, 100)
                ->scale(3)
                ->allowAnimations(false)
                ->locale('fr-FR')
                ->timezone('America/New_York')
                ->freezeTime(false)
                ->maskColor('#FFFFFF'),
        ]);

    [$inherits, $overrides] = $suite->plan('/repo');

    expect($inherits->theme)->toBe(Theme::Dark)
        ->and((string) $inherits->viewport)->toBe('1280x800')
        ->and($inherits->padding)->toBe(16)
        ->and($inherits->minSize)->toBe(Size::OpenGraph)
        ->and($inherits->scale)->toBe(1.0)
        ->and($inherits->animations)->toBeTrue()
        ->and($inherits->locale)->toBe('de-DE')
        ->and($inherits->timezone)->toBe('Europe/Berlin')
        ->and($inherits->frozenTime?->format(DATE_ATOM))->toBe('2030-06-01T12:00:00+02:00')
        ->and($inherits->maskColor)->toBe('#000000');

    expect($overrides->theme)->toBe(Theme::Light)
        ->and($overrides->viewport)->toBe(Viewport::Mobile)
        ->and($overrides->padding)->toBe(8)
        ->and((string) $overrides->minSize)->toBe('300x100')
        ->and($overrides->scale)->toBe(3.0)
        ->and($overrides->animations)->toBeFalse()
        ->and($overrides->locale)->toBe('fr-FR')
        ->and($overrides->timezone)->toBe('America/New_York')
        ->and($overrides->frozenTime)->toBeNull()
        ->and($overrides->maskColor)->toBe('#FFFFFF');
});

it('merges suite and screenshot masks', function (): void {
    [$capture] = ScreenshotSuite::make()
        ->mask('[data-focus-mask]')
        ->themes([Theme::Light])
        ->screenshots([Screenshot::make('a')->visit('/admin')->mask('.avatar', '[data-focus-mask]')])
        ->plan('/repo');

    expect($capture->masks)->toBe(['[data-focus-mask]', '.avatar']);
});

it('narrows captures with --only and --theme', function (): void {
    $suite = ScreenshotSuite::make()->screenshots([
        Screenshot::make('editor')->visit('/admin'),
        Screenshot::make('picker')->visit('/admin'),
        Screenshot::make('light-only')->visit('/admin')->themes([Theme::Light]),
    ]);

    expect(array_map(fn ($capture) => $capture->label(), $suite->plan('/repo', ['picker'])))
        ->toBe(['picker (light)', 'picker (dark)'])
        ->and(array_map(fn ($capture) => $capture->label(), $suite->plan('/repo', theme: Theme::Dark)))
        ->toBe(['editor (dark)', 'picker (dark)'])
        ->and($suite->plan('/repo', ['light-only'], Theme::Dark))
        ->toBe([]);
});

it('rejects unknown --only names', function (): void {
    ScreenshotSuite::make()
        ->screenshots([Screenshot::make('editor')->visit('/admin')])
        ->plan('/repo', ['editor', 'nope']);
})->throws(FocusException::class, 'nope');

it('resolves relative and absolute output paths', function (): void {
    $suite = ScreenshotSuite::make()->screenshots([Screenshot::make('a')->visit('/admin')->themes([Theme::Light])]);

    expect($suite->outputPath('art/')->plan('/repo')[0]->path)->toBe('/repo/art/a-light.png')
        ->and($suite->outputPath('/tmp/shots')->plan('/repo')[0]->path)->toBe('/tmp/shots/a-light.png');
});
