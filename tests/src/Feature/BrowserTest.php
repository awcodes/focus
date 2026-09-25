<?php

declare(strict_types=1);

use Awcodes\Focus\Enums\FailureReason;
use Awcodes\Focus\Enums\Theme;
use Awcodes\Focus\Exceptions\CaptureException;
use Awcodes\Focus\Runtime\CaptureResult;
use Awcodes\Focus\Runtime\Runner;
use Awcodes\Focus\Screenshot;
use Awcodes\Focus\ScreenshotSuite;
use Playwright\Page\PageInterface;

beforeEach(function (): void {
    if (! browserAvailable()) {
        $this->markTestSkipped('Playwright is not installed. Run vendor/bin/playwright-install chromium.');
    }
});

/**
 * @param  list<Screenshot>  $screenshots
 * @return array{list<CaptureResult>, string}
 */
function capture(array $screenshots, ?Closure $configure = null): array
{
    $root = tempDirectory();
    $suite = ScreenshotSuite::make()->timeout(5_000)->screenshots($screenshots);

    if ($configure) {
        $configure($suite);
    }

    return [(new Runner)->run($suite, $suite->plan($root), fixtureServer()), $root];
}

function errors(array $results): array
{
    return array_map(fn (CaptureResult $result) => $result->error?->getMessage(), $results);
}

it('captures light and dark viewport screenshots behind the login', function (): void {
    [$results, $root] = capture([Screenshot::make('dashboard')->visit('/admin/page')]);

    expect(errors($results))->toBe([null, null]);

    $light = "{$root}/docs/assets/dashboard-light.png";
    $dark = "{$root}/docs/assets/dashboard-dark.png";

    expect(getimagesize($light))->toMatchArray([0 => 2880, 1 => 2000])
        ->and(pixel($light, 5, 5))->toBe([255, 255, 255])
        ->and(pixel($dark, 5, 5))->toBe([17, 17, 17]);
});

it('frames a focus subject with padding and minimum size at the output scale', function (): void {
    [$results, $root] = capture([
        Screenshot::make('card')->visit('/admin/page')->focus('[data-focus="card"]')->padding(10)->themes([Theme::Light]),
        Screenshot::make('tiny')->visit('/admin/page')->focus('[data-focus="tiny"]')->minSize(400, 200)->scale(1)->themes([Theme::Light]),
    ]);

    expect(errors($results))->toBe([null, null]);

    [$width, $height] = getimagesize("{$root}/docs/assets/card-light.png");
    expect($width)->toBe((300 + 40 + 2 + 20) * 2)
        ->and(getimagesize("{$root}/docs/assets/tiny-light.png"))->toMatchArray([0 => 400, 1 => 200]);
});

it('captures regions beyond the viewport and full pages', function (): void {
    [$results, $root] = capture([
        Screenshot::make('footer')->visit('/admin/page')->focus('[data-focus="footer"]')->scale(1)->themes([Theme::Light]),
        Screenshot::make('full')->visit('/admin/page')->fullPage()->scale(1)->themes([Theme::Light]),
    ]);

    expect(errors($results))->toBe([null, null])
        ->and(getimagesize("{$root}/docs/assets/full-light.png")[1])->toBeGreaterThan(3000);
});

it('waits for late network content before capturing', function (): void {
    $text = null;

    capture([
        Screenshot::make('late')
            ->visit('/admin/page')
            ->beforeCapture(function (PageInterface $page) use (&$text): void {
                $text = $page->locator('[data-focus="late"]')->textContent();
            })
            ->themes([Theme::Light]),
    ]);

    expect($text)->toBe('Loaded late');
});

it('freezes the clock, locale, and timezone', function (): void {
    $values = null;

    capture([
        Screenshot::make('clock')
            ->visit('/admin/page')
            ->beforeCapture(function (PageInterface $page) use (&$values): void {
                $values = $page->evaluate('() => [document.querySelector("[data-focus=clock]").textContent, navigator.language, Intl.DateTimeFormat().resolvedOptions().timeZone]');
            })
            ->themes([Theme::Light]),
    ], fn (ScreenshotSuite $suite) => $suite->freezeTime('2030-05-01 08:30:00')->timezone('Europe/Paris')->locale('fr-FR'));

    expect($values)->toBe(['2030-05-01T06:30:00.000Z', 'fr-FR', 'Europe/Paris']);
});

it('runs interactions and lifecycle callbacks in order', function (): void {
    $calls = [];
    $record = function (string $name) use (&$calls): Closure {
        return function (PageInterface $page) use (&$calls, $name): void {
            $calls[] = $name;
        };
    };

    [$results, $root] = capture([
        Screenshot::make('modal')
            ->visit('/admin/page')
            ->before($record('before'))
            ->click('[data-focus-action="open"]')
            ->fill('#search', 'hello')
            ->select('#group', 'b')
            ->press('Tab', '#search')
            ->hover('#title')
            ->waitFor('[data-focus="modal"]')
            ->ready($record('ready'))
            ->beforeCapture($record('beforeCapture'))
            ->after($record('after'))
            ->focus('[data-focus="modal"]')
            ->themes([Theme::Light]),
    ], fn (ScreenshotSuite $suite) => $suite->beforeEach($record('beforeEach')));

    expect(errors($results))->toBe([null])
        ->and($calls)->toBe(['beforeEach', 'before', 'ready', 'beforeCapture', 'after'])
        ->and(file_exists("{$root}/docs/assets/modal-light.png"))->toBeTrue();
});

it('masks dynamic content and removes the mask afterwards', function (): void {
    $remaining = null;

    [$results, $root] = capture([
        Screenshot::make('masked')
            ->visit('/admin/page')
            ->mask('[data-focus="card"]')
            ->maskColor('#00FF00')
            ->focus('[data-focus="card"]')
            ->padding(0)
            ->scale(1)
            ->after(function (PageInterface $page) use (&$remaining): void {
                $remaining = $page->evaluate('() => document.querySelectorAll("focus-mask").length');
            })
            ->themes([Theme::Light]),
    ]);

    expect(errors($results))->toBe([null])
        ->and(pixel("{$root}/docs/assets/masked-light.png", 50, 20))->toBe([0, 255, 0])
        ->and($remaining)->toBe(0);
});

it('reports distinct, actionable failures', function (): void {
    [$results] = capture([
        Screenshot::make('missing')->visit('/admin/page')->focus('[data-focus="nope"]'),
        Screenshot::make('dup')->visit('/admin/page')->focus('[data-focus="dup"]'),
        Screenshot::make('hidden')->visit('/admin/page')->focus('[data-focus="hidden"]'),
        Screenshot::make('not-found-page')->visit('/admin/missing'),
        Screenshot::make('bad-click')->visit('/admin/page')->click('[data-focus-action="nope"]'),
        Screenshot::make('throws')->visit('/admin/page')->before(fn () => throw new LogicException('boom')),
    ], fn (ScreenshotSuite $suite) => $suite->themes([Theme::Light])->timeout(1_000));

    expect(array_map(fn (CaptureResult $result) => $result->error?->reason, $results))->toBe([
        FailureReason::SelectorNotFound,
        FailureReason::SelectorAmbiguous,
        FailureReason::SelectorHidden,
        FailureReason::Navigation,
        FailureReason::SelectorNotFound,
        FailureReason::Callback,
    ])->and($results[0]->error->selector)->toBe('[data-focus="nope"]')
        ->and($results[0]->error->url)->toEndWith('/admin/page')
        ->and($results[1]->error->getMessage())->toContain('matched 2 elements');
});

it('fails the run when authentication fails', function (): void {
    capture(
        [Screenshot::make('a')->visit('/admin/page')],
        fn (ScreenshotSuite $suite) => $suite->login(password: 'wrong'),
    );
})->throws(CaptureException::class, 'did not leave the login page');

it('skips authentication when disabled', function (): void {
    [$results] = capture(
        [Screenshot::make('public')->visit('/public')->themes([Theme::Light])],
        fn (ScreenshotSuite $suite) => $suite->withoutLogin(),
    );

    expect(errors($results))->toBe([null]);
});

it('produces identical pixels on repeated runs', function (): void {
    $screenshot = fn () => Screenshot::make('stable')->visit('/admin/page')->mask('[data-focus="late"]')->themes([Theme::Dark]);

    [, $first] = capture([$screenshot()]);
    [, $second] = capture([$screenshot()]);

    expect(md5_file("{$first}/docs/assets/stable-dark.png"))->toBe(md5_file("{$second}/docs/assets/stable-dark.png"));
});
