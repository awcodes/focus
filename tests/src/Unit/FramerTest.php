<?php

declare(strict_types=1);

use Awcodes\Focus\Runtime\Framer;
use Awcodes\Focus\Support\Dimensions;

it('expands the subject by padding', function (): void {
    $framed = Framer::frame(['x' => 100, 'y' => 100, 'width' => 50, 'height' => 20], 10, null, 2000, 2000);

    expect($framed)->toBe(['clip' => ['x' => 90, 'y' => 90, 'width' => 70, 'height' => 40], 'clamped' => false]);
});

it('grows a small subject to the minimum size, centered', function (): void {
    $framed = Framer::frame(['x' => 500, 'y' => 500, 'width' => 32, 'height' => 32], 0, new Dimensions(400, 200), 2000, 2000);

    expect($framed['clip'])->toBe(['x' => 316, 'y' => 416, 'width' => 400, 'height' => 200]);
});

it('shifts rather than shrinks near a document edge', function (): void {
    $framed = Framer::frame(['x' => 5, 'y' => 1990, 'width' => 32, 'height' => 8], 0, new Dimensions(400, 200), 1440, 2000);

    expect($framed)->toBe(['clip' => ['x' => 0, 'y' => 1800, 'width' => 400, 'height' => 200], 'clamped' => false]);
});

it('clamps a region larger than the document and flags it', function (): void {
    $framed = Framer::frame(['x' => 10, 'y' => 10, 'width' => 100, 'height' => 100], 0, new Dimensions(2000, 300), 1440, 900);

    expect($framed)->toBe(['clip' => ['x' => 0, 'y' => 0, 'width' => 1440, 'height' => 300], 'clamped' => true]);
});

it('does not report padding that runs past the document edge', function (): void {
    $framed = Framer::frame(['x' => 5, 'y' => 100, 'width' => 380, 'height' => 100], 32, null, 390, 2000);

    expect($framed)->toBe(['clip' => ['x' => 0, 'y' => 68, 'width' => 390, 'height' => 164], 'clamped' => false]);
});

it('reports a subject wider than the document', function (): void {
    expect(Framer::frame(['x' => 0, 'y' => 0, 'width' => 500, 'height' => 100], 0, null, 390, 2000)['clamped'])->toBeTrue();
});

it('rounds to whole pixels without growing past the requested size', function (): void {
    $framed = Framer::frame(['x' => 10.4, 'y' => 10.6, 'width' => 20.2, 'height' => 20.2], 0, null, 1000, 1000);

    expect($framed['clip'])->toBe(['x' => 10, 'y' => 11, 'width' => 21, 'height' => 21]);
});
