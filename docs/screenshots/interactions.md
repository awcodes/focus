---
title: Interactions, readiness, and callbacks
description: Prepare UI state with browser interactions, wait for readiness, mask dynamic content, and use Playwright directly.
---

# Interactions, readiness, and callbacks

Many screenshots show a state the page does not load in: an open modal, a filled form, a hovered menu. Screenshots record a sequence of steps that run in order after navigation.

## Interactions

```php
Screenshot::make('brick-picker')
    ->visit('/admin/pages/1/edit')
    ->click('[data-focus-action="add-brick"]')
    ->waitFor('[data-focus="brick-picker"]')
    ->focus('[data-focus="brick-picker"]');
```

| Method | Action |
|---|---|
| `visit(string $url)` | Navigate. Relative URLs resolve against the base URL; absolute URLs are used as-is. |
| `click(string $selector)` | Click an element. |
| `fill(string $selector, string $value)` | Replace an input's value. |
| `select(string $selector, string\|array $values)` | Choose one or more `<select>` options. |
| `hover(string $selector)` | Hover over an element. |
| `press(string $key, ?string $selector = null)` | Press a key on an element, or on the page when no selector is given. |
| `scrollIntoView(string $selector)` | Scroll an element into view. |
| `waitFor(string $selector, ?int $timeout = null)` | Wait until an element is visible. |
| `wait(int $milliseconds)` | Pause. An escape hatch; prefer `waitFor()` or `ready()`. |
| `ready(Closure $callback)` | Run a custom readiness check (see below). |

Every screenshot must call `visit()` at least once. Interaction selectors must match exactly one visible element: a selector that matches nothing, matches several elements, or matches a hidden element fails with a specific message.

A `visit()` that returns an HTTP error status fails as **Navigation failed**.

Steps operate on the top-level page. To interact with content inside an iframe, use a callback with Playwright's `frameLocator()`.

## Readiness

A finished navigation does not mean a Filament page is ready to photograph. After every step, and again before capture, Focus waits for the page to settle:

- the document has finished loading;
- web fonts have loaded;
- images that are not lazy-loaded have loaded;
- Alpine has initialised, when the page uses it;
- no `fetch` or `XMLHttpRequest` requests are in flight, which covers Livewire updates;
- the DOM has stopped changing for several animation frames;
- all of the above also hold inside same-origin iframes.

None of this is Filament-specific, so it works for any Laravel application. If a page is still changing after ten seconds, for example because of polling, Focus prints a warning and captures anyway. Add a `waitFor()` or `ready()` step if the result is incomplete.

For conditions Focus cannot infer, use `waitFor()` with a readiness hook:

```php
->waitFor('[data-focus-ready]')
```

or a callback that drives the page directly:

```php
use Playwright\Page\PageInterface;

->ready(function (PageInterface $page): void {
    $page->waitForFunction('() => window.chartsRendered === true');
})
```

## Masking dynamic content

Timestamps, avatars, counters, and other content your fixtures cannot fully control make screenshots change on every run. Cover them with a solid block at capture time:

```php
->mask('[data-focus-mask]', '.user-avatar')
->maskColor('#E5E7EB')
```

`mask()` accepts one or more selectors and works at suite and screenshot level; the two sets are combined. The default colour is `#FF00FF`. Masks are removed again immediately after the capture. Masks cover elements in the top-level document only, not inside iframes.

## Lifecycle callbacks

Callbacks receive the live Playwright page, `Playwright\Page\PageInterface`, so unusual behaviour does not need a Focus feature. Each screenshot runs in this order:

1. authentication (once per run, before any screenshot);
2. the suite's `beforeEach()` callbacks;
3. the screenshot's `before()` callbacks;
4. the screenshot's steps, in order, each followed by a readiness wait;
5. the screenshot's `beforeCapture()` callbacks;
6. the capture;
7. the screenshot's `after()` callbacks.

```php
use Playwright\Page\PageInterface;

return ScreenshotSuite::make()
    ->beforeEach(function (PageInterface $page): void {
        $page->setExtraHTTPHeaders(['X-Docs-Screenshot' => '1']);
    })
    ->screenshots([
        Screenshot::make('preview')
            ->visit('/admin/pages/1/edit')
            ->before(fn (PageInterface $page) => $page->setDefaultTimeout(30_000))
            ->beforeCapture(function (PageInterface $page): void {
                $page->frameLocator('iframe.preview')->locator('.block')->first()->hover();
            })
            ->focus('[data-focus="editor"]'),
    ]);
```

Every screenshot runs in a fresh browser context with its own page, so callbacks cannot leak state into the next screenshot. `beforeEach()` is the place for setup every screenshot shares.

An exception thrown in a callback fails that capture as **Callback failed**; other screenshots still run.

## Custom steps

For a reusable interaction, implement `Awcodes\Focus\Steps\Step` and add it with `step()`:

```php
use Awcodes\Focus\Steps\Step;
use Playwright\Page\PageInterface;

final class DismissNotifications implements Step
{
    public function run(PageInterface $page, string $baseUrl): void
    {
        $page->evaluate('() => document.querySelectorAll("[data-focus-dismiss]").forEach((el) => el.remove())');
    }

    public function describe(): string
    {
        return 'dismissNotifications()';
    }
}
```

```php
->step(new DismissNotifications)
```

`describe()` is used in error messages.
