<?php

declare(strict_types=1);

use Awcodes\Focus\Exceptions\CaptureException;
use Awcodes\Focus\Runtime\AssetWriter;
use Awcodes\Focus\Runtime\Orphans;
use Awcodes\Focus\Screenshot;
use Awcodes\Focus\ScreenshotSuite;

it('reports only scheme-matching files that the manifest did not produce', function (): void {
    $root = tempDirectory();
    mkdir("{$root}/docs/assets", recursive: true);

    foreach (['editor-light.png', 'editor-dark.png', 'old-light.png', 'old-dark.png', 'logo.png', 'light.png', '-dark.png', '.editor.abc.focus.png', 'notes-dark.jpg'] as $file) {
        touch("{$root}/docs/assets/{$file}");
    }

    $captures = ScreenshotSuite::make()->screenshots([Screenshot::make('editor')->visit('/')])->plan($root);

    expect(array_map(basename(...), Orphans::find("{$root}/docs/assets", $captures)))
        ->toBe(['old-dark.png', 'old-light.png']);
});

it('ignores a missing output directory', function (): void {
    expect(Orphans::find('/nonexistent/focus', []))->toBe([]);
});

it('writes atomically and creates the directory', function (): void {
    $path = tempDirectory() . '/nested/shot-light.png';

    AssetWriter::write($path, fn (string $temporary) => file_put_contents($temporary, 'png'));

    expect(file_get_contents($path))->toBe('png')
        ->and(glob(dirname($path) . '/.*.focus.png'))->toBe([]);
});

it('leaves the previous asset untouched when a capture fails', function (): void {
    $path = tempDirectory() . '/shot-light.png';
    file_put_contents($path, 'previous');

    expect(fn () => AssetWriter::write($path, function (string $temporary): void {
        file_put_contents($temporary, 'partial');

        throw new RuntimeException('browser crashed');
    }))->toThrow(RuntimeException::class, 'browser crashed');

    expect(file_get_contents($path))->toBe('previous')
        ->and(glob(dirname($path) . '/.*.focus.png'))->toBe([]);
});

it('rejects an empty capture', function (): void {
    $path = tempDirectory() . '/shot-light.png';

    AssetWriter::write($path, fn (string $temporary) => touch($temporary));
})->throws(CaptureException::class, 'did not produce an image');
