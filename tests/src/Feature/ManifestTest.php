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
