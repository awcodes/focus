<?php

declare(strict_types=1);

use Awcodes\Focus\Contracts\HasDimensions;
use Awcodes\Focus\Enums\Size;
use Awcodes\Focus\Enums\Viewport;
use Awcodes\Focus\Screenshot;
use Awcodes\Focus\Support\Dimensions;

enum DocsSize: string implements HasDimensions
{
    case Hero = 'hero';

    public function width(): int
    {
        return 1600;
    }

    public function height(): int
    {
        return 900;
    }
}

it('accepts integer dimensions', function (): void {
    $dimensions = Dimensions::from(400, 200, 'minSize');

    expect($dimensions->width())->toBe(400)
        ->and($dimensions->height())->toBe(200);
});

it('accepts built-in and user-defined presets', function (HasDimensions $preset, int $width, int $height): void {
    $dimensions = Dimensions::from($preset, null, 'minSize');

    expect($dimensions)->toBe($preset)
        ->and($dimensions->width())->toBe($width)
        ->and($dimensions->height())->toBe($height);
})->with([
    'open graph' => [Size::OpenGraph, 1200, 630],
    'twitter' => [Size::Twitter, 1200, 675],
    'youtube' => [Size::YouTube, 1280, 720],
    'github social' => [Size::GitHubSocial, 1280, 640],
    'desktop' => [Viewport::Desktop, 1440, 1000],
    'tablet' => [Viewport::Tablet, 768, 1024],
    'mobile' => [Viewport::Mobile, 390, 844],
    'custom' => [DocsSize::Hero, 1600, 900],
]);

it('requires a height for an integer width', function (): void {
    Screenshot::make('a')->minSize(400);
})->throws(InvalidArgumentException::class, 'minSize() requires a height');

it('forbids a height alongside a preset', function (): void {
    Screenshot::make('a')->viewportSize(Viewport::Mobile, 100);
})->throws(InvalidArgumentException::class, 'viewportSize() does not accept a height');

it('rejects non-positive dimensions', function (int $width, int $height): void {
    Screenshot::make('a')->minSize($width, $height);
})->with([[0, 100], [100, -1]])->throws(InvalidArgumentException::class, 'must be positive integers');

it('keeps the desktop viewport preset in sync with the package default', function (): void {
    expect(Awcodes\Focus\Defaults::VIEWPORT)->toBe(Viewport::Desktop);
});
