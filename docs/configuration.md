---
title: Configuration
description: Configure Focus with a focus.php manifest, suite-wide settings, and setting precedence.
---

# Configuration

Focus has no Laravel configuration file. Everything lives in a `focus.php` manifest at the root of your repository, because the repository, not an application, owns its screenshots.

## The manifest

`focus.php` returns a `ScreenshotSuite`:

```php
<?php

use Awcodes\Focus\Screenshot;
use Awcodes\Focus\ScreenshotSuite;

return ScreenshotSuite::make()
    ->screenshots([
        Screenshot::make('dashboard')
            ->visit('/admin')
            ->viewport(),
    ]);
```

To use a different file, pass `--config`:

```bash
vendor/bin/focus --config=custom-focus.php
```

Focus validates the whole manifest before opening a browser and reports every problem at once, with the manifest line where it can. These are errors:

- a screenshot name that is not lowercase kebab-case (`^[a-z0-9]+(-[a-z0-9]+)*$`), such as `Editor` or `brick_picker`;
- two screenshots with the same name;
- a screenshot that never calls `visit()`;
- more than one capture mode on a screenshot (see [Capture modes](screenshots/capture-modes.md));
- `padding()` or `minSize()` on a screenshot that does not use `focus()`;
- invalid arguments, such as a negative padding or a `minSize()` width without a height.

## Suite settings

Most settings can be set on the suite for every screenshot, or on an individual screenshot:

| Method | Purpose | Default |
|---|---|---|
| `themes([...])` | Themes to capture | `[Theme::Light, Theme::Dark]` |
| `viewportSize(...)` | Browser viewport | `1440x1000` (`Viewport::Desktop`) |
| `padding(int)` | Context around a `focus()` subject, in CSS pixels | `32` |
| `minSize(...)` | Minimum `focus()` crop | none |
| `scale(int\|float)` | Output pixel density | `2` |
| `allowAnimations()` | Keep CSS animations and transitions | animations disabled |
| `locale(string)` | Browser locale | `en-US` |
| `timezone(string)` | Browser timezone | `UTC` |
| `freezeTime(...)` | Fixed browser clock, or `false` for the real clock | `2026-01-01 09:00:00` |
| `mask(...)` | Selectors to cover at capture time | none |
| `maskColor(string)` | Mask fill colour | `#FF00FF` |
| `keepInteractionState()` | Keep hover and focus left by interactions | cleared before capture |

These settings are suite-level only:

| Method | Purpose | Default |
|---|---|---|
| `outputPath(string)` | Where PNGs are written, relative to the repository root unless absolute | `docs/assets` |
| `baseUrl(string)` | Use an already-running application instead of starting Workbench | Focus starts Workbench |
| `login(...)` | Sign in through a login form | Workbench development account |
| `withoutLogin()` | Skip authentication | — |
| `authenticateUsing(...)` | Custom authentication | — |
| `reuseSession(bool)` | Reuse the previous run's signed-in session with `login()` | `true` |
| `timeout(int)` | Timeout in milliseconds for interactions and selectors | `15000` |
| `beforeEach(Closure)` | Callback before every screenshot | — |

Authentication and the server are covered in [Workbench integration](workbench.md).

## Setting precedence

Every setting resolves in the same order, most specific first:

```text
Screenshot → ScreenshotSuite → package default
```

For example, a suite that sets `->scale(1)` produces 1× images, except for a screenshot that sets `->scale(2)` itself:

```php
return ScreenshotSuite::make()
    ->scale(1)
    ->themes([Theme::Light])
    ->screenshots([
        Screenshot::make('overview')->visit('/admin'),                 // 1×, light
        Screenshot::make('detail')->visit('/admin')->scale(2)          // 2×, light
            ->focus('[data-focus="detail"]'),
    ]);
```

Masks are the exception: suite masks and screenshot masks are combined rather than replaced.

Suite-level `padding()` and `minSize()` apply only to `focus()` screenshots and are ignored for `viewport()` and `fullPage()` ones.

CLI options take precedence over the manifest where they overlap: `--base-url` overrides `baseUrl()`. The `--only` and `--theme` filters only narrow what the manifest defines; `--theme=dark` never produces a dark capture for a screenshot restricted to `Theme::Light`.

## Output location

Screenshots are written to `docs/assets` by default. Change it at suite level:

```php
return ScreenshotSuite::make()
    ->outputPath('resources/screenshots')
    ->screenshots([...]);
```

The file naming scheme, atomic writes, and orphan handling are covered in [CLI](cli.md#output-files).
