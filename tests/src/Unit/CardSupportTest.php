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
