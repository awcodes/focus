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

Hidden elements count as matches. Filament, for example, keeps closed modals in the page, so `.fi-modal-window` can match several elements while only one is open. Add Playwright's `:visible` pseudo-class to match only what is on screen:

```php
->focus('.fi-modal-window:visible')
```

A `visit()` that returns an HTTP error status fails as **Navigation failed**.

## Inside iframes

Steps and `focus()` search the top-level page by default. To reach content inside an iframe, such as an editor preview, scope them with `within()`:

```php
Screenshot::make('block-controls')
    ->visit('/admin/pages/1/edit')
    ->within('iframe.mason-iframe', fn (Screenshot $screenshot) => $screenshot
        ->click('[data-block-index="0"]')
        ->waitFor('.mason-block-controls')
        ->focus('.mason-block-controls'))
    ->minSize(400, 200);
```

The first argument is a selector that matches exactly one iframe. Inside the closure, `click()`, `fill()`, `select()`, `hover()`, `press()`, `scrollIntoView()`, `waitFor()`, and `focus()` all resolve inside that frame. A focused subject inside an iframe is framed in page coordinates, so padding and minimum size can include the page around the frame.

`visit()`, `mask()`, and nested `within()` calls are not allowed inside `within()`. Settings such as `padding()` can be called inside or outside it; they apply to the whole screenshot. Error messages name the frame, for example `focus(.modal) in iframe.mason-iframe matched no elements.`

## Clearing interaction state

Clicking leaves the pointer over the clicked element and keyboard focus on it, which shows up as hover styles, tooltips, and focus rings. Before capture, Focus clears both:

- the pointer moves off the page, unless the last pointer step was a `hover()`, which is kept because hovering is deliberate;
- the focused element is blurred, in the page and in every same-origin iframe.

This happens after the steps and before `beforeCapture()` callbacks, so a hover or focus set in `beforeCapture()` is kept.

To keep everything as the steps left it, for example to show a focused, filled input or a dropdown that closes when it loses focus:

```php
->keepInteractionState()
```

It works at screenshot and suite level.

## Readiness

A finished navigation does not mean a Filament page is ready to photograph. After every step, and again before capture, Focus waits for the page to settle:

- the document has finished loading;
- web fonts have loaded;
- images that are not lazy-loaded have loaded (lazy images are loaded too, just before capture; see below);
- Alpine has initialised, when the page uses it;
- no `fetch` or `XMLHttpRequest` requests are in flight, which covers Livewire updates;
- the DOM has stopped changing for several animation frames;
- all of the above also hold inside same-origin iframes.

Just before capture, Focus switches lazy-loaded images (`loading="lazy"`) to load immediately and waits for them. Images outside the viewport, in a focus region below the fold or on a full page, are captured loaded rather than blank. Remote images are loaded too. Focus answers Filament's default avatar from `ui-avatars.com` locally; serve other remote images with a fixture so they do not depend on the network. See [Remote requests](themes-and-stability.md#remote-requests).

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

Timestamps, counters, and other content your fixtures cannot fully control make screenshots change on every run. Cover them with a solid block at capture time:

```php
->mask('[data-focus-mask]', '.last-synced-at')
->maskColor('#E5E7EB')
```

`mask()` accepts one or more selectors and works at suite and screenshot level; the two sets are combined. The default colour is `#FF00FF`. Masks are removed again immediately after the capture. Masks cover elements in the top-level document only, not inside iframes.

## Hiding elements

Sometimes the problem is not changing content but neighbouring UI, such as form buttons just below a focused editor. Hide it at capture time:

```php
->hide('.fi-form-actions')
```

Hidden elements keep their space, because Focus applies `visibility: hidden` rather than removing them, so the layout and the framing do not move. `hide()` accepts one or more selectors and works at suite and screenshot level; the two sets are combined. The styles are removed again right after the capture. Like masks, `hide()` applies to the top-level document only.

Use `mask()` when the reader should see that something is there, such as a timestamp, and `hide()` when it is simply in the way.

## Lifecycle callbacks

Callbacks receive the live Playwright page, `Playwright\Page\PageInterface`, so unusual behaviour does not need a Focus feature. Each screenshot runs in this order:

1. authentication (once per run, before any screenshot);
2. the suite's `beforeEach()` callbacks;
3. the screenshot's `before()` callbacks;
4. the screenshot's steps, including `visit()`, in order, each followed by a readiness wait;
5. clearing interaction state (see above);
6. the screenshot's `beforeCapture()` callbacks;
7. the capture;
8. the screenshot's `after()` callbacks.

`before()` callbacks run before the first `visit()`, on a blank page. Use them for page-level setup such as timeouts, headers, or routes. For work on the loaded page, use a `ready()` step, which runs in order with the other steps.

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
