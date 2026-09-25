<?php

declare(strict_types=1);

use Awcodes\Focus\Enums\CaptureMode;
use Awcodes\Focus\Screenshot;
use Awcodes\Focus\Steps\Visit;

it('records interaction steps in order', function (): void {
    $screenshot = Screenshot::make('picker')
        ->visit('/admin/pages/1/edit')
        ->click('[data-focus-action="add"]')
        ->fill('#search', 'hero')
        ->select('#group', 'content')
        ->hover('.brick')
        ->press('Escape')
        ->press('Enter', '#search')
        ->scrollIntoView('.footer')
        ->waitFor('[data-focus="picker"]')
        ->wait(100)
        ->ready(fn () => null);

    expect(array_map(fn ($step) => $step->describe(), $screenshot->getSteps()))->toBe([
        'visit(/admin/pages/1/edit)',
        'click([data-focus-action="add"])',
        'fill(#search)',
        'select(#group)',
        'hover(.brick)',
        'press(Escape)',
        'press(Enter, #search)',
        'scrollIntoView(.footer)',
        'waitFor([data-focus="picker"])',
        'wait(100ms)',
        'ready()',
    ])->and($screenshot->getUrl())->toBe('/admin/pages/1/edit');
});

it('defaults to a viewport capture', function (): void {
    expect(Screenshot::make('a')->getCaptureMode())->toBe(CaptureMode::Viewport);
});

it('records the focus subject', function (): void {
    $screenshot = Screenshot::make('a')->focus('[data-focus="editor"]');

    expect($screenshot->getCaptureMode())->toBe(CaptureMode::Focus)
        ->and($screenshot->getFocusSelector())->toBe('[data-focus="editor"]');
});

it('resolves visit urls against the base url', function (string $url, string $expected): void {
    expect(Visit::resolve($url, 'http://127.0.0.1:8000/'))->toBe($expected);
})->with([
    ['/admin', 'http://127.0.0.1:8000/admin'],
    ['admin/pages', 'http://127.0.0.1:8000/admin/pages'],
    ['https://example.com/x', 'https://example.com/x'],
]);

it('validates setting arguments eagerly', function (callable $call, string $message): void {
    expect(fn () => $call(Screenshot::make('a')))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'negative padding' => [fn ($s) => $s->padding(-1), 'padding()'],
    'zero scale' => [fn ($s) => $s->scale(0), 'scale()'],
    'empty themes' => [fn ($s) => $s->themes([]), 'themes()'],
    'string themes' => [fn ($s) => $s->themes(['dark']), 'themes()'],
    'bad timezone' => [fn ($s) => $s->timezone('Mars/Base'), 'timezone()'],
    'bad frozen time' => [fn ($s) => $s->freezeTime('not a date'), 'freezeTime()'],
    'negative wait' => [fn ($s) => $s->wait(-5), 'wait()'],
]);

it('scopes steps and focus to an iframe inside within()', function (): void {
    $screenshot = Screenshot::make('a')
        ->visit('/admin')
        ->within('iframe.preview', fn (Screenshot $s) => $s->click('.add')->waitFor('.modal')->focus('.modal'))
        ->click('.outside');

    expect(array_map(fn ($step) => $step->describe(), $screenshot->getSteps()))->toBe([
        'visit(/admin)',
        'click(.add) in iframe.preview',
        'waitFor(.modal) in iframe.preview',
        'click(.outside)',
    ])->and($screenshot->getFocusSelector())->toBe('.modal')
        ->and($screenshot->getFocusFrame())->toBe('iframe.preview');
});

it('rejects top-level-only methods inside within()', function (callable $call, string $method): void {
    expect(fn () => Screenshot::make('a')->within('iframe', $call))
        ->toThrow(InvalidArgumentException::class, "{$method} cannot be used inside within()");
})->with([
    'visit' => [fn (Screenshot $s) => $s->visit('/x'), 'visit()'],
    'mask' => [fn (Screenshot $s) => $s->mask('.x'), 'mask()'],
    'hide' => [fn (Screenshot $s) => $s->hide('.x'), 'hide()'],
    'nested within' => [fn (Screenshot $s) => $s->within('iframe', fn () => null), 'within()'],
]);

it('leaves the frame scope when within() ends, even after an exception', function (): void {
    $screenshot = Screenshot::make('a');

    try {
        $screenshot->within('iframe', fn () => throw new RuntimeException('boom'));
    } catch (RuntimeException) {
    }

    expect($screenshot->click('.x')->getSteps()[0]->describe())->toBe('click(.x)');
});
