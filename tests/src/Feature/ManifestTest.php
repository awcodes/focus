<?php

declare(strict_types=1);

use Awcodes\Focus\Exceptions\InvalidManifestException;
use Awcodes\Focus\Manifest\ManifestLoader;
use Awcodes\Focus\ScreenshotSuite;

it('loads a valid manifest', function (): void {
    $suite = (new ManifestLoader)->load(manifestFixture('valid.php'));

    expect($suite)->toBeInstanceOf(ScreenshotSuite::class)
        ->and($suite->getScreenshots())->toHaveCount(2);
});

it('reports every validation error at once', function (): void {
    try {
        (new ManifestLoader)->load(manifestFixture('invalid.php'));
    } catch (InvalidManifestException $e) {
        expect($e->errors)->toBe([
            'Screenshot name [Editor] must be lowercase kebab-case (e.g. [brick-picker]).',
            'Duplicate screenshot name [twice].',
            'Screenshot [modes] has more than one capture mode (focus(), fullPage()); use exactly one of focus(), viewport(), or fullPage().',
            'Screenshot [padded] sets padding(), which only applies to focus() captures.',
            'Screenshot [nowhere] never calls visit().',
        ]);

        return;
    }

    test()->fail('Expected an InvalidManifestException.');
});

it('reports the manifest line of an invalid argument', function (): void {
    (new ManifestLoader)->load(manifestFixture('throws.php'));
})->throws(InvalidManifestException::class, 'minSize() requires a height when given an integer width. (line 10)');

it('requires the manifest to return a suite', function (): void {
    (new ManifestLoader)->load(manifestFixture('wrong-type.php'));
})->throws(InvalidManifestException::class, 'must return an instance of Awcodes\Focus\ScreenshotSuite, array returned');

it('explains a missing manifest', function (): void {
    (new ManifestLoader)->load(manifestFixture('missing.php'));
})->throws(InvalidManifestException::class, 'vendor/bin/focus init');

it('ignores suite-level padding and min size for non-focus screenshots', function (): void {
    $suite = ScreenshotSuite::make()
        ->padding(10)
        ->minSize(400, 200)
        ->screenshots([Awcodes\Focus\Screenshot::make('page')->visit('/admin')->fullPage()]);

    expect((new Awcodes\Focus\Manifest\ManifestValidator)->validate($suite))->toBe([]);
});

it('accepts a suite with only cards', function (): void {
    $suite = ScreenshotSuite::make()
        ->cardTemplates('templates')
        ->cards([Awcodes\Focus\Card::make('social')]);

    expect((new Awcodes\Focus\Manifest\ManifestValidator)->validate($suite))->toBe([]);
});

it('requires screenshots or cards', function (): void {
    expect((new Awcodes\Focus\Manifest\ManifestValidator)->validate(ScreenshotSuite::make()))
        ->toBe(['The suite does not define any screenshots or cards.']);
});

it('reports every card validation error at once', function (): void {
    $suite = ScreenshotSuite::make()
        ->cardOutputPath(' ')
        ->screenshots([
            Awcodes\Focus\Screenshot::make('editor')->visit('/admin'),
            Awcodes\Focus\Screenshot::make('picker')->visit('/admin')->themes([Awcodes\Focus\Enums\Theme::Light]),
        ])
        ->cards([
            Awcodes\Focus\Card::make('Social'),
            Awcodes\Focus\Card::make('editor'),
            Awcodes\Focus\Card::make('banner')->screenshots(['editor', 'missing', 'picker', 'picker']),
            Awcodes\Focus\Card::make('both')
                ->themes([Awcodes\Focus\Enums\Theme::Light, Awcodes\Focus\Enums\Theme::Dark])
                ->screenshots(['editor']),
        ]);

    expect((new Awcodes\Focus\Manifest\ManifestValidator)->validate($suite))->toBe([
        'cards() requires cardTemplates(): the directory of built card templates.',
        'cardOutputPath() must not be empty.',
        'Card name [Social] must be lowercase kebab-case (e.g. [social]).',
        'Duplicate name [editor]: screenshot and card names must be unique across the suite.',
        'Card [banner] uses unknown screenshot [missing].',
        "Card [banner] renders in [dark], but screenshot [picker] is not captured in that theme. Add it to the screenshot's themes() or change the card's themes().",
    ]);
});

it('rejects an empty card template directory', function (): void {
    $suite = ScreenshotSuite::make()
        ->cardTemplates('  ')
        ->cards([Awcodes\Focus\Card::make('social')]);

    expect((new Awcodes\Focus\Manifest\ManifestValidator)->validate($suite))->toBe(['cardTemplates() must not be empty.']);
});
