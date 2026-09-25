---
title: Capture modes
description: Choose between focus, viewport, and full-page captures, and understand how focus framing works.
---

# Capture modes

Every screenshot has exactly one capture mode.

| Method | Captures |
|---|---|
| `focus('selector')` | A region framed around one element |
| `viewport()` | The visible browser viewport |
| `fullPage()` | The complete scrollable document |

A screenshot with no mode defaults to `viewport()`. Calling more than one of these on the same screenshot is a manifest error, reported before any browser work begins.

## Focus

`focus()` identifies the subject of the screenshot: what matters. Focus handles the crop.

```php
Screenshot::make('toolbar-action')
    ->visit('/admin/pages/1/edit')
    ->focus('[data-focus="toolbar-action"]')
    ->padding(24)
    ->minSize(400, 200);
```

To frame the capture, Focus:

1. waits for exactly one element to match the selector and become visible;
2. scrolls it into view;
3. measures its bounding box;
4. expands the box by `padding()` on every side;
5. grows it further, keeping the subject centred, until it meets `minSize()`;
6. shifts the region to stay inside the document;
7. captures that region at the configured [scale](dimensions.md#scale).

To frame a subject inside an iframe, call `focus()` inside [`within()`](interactions.md#inside-iframes).

Because the region is measured in document coordinates, a focus capture can be taller or wider than the browser viewport. Focus captures it from the full document rather than resizing the viewport, which would change the responsive layout.

## Viewport

`viewport()` captures exactly what the browser shows at the configured viewport size:

```php
Screenshot::make('dashboard')
    ->visit('/admin')
    ->viewport();
```

`viewport()` takes no arguments. Browser dimensions are set separately with [`viewportSize()`](dimensions.md#viewport-size).

## Full page

`fullPage()` captures the entire scrollable document:

```php
Screenshot::make('settings-page')
    ->visit('/admin/settings')
    ->fullPage();
```

## Rules for padding and minimum size

`padding()` and `minSize()` only affect `focus()` captures. Setting either on a `viewport()` or `fullPage()` screenshot is a manifest error. Suite-level values are ignored for those modes, so you can set suite-wide framing defaults without affecting whole-screen captures.

## Framing edge cases

| Situation | Behaviour |
|---|---|
| The selector matches nothing | The capture fails as **Selector not found**, naming the selector and URL. |
| The selector matches several elements | The capture fails and reports the count. Focus never silently picks the first; add a more specific `data-focus` hook, or `:visible` when the other matches are hidden. |
| The element exists but is hidden or has no size | The capture fails as **Selector hidden**, distinct from not found. |
| The region is larger than the viewport | It is captured from the full document; the viewport is not resized. |
| The region is larger than the document | It is clamped to the document. The run warns only when the subject itself or the `minSize()` does not fit; padding that runs past the edge, common at mobile widths, is silently trimmed. |
| The subject is near a document edge | The region shifts to stay in bounds rather than shrinking, so centring is best-effort. |
