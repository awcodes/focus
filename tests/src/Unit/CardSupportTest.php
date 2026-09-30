<?php

declare(strict_types=1);

use Awcodes\Focus\Exceptions\FocusException;
use Awcodes\Focus\Support\PackageMetadata;
use Awcodes\Focus\Support\TemplateDirectory;

it('reads the package name and description', function (): void {
    $root = tempDirectory();
    file_put_contents($root . '/composer.json', json_encode(['name' => 'awcodes/mason', 'description' => 'Bricks.']));

    $metadata = PackageMetadata::fromComposer($root);

    expect($metadata->name)->toBe('awcodes/mason')
        ->and($metadata->description)->toBe('Bricks.')
        ->and($metadata->title())->toBe('Mason');
});

it('title-cases the package segment', function (string $name, string $title): void {
    expect((new PackageMetadata($name, null))->title())->toBe($title);
})->with([
    ['awcodes/filament-curator', 'Filament Curator'],
    ['awcodes/light_switch', 'Light Switch'],
    ['awcodes/filament.table-repeater', 'Filament Table Repeater'],
    ['no-vendor', 'No Vendor'],
]);

it('treats a missing composer.json or name as unknown', function (): void {
    $root = tempDirectory();

    expect(PackageMetadata::fromComposer($root)->name)->toBeNull()
        ->and(PackageMetadata::fromComposer($root)->title())->toBeNull();

    file_put_contents($root . '/composer.json', json_encode(['name' => '', 'description' => ['not', 'a', 'string']]));

    expect(PackageMetadata::fromComposer($root)->name)->toBeNull()
        ->and(PackageMetadata::fromComposer($root)->description)->toBeNull();
});

it('reports an unreadable composer.json', function (): void {
    $root = tempDirectory();
    file_put_contents($root . '/composer.json', '{ nope');

    PackageMetadata::fromComposer($root);
})->throws(FocusException::class, 'Could not read');

it('finds a template as a file or a directory index', function (): void {
    $root = tempDirectory();
    mkdir($root . '/two-up');
    mkdir($root . '/social');
    touch($root . '/default.html');
    touch($root . '/two-up/index.html');
    touch($root . '/social/wide.html');

    $templates = new TemplateDirectory($root);

    expect($templates->find('default'))->toBe($root . '/default.html')
        ->and($templates->find('two-up'))->toBe($root . '/two-up/index.html')
        ->and($templates->find('social/wide'))->toBe($root . '/social/wide.html');
});

it('reports a missing or ambiguous template', function (): void {
    $root = tempDirectory();
    mkdir($root . '/two-up');
    touch($root . '/two-up.html');
    touch($root . '/two-up/index.html');

    $templates = new TemplateDirectory($root);

    expect(fn () => $templates->find('one-up'))->toThrow(FocusException::class, 'Card template [one-up] not found: expected [one-up.html] or [one-up/index.html]')
        ->and(fn () => $templates->find('two-up'))->toThrow(FocusException::class, 'Card template [two-up] is ambiguous')
        ->and(fn () => (new TemplateDirectory($root . '/missing'))->find('two-up'))->toThrow(FocusException::class, 'does not exist');
});

it('lays a template without a canvas out at the card size', function (): void {
    $frame = Awcodes\Focus\Support\CardFrame::for(Awcodes\Focus\Enums\Size::OpenGraph, 2.0, null);

    expect([$frame->viewportWidth, $frame->viewportHeight, $frame->deviceScaleFactor, $frame->clip, $frame->warning])
        ->toBe([1200, 630, 2.0, null, null]);
});

it('scales a canvas to cover the card and crops the centre', function (): void {
    $canvas = new Awcodes\Focus\Support\Dimensions(2560, 1440);

    $github = Awcodes\Focus\Support\CardFrame::for(Awcodes\Focus\Enums\Size::GitHubSocial, 1.0, $canvas);
    $video = Awcodes\Focus\Support\CardFrame::for(new Awcodes\Focus\Support\Dimensions(1920, 1080), 1.0, $canvas);
    $tall = Awcodes\Focus\Support\CardFrame::for(new Awcodes\Focus\Support\Dimensions(1000, 1000), 1.0, $canvas);

    expect([$github->viewportWidth, $github->viewportHeight, $github->deviceScaleFactor])->toBe([2560, 1440, 0.5])
        ->and($github->clip)->toBe(['x' => 0.0, 'y' => 80.0, 'width' => 2560.0, 'height' => 1280.0])
        ->and($github->warning)->toBe('The 2560x1440 template was cropped to fit 1280x640: 80px from the top and bottom. Keep important content away from those edges.')
        ->and($video->deviceScaleFactor)->toBe(0.75)
        ->and($video->clip)->toBe(['x' => 0.0, 'y' => 0.0, 'width' => 2560.0, 'height' => 1440.0])
        ->and($video->warning)->toBeNull()
        ->and($tall->clip)->toBe(['x' => 560.0, 'y' => 0.0, 'width' => 1440.0, 'height' => 1440.0])
        ->and($tall->warning)->toContain('560px from the left and right');
});

it('ignores a crop within one percent', function (): void {
    $frame = Awcodes\Focus\Support\CardFrame::for(new Awcodes\Focus\Support\Dimensions(1200, 673), 1.0, new Awcodes\Focus\Support\Dimensions(2560, 1440));

    expect($frame->warning)->toBeNull();
});

it('crops an Open Graph canvas to GitHub social by 30px top and bottom', function (): void {
    $frame = Awcodes\Focus\Support\CardFrame::for(Awcodes\Focus\Enums\Size::GitHubSocial, 2.0, new Awcodes\Focus\Support\Dimensions(2400, 1260));

    expect($frame->clip)->toBe(['x' => 0.0, 'y' => 30.0, 'width' => 2400.0, 'height' => 1200.0])
        ->and($frame->warning)->toContain('30px from the top and bottom');
});

it('reads the canvas meta tag', function (string $html, ?string $expected): void {
    expect(Awcodes\Focus\Support\TemplateCanvas::read($html)?->__toString())->toBe($expected);
})->with([
    'name first' => ['<head><meta name="focus:canvas" content="2560x1440"></head>', '2560x1440'],
    'content first' => ["<meta content='1200x630' name='focus:canvas' />", '1200x630'],
    'unquoted' => ['<meta name=focus:canvas content=1920x1080>', '1920x1080'],
    'other meta only' => ['<meta name="viewport" content="width=device-width"><meta name="focus:canvasx" content="1x1">', null],
    'none' => ['<html></html>', null],
]);

it('rejects a malformed canvas meta tag', function (): void {
    Awcodes\Focus\Support\TemplateCanvas::read('<meta name="focus:canvas" content="2560 by 1440">');
})->throws(FocusException::class, 'The focus:canvas meta tag must look like content="2560x1440", [2560] given.');
