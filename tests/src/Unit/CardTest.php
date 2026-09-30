<?php

declare(strict_types=1);

use Awcodes\Focus\Card;
use Awcodes\Focus\Enums\Size;
use Awcodes\Focus\Enums\Theme;

it('leaves settings unset until configured', function (): void {
    $card = Card::make('social');

    expect($card->getName())->toBe('social')
        ->and($card->getTemplate())->toBeNull()
        ->and($card->getScreenshots())->toBe([])
        ->and($card->getSizes())->toBeNull()
        ->and($card->getThemes())->toBeNull()
        ->and($card->getScale())->toBeNull()
        ->and($card->getTitle())->toBeNull()
        ->and($card->getDescription())->toBeNull()
        ->and($card->getValues())->toBe([]);
});

it('records its configuration', function (): void {
    $card = Card::make('social')
        ->template('social/two-up')
        ->screenshots(['editor', 'picker'])
        ->sizes([Size::OpenGraph, [1920, 1080]])
        ->themes([Theme::Light, Theme::Dark, Theme::Light])
        ->scale(1)
        ->title('Mason')
        ->description('Build pages from bricks.')
        ->with(['tagline' => 'Bricks', 'stars' => 1200, 'ratio' => 1.5]);

    expect($card->getTemplate())->toBe('social/two-up')
        ->and($card->getScreenshots())->toBe(['editor', 'picker'])
        ->and(array_map(fn ($size) => "{$size->width()}x{$size->height()}", $card->getSizes()))->toBe(['1200x630', '1920x1080'])
        ->and($card->getSizes()[0])->toBe(Size::OpenGraph)
        ->and($card->getThemes())->toBe([Theme::Light, Theme::Dark])
        ->and($card->getScale())->toBe(1.0)
        ->and($card->getTitle())->toBe('Mason')
        ->and($card->getDescription())->toBe('Build pages from bricks.')
        ->and($card->getValues())->toBe(['tagline' => 'Bricks', 'stars' => '1200', 'ratio' => '1.5']);
});

it('merges repeated with() calls', function (): void {
    $card = Card::make('social')->with(['a' => 'one', 'b' => 'two'])->with(['b' => 'three']);

    expect($card->getValues())->toBe(['a' => 'one', 'b' => 'three']);
});

it('accepts Stringable values', function (): void {
    $value = new class implements Stringable
    {
        public function __toString(): string
        {
            return 'v3';
        }
    };

    expect(Card::make('social')->with(['version' => $value])->getValues())->toBe(['version' => 'v3']);
});

it('rejects invalid template names', function (string $name): void {
    Card::make('social')->template($name);
})->with([
    'extension' => 'two-up.html',
    'traversal' => '../two-up',
    'uppercase' => 'TwoUp',
    'leading slash' => '/two-up',
    'trailing slash' => 'two-up/',
    'empty' => '',
])->throws(InvalidArgumentException::class, 'template() expects a lowercase kebab-case name');

it('rejects sizes listed twice, including a preset and its dimensions', function (): void {
    Card::make('social')->sizes([Size::OpenGraph, [1200, 630]]);
})->throws(InvalidArgumentException::class, 'sizes() lists [1200x630] more than once.');

it('rejects invalid sizes', function (mixed $sizes, string $message): void {
    expect(fn () => Card::make('social')->sizes($sizes))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'empty' => [[], 'sizes() requires at least one size.'],
    'not a pair' => [[[1200]], 'sizes() accepts presets such as Size::OpenGraph or [width, height] pairs.'],
    'strings' => [[['1200', '630']], 'sizes() accepts presets such as Size::OpenGraph or [width, height] pairs.'],
    'non-positive' => [[[0, 630]], 'sizes() dimensions must be positive integers, [0x630] given.'],
]);

it('rejects invalid themes and scale', function (): void {
    expect(fn () => Card::make('social')->themes([]))->toThrow(InvalidArgumentException::class, 'themes() requires at least one theme.')
        ->and(fn () => Card::make('social')->themes(['dark']))->toThrow(InvalidArgumentException::class, 'themes() only accepts')
        ->and(fn () => Card::make('social')->scale(0))->toThrow(InvalidArgumentException::class, 'scale() must be greater than zero, [0] given.');
});

it('rejects empty screenshot names', function (): void {
    Card::make('social')->screenshots(['editor', ' ']);
})->throws(InvalidArgumentException::class, 'screenshots() only accepts screenshot names.');

it('rejects invalid with() keys and values', function (array $values, string $message): void {
    expect(fn () => Card::make('social')->with($values))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'not kebab-case' => [['tagLine' => 'x'], 'with() keys must be lowercase kebab-case, [tagLine] given.'],
    'dotted' => [['screenshot.1' => 'x'], 'with() keys must be lowercase kebab-case, [screenshot.1] given.'],
    'list' => [['x'], 'with() keys must be lowercase kebab-case, [0] given.'],
    'title' => [['title' => 'x'], 'with() cannot set [title], which Focus fills itself. Use title() instead.'],
    'package' => [['package' => 'x'], 'with() cannot set [package], which Focus fills itself. It comes from composer.json.'],
    'screenshot' => [['screenshot' => 'x'], 'with() cannot set [screenshot], which Focus fills itself. Pass screenshots with screenshots().'],
    'array value' => [['tags' => ['a']], 'with() value for [tags] must be a string, number, or Stringable, array given.'],
    'null value' => [['tagline' => null], 'with() value for [tagline] must be a string, number, or Stringable, null given.'],
]);
