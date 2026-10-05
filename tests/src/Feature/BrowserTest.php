<?php

declare(strict_types=1);

use Awcodes\Focus\Authentication\FormLogin;
use Awcodes\Focus\Authentication\SessionCache;
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
function capture(array $screenshots, ?Closure $configure = null, ?SessionCache $sessions = null): array
{
    $root = tempDirectory();
    $suite = ScreenshotSuite::make()->timeout(5_000)->screenshots($screenshots);

    if ($configure) {
        $configure($suite);
    }

    return [(new Runner(sessions: $sessions))->run($suite, $suite->plan($root), fixtureServer()), $root];
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
        fn (ScreenshotSuite $suite) => $suite->authenticateUsing(new FormLogin(password: 'wrong', timeout: 1_000)),
    );
})->throws(CaptureException::class, 'did not leave the login page');

it('explains a rate-limited login', function (): void {
    capture(
        [Screenshot::make('a')->visit('/admin/page')],
        fn (ScreenshotSuite $suite) => $suite->authenticateUsing(new FormLogin(password: 'throttle', timeout: 1_000)),
    );
})->throws(CaptureException::class, 'rate-limiting sign-in attempts');

it('reuses the previous run\'s session instead of signing in again', function (): void {
    $sessions = SessionCache::for(tempDirectory(), new FormLogin, tempDirectory());
    $screenshot = fn () => Screenshot::make('a')->visit('/admin/page')->themes([Theme::Light]);

    $before = fixtureLogins();
    [$first] = capture([$screenshot()], sessions: $sessions);
    [$second] = capture([$screenshot()], sessions: $sessions);

    expect(errors($first))->toBe([null])
        ->and(errors($second))->toBe([null])
        ->and(fixtureLogins() - $before)->toBe(1)
        ->and($sessions->load())->not->toBeNull();
});

it('signs in again when the saved session is no longer valid', function (): void {
    $sessions = SessionCache::for(tempDirectory(), new FormLogin, tempDirectory());
    $screenshot = fn () => Screenshot::make('a')->visit('/admin/page')->themes([Theme::Light]);

    capture([$screenshot()], sessions: $sessions);
    invalidateFixtureSessions();

    $before = fixtureLogins();
    [$results] = capture([$screenshot()], sessions: $sessions);

    expect(errors($results))->toBe([null])
        ->and(fixtureLogins() - $before)->toBe(1);
});

it('does not reuse sessions for custom authentication', function (): void {
    $sessions = SessionCache::for(tempDirectory(), new FormLogin, tempDirectory());

    [$results] = capture(
        [Screenshot::make('public')->visit('/public')->themes([Theme::Light])],
        fn (ScreenshotSuite $suite) => $suite->authenticateUsing(fn (PageInterface $page, string $baseUrl) => $page->goto("{$baseUrl}/public")),
        $sessions,
    );

    expect(errors($results))->toBe([null])
        ->and(file_exists($sessions->path))->toBeFalse();
});

it('forgets a saved session when signing in fails', function (): void {
    $sessions = SessionCache::for(tempDirectory(), new FormLogin, tempDirectory());
    $sessions->save(['cookies' => [], 'origins' => []]);

    expect(fn () => capture(
        [Screenshot::make('a')->visit('/admin/page')],
        fn (ScreenshotSuite $suite) => $suite->authenticateUsing(new FormLogin(password: 'wrong', timeout: 1_000)),
        $sessions,
    ))->toThrow(CaptureException::class);

    expect(file_exists($sessions->path))->toBeFalse();
});

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

it('frames a subject inside an iframe', function (): void {
    [$results, $root] = capture([
        Screenshot::make('framed')
            ->visit('/admin/frame')
            ->within('#preview', fn (Screenshot $screenshot) => $screenshot->focus('[data-focus="panel"]'))
            ->padding(0)
            ->scale(1)
            ->themes([Theme::Light]),
    ]);

    expect(errors($results))->toBe([null])
        ->and(getimagesize("{$root}/docs/assets/framed-light.png"))->toMatchArray([0 => 200, 1 => 100])
        ->and(pixel("{$root}/docs/assets/framed-light.png", 100, 50))->toBe([99, 102, 241]);
});

it('runs interactions inside an iframe', function (): void {
    [$results, $root] = capture([
        Screenshot::make('popup')
            ->visit('/admin/frame')
            ->within('#preview', fn (Screenshot $screenshot) => $screenshot
                ->click('#open')
                ->waitFor('#popup')
                ->focus('#popup'))
            ->padding(0)
            ->scale(1)
            ->themes([Theme::Light]),
    ]);

    expect(errors($results))->toBe([null])
        ->and(getimagesize("{$root}/docs/assets/popup-light.png"))->toMatchArray([0 => 200, 1 => 100]);
});

it('reports iframe selector failures with the frame', function (): void {
    [$results] = capture([
        Screenshot::make('missing')->visit('/admin/frame')->within('#preview', fn (Screenshot $s) => $s->focus('#nope')),
        Screenshot::make('dup')->visit('/admin/frame')->within('#preview', fn (Screenshot $s) => $s->focus('.dup')),
        Screenshot::make('hidden')->visit('/admin/frame')->within('#preview', fn (Screenshot $s) => $s->click('[data-focus="hidden"]')),
    ], fn (ScreenshotSuite $suite) => $suite->themes([Theme::Light])->timeout(1_000));

    expect(array_map(fn (CaptureResult $result) => $result->error?->reason, $results))->toBe([
        FailureReason::SelectorNotFound,
        FailureReason::SelectorAmbiguous,
        FailureReason::SelectorHidden,
    ])->and($results[0]->error->getMessage())->toBe('focus(#nope) in #preview matched no elements.')
        ->and($results[2]->error->getMessage())->toContain('click([data-focus="hidden"]) in #preview');
});

/**
 * @return array{focused: bool, hovered: bool}
 */
function interactionState(Screenshot $screenshot, string $selector = '[data-focus-action="open"]'): array
{
    $state = null;

    capture([
        $screenshot->beforeCapture(function (PageInterface $page) use (&$state, $selector): void {
            $state = $page->evaluate('(selector) => ({ focused: document.activeElement === document.querySelector(selector), hovered: document.querySelector(selector).matches(":hover") })', $selector);
        })->themes([Theme::Light]),
    ]);

    return $state;
}

it('clears the pointer and keyboard focus before capture', function (): void {
    expect(interactionState(Screenshot::make('reset')->visit('/admin/page')->click('[data-focus-action="open"]')))
        ->toBe(['focused' => false, 'hovered' => false]);
});

it('keeps a deliberate hover', function (): void {
    expect(interactionState(Screenshot::make('hovered')->visit('/admin/page')->click('#title')->hover('[data-focus-action="open"]')))
        ->toBe(['focused' => false, 'hovered' => true]);
});

it('keeps interaction state when asked', function (): void {
    expect(interactionState(Screenshot::make('kept')->visit('/admin/page')->click('[data-focus-action="open"]')->keepInteractionState()))
        ->toBe(['focused' => true, 'hovered' => true]);
});

it('clears focus inside iframes', function (): void {
    $focused = null;

    capture([
        Screenshot::make('frame-focus')
            ->visit('/admin/frame')
            ->within('#preview', fn (Screenshot $screenshot) => $screenshot->click('#open'))
            ->beforeCapture(function (PageInterface $page) use (&$focused): void {
                $focused = $page->evaluate('() => document.getElementById("preview").contentDocument.activeElement.id');
            })
            ->themes([Theme::Light]),
    ]);

    expect($focused)->toBe('');
});

it('loads lazy images before capture, in view and below the fold', function (): void {
    $loaded = null;

    [$results] = capture([
        Screenshot::make('lazy')
            ->visit('/admin/lazy')
            ->beforeCapture(function (PageInterface $page) use (&$loaded): void {
                $loaded = $page->evaluate('() => ["top", "bottom"].map((id) => document.getElementById(id).naturalWidth > 0)');
            })
            ->fullPage()
            ->themes([Theme::Light]),
    ]);

    expect(errors($results))->toBe([null])
        ->and($loaded)->toBe([true, true]);
});

it('hides elements at capture time without moving the layout', function (): void {
    $shot = fn (string $name) => Screenshot::make($name)
        ->visit('/admin/page')
        ->focus('[data-focus="card"]')
        ->padding(0)
        ->scale(1)
        ->themes([Theme::Light]);

    $styleRemoved = null;

    [$results, $root] = capture([
        $shot('visible'),
        $shot('hidden')
            ->hide('[data-focus="card"]')
            ->after(function (PageInterface $page) use (&$styleRemoved): void {
                $styleRemoved = $page->evaluate('() => document.getElementById("focus-hide") === null');
            }),
    ]);

    expect(errors($results))->toBe([null, null])
        ->and(getimagesize("{$root}/docs/assets/hidden-light.png"))->toBe(getimagesize("{$root}/docs/assets/visible-light.png"))
        ->and(pixel("{$root}/docs/assets/visible-light.png", 0, 50))->not->toBe([255, 255, 255])
        ->and(pixel("{$root}/docs/assets/hidden-light.png", 0, 50))->toBe([255, 255, 255])
        ->and($styleRemoved)->toBeTrue();
});

/**
 * A solid-colour PNG fixture in a temporary directory.
 *
 * @param  array{int, int, int}  $rgb
 */
function colorFixture(array $rgb): string
{
    $path = tempDirectory() . '/fixture.png';
    $image = imagecreatetruecolor(8, 8);
    imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));
    imagepng($image, $path);

    return $path;
}

it('answers remote requests from fixtures and the built-in ui-avatars.com stand-in', function (): void {
    $blue = colorFixture([0, 0, 255]);

    [$results, $root] = capture(
        [Screenshot::make('avatars')->visit('/public/avatars')->scale(1)->themes([Theme::Light])],
        fn (ScreenshotSuite $suite) => $suite->withoutLogin()->fixture('https://www.gravatar.com/avatar/**', $blue),
    );

    $path = "{$root}/docs/assets/avatars-light.png";

    expect(errors($results))->toBe([null])
        ->and(pixel($path, 2, 2))->toBe([255, 0, 0])
        ->and(pixel($path, 102, 2))->toBe([0, 0, 255]);
});

it('warns about remote requests that loaded over the network, unless allowed', function (): void {
    $screenshot = fn () => Screenshot::make('avatars')->visit('/public/avatars')->scale(1)->themes([Theme::Light]);
    $origin = 'http://localhost:' . parse_url(fixtureServer(), PHP_URL_PORT);

    [$warned] = capture([$screenshot()], fn (ScreenshotSuite $suite) => $suite->withoutLogin());
    [$allowed] = capture([$screenshot()], fn (ScreenshotSuite $suite) => $suite->withoutLogin()->allowRemote("{$origin}/**"));

    expect(implode(' ', $warned[0]->warnings))
        ->toContain("Loaded {$origin}/img/green from {$origin} over the network")
        ->toContain('https://www.gravatar.com')
        ->not->toContain('ui-avatars.com')
        ->and(implode(' ', $allowed[0]->warnings))->not->toContain($origin);
});

it('fails a capture whose fixture file is missing', function (): void {
    [$results, $root] = capture(
        [Screenshot::make('avatars')->visit('/public/avatars')->themes([Theme::Light])],
        fn (ScreenshotSuite $suite) => $suite->withoutLogin()->fixture('https://www.gravatar.com/avatar/**', '/nowhere/avatar.png'),
    );

    expect($results[0]->error?->reason)->toBe(FailureReason::Fixture)
        ->and($results[0]->error?->getMessage())->toContain('/nowhere/avatar.png')
        ->and(file_exists("{$root}/docs/assets/avatars-light.png"))->toBeFalse();
});
