---
title: Installation
description: Install Focus, its Playwright browser, and the composer focus script.
---

# Installation

## Compatibility

| Filament version | Package version |
|------------------|-----------------|
| 4.x & 5.x        | 1.x             |

Focus requires PHP 8.2 or later and Node.js 20 or later. It does not depend on Filament itself: it drives whatever application your Workbench serves, and targets Filament v4 and v5 Workbenches.

Focus uses [Playwright](https://playwright.dev) through the `playwright-php/playwright` package and captures with Chromium.

## Requiring the package

Focus is a development tool. Install it as a dev dependency:

```bash
composer require --dev awcodes/focus
```

## Scaffolding with `init`

Run `init` from the repository root:

```bash
vendor/bin/focus init
```

It does three things:

1. **Creates `focus.php`** from a starter manifest. An existing `focus.php` is never overwritten.
2. **Adds a `focus` Composer script** after asking for confirmation. An existing `focus` script is never overwritten.
3. **Installs the Playwright server and Chromium** by running the Playwright installer. If that fails, it prints the command to run manually.

`init` is safe to re-run: every step checks for existing work first. Pass `--skip-browsers` to skip the browser installation.

## The Composer script

`init` adds this script to `composer.json`:

```json
{
    "scripts": {
        "focus": [
            "Composer\\Config::disableProcessTimeout",
            "focus"
        ]
    }
}
```

`disableProcessTimeout` matters. Composer stops scripts after 300 seconds by default, and a full suite of screenshots can take longer than that. With the script in place, regenerate every screenshot with:

```bash
composer focus
```

Arguments after `--` are passed through, for example `composer focus -- --only=editor`.

## Installing the browser manually

The Playwright server's Node dependencies and the Chromium download live inside `vendor/`, so a fresh `composer install` (including in a new clone) needs them installed again:

```bash
vendor/bin/playwright-install chromium
```

Re-running `vendor/bin/focus init` does the same thing.

> [!NOTE]
> Focus is not part of `composer test`, and its screenshots are not tests. It is meant for local development; running it in CI is not a v1 goal.
