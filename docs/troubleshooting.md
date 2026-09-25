---
title: Troubleshooting
description: Diagnose common Focus failures by category, from an unavailable Workbench to unstable screenshots.
---

# Troubleshooting

Every failure names a category. Start with the category, then the message beneath it.

## Workbench unavailable

Focus could not reach the application.

- **"vendor/bin/testbench was not found"**: run `composer install`, or pass `--base-url` to use an application that is already running.
- **"Workbench exited before it was ready"**: the Workbench failed to boot. Run `composer serve` to see the error directly; the output Focus captured is printed below the message.
- **"Nothing responded at …"**: the `--base-url` or `baseUrl()` server is not running, or is on a different port.

## Authentication failed

The run stops before any capture.

- **"The login page returned HTTP 404"**: the application has no login at the configured path. Use `->login(path: '/your/login')`, or `->withoutLogin()` for public pages.
- **"Signing in as … did not leave the login page"**: the credentials were rejected. The Workbench is usually not seeded with the development account, often because a Composer command reset it; run `composer build`. For other credentials, configure `->login(...)`.
- **"The login page … has no … field"**: the form does not have a standard email field, password field, or submit button. Use `->authenticateUsing(...)` to sign in yourself.

## Navigation failed

A `visit()` returned an HTTP error. Check the URL against the Workbench routes and make sure the records it refers to are seeded. A `500` usually means the Workbench needs rebuilding with `composer build`.

## Selector not found, ambiguous, or hidden

- **Not found**: nothing matched within the timeout. Check the selector in the browser's developer tools. If the element is inside an iframe, wrap the steps in [`within()`](screenshots/interactions.md#inside-iframes).
- **Matched more than one element**: add a more specific `data-focus` hook. If the other matches are hidden, such as closed modals, add `:visible`. Focus never guesses which match you meant.
- **Hidden**: the element exists but is not visible or has no size. It may appear only after an interaction; add the `click()` or `hover()` that reveals it, followed by `waitFor()`.

Run the screenshot with `--headed --only=name` to watch what happens.

## Timed out

An interaction, `waitFor()`, or Playwright call inside a callback did not finish within the timeout. The default is 15 seconds; raise it for slow pages:

```php
return ScreenshotSuite::make()
    ->timeout(30_000)
    ->screenshots([...]);
```

## Callback failed

A `before()`, `beforeCapture()`, `after()`, `beforeEach()`, `ready()`, or custom step threw an exception. The message is the exception's own message.

## Browser error

Playwright or Chromium failed.

- **"Could not start Playwright"** or **"Could not launch Chromium"**: the Playwright server or browser is not installed, which is normal after a fresh `composer install`. Run `vendor/bin/focus init` or `vendor/bin/playwright-install chromium`. Focus needs Node.js 20 or later.

## Could not write output

Focus could not create the output directory or move the finished file into place. Check the `outputPath()` directory is writable.

## Warnings

Warnings do not fail a capture, but they are worth reading:

- **"The page was still changing … captured anyway"**: something kept changing the page, such as polling or a looping animation with `allowAnimations()`. Add a `waitFor()` or `ready()` step for the state you want.
- **"The framed region was larger than the document and was clamped"**: `minSize()` or `padding()` asked for more than the page contains, so the capture is smaller than requested.
- **"Every theme produced an identical image"**: the page has no dark mode. Restrict it with `->themes([Theme::Light])`.

## Screenshots look wrong

- **Unstyled or outdated UI**: the Workbench's published assets are stale. Run `composer build` in the package repository after pulling changes.
- **Content changes on every run**: fixtures use random Faker data or `now()`. Make them deterministic, or `mask()` the affected elements. See [Workbench integration](workbench.md#deterministic-fixtures).
- **Tooltips, hover styles, or focus rings**: Focus clears these before capture unless the last pointer step is a `hover()` or the screenshot uses `keepInteractionState()`. Anything set in a `beforeCapture()` callback is kept.
- **Wrong theme inside an embedded preview**: the iframe controls its own colour scheme. The browser reports the requested theme, but the embedded document decides how to render it.
- **Small differences between machines**: font rendering differs across operating systems. Regenerate from one consistent environment; see [Themes and rendering stability](screenshots/themes-and-stability.md#cross-platform-differences).
