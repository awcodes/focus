---
title: Workbench integration
description: Connect Focus to a Laravel Workbench application with stable selectors, deterministic fixtures, and authentication.
---

# Workbench integration

Focus integrates with the Workbench application a package already has. It does not replace it or add a second screenshot-specific application.

## The server

With no base URL configured, Focus starts the Workbench itself: it runs `vendor/bin/testbench serve` on a free local port, waits for it to respond, captures, and then stops only the server it started. Nothing else is required:

```bash
composer focus
```

Focus does not run `composer build`. The Workbench must already be built, which `composer serve` or `composer build` does. Rebuild after pulling plugin changes; otherwise screenshots show stale published assets or old fixture data.

Composer operations can also reset the Workbench. In the awcodes Workbench setup, `post-autoload-dump` runs `testbench package:purge-skeleton`, so `composer install`, `composer require`, or `composer dump-autoload` delete the Workbench database. Run `composer build` again before generating screenshots.

To use a server that is already running, for example `composer serve` in another terminal, pass a base URL:

```bash
vendor/bin/focus --base-url=http://127.0.0.1:8000
```

or set it in the manifest:

```php
return ScreenshotSuite::make()
    ->baseUrl('http://127.0.0.1:8000')
    ->screenshots([...]);
```

`--base-url` takes precedence over `baseUrl()`. Focus checks that something responds there before starting the browser.

## Authentication

Authentication is set up once per run, not in each screenshot. By default Focus follows the Workbench convention: it opens `/admin/login`, fills the email and password fields, submits the form, and reuses the signed-in session for every screenshot. The default credentials are:

```text
Email: test@example.com
Password: password
```

Focus only uses the application's normal login form; it adds no authentication bypass. If the application redirects away from the login page because you are already signed in, Focus continues.

Use different credentials or a different login path:

```php
->login('docs@example.com', 'secret', '/app/login')
```

Skip authentication entirely for public pages:

```php
->withoutLogin()
```

Or sign in yourself. The callback receives the Playwright page and the base URL, and the session it establishes is reused:

```php
use Playwright\Page\PageInterface;

->authenticateUsing(function (PageInterface $page, string $baseUrl): void {
    $page->goto("{$baseUrl}/login");
    $page->locator('#username')->fill('docs');
    $page->locator('#password')->fill('secret');
    $page->locator('button[type="submit"]')->click();
    $page->waitForURL("{$baseUrl}/dashboard");
})
```

If authentication fails, the run stops before any capture with an **Authentication failed** error.

## Stable selectors

Prefer dedicated automation hooks over Filament's internal `.fi-*` classes or positional selectors, which change between Filament versions:

```html
<div data-focus="mason-editor">...</div>
<button data-focus-action="add-brick">...</button>
<div data-focus-ready>...</div>
<span data-focus-mask>...</span>
```

| Attribute | Use |
|---|---|
| `data-focus="name"` | A subject for `focus()` |
| `data-focus-action="name"` | Something to click, hover, or press before capture |
| `data-focus-ready` | Present once a region has finished rendering, for `waitFor()` |
| `data-focus-mask` | Dynamic content to cover with `mask()` |

These attributes are a contract between the Workbench and `focus.php`. Adding them to your package views is harmless, since they do nothing on their own, and it keeps screenshots working when markup changes.

A `focus()` selector must match exactly one element. If a hook appears more than once on a page, make it more specific.

## Deterministic fixtures

Focus fixes the browser's clock, locale, and timezone, but it cannot control what the server renders. For stable screenshots, the Workbench fixtures should be deterministic:

- Seed known records at known URLs, such as `/admin/pages/1/edit`.
- Use fixed strings for content that appears in screenshots, or seed Faker with a fixed value.
- Avoid `now()` in fixtures that render dates; use fixed dates instead.
- Mask what you cannot fix, such as avatars fetched from external services.

## Other Laravel applications

Nothing in the capture engine depends on Filament. For a non-Filament application, point Focus at it with `--base-url` or `baseUrl()` and configure `login()`, `authenticateUsing()`, or `withoutLogin()` to suit.
