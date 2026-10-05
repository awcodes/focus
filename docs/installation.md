---
title: Installation
description: Install Focus, its Playwright browser, and the composer focus script.
---

# Installation

## Compatibility

| Filament version | Package version |
|------------------|-----------------|
| 4.x & 5.x        | 1.x             |

Focus requires PHP 8.3 or later and Node.js 20 or later. It does not depend on Filament itself: it drives whatever application your Workbench serves, and targets Filament v4 and v5 Workbenches.

Focus uses [Playwright](https://playwright.dev) through the `playwright-php/playwright` package and captures with Chromium.

## Versioning

From 1.0, Focus follows [semantic versioning](https://semver.org). A breaking change to the public API or to the images a manifest produces needs a new major version.

### The public API

These are the stable parts of Focus:

- **The manifest classes:** `ScreenshotSuite`, `Screenshot` and `Card`, and their documented methods.
- **The enums:** `Theme`, `Viewport`, `Size`, `CaptureMode` and `FailureReason`.
- **The contracts:** `Steps\Step` for custom steps, `Authentication\Authenticator` for custom sign-in, and `Contracts\HasDimensions`.
- **The exceptions:** `CaptureException`, `FocusException` and `InvalidManifestException`.
- **The CLI:** the commands, their options, and their exit codes.
- **The output file names:** `{name}-{theme}.png` and `{name}-{size}-{theme}.png`.
- **The card template contract:** the `data-focus` keys, the `--focus-screenshot-N` CSS variables, and the `focus:canvas` meta tag.

Classes and methods marked `@internal` can change in any release.

### Defaults

A default that changes the pixels of an existing manifest's output counts as a breaking change. Examples are the viewport, scale, padding, frozen time and theme handling. New options, new warnings and new defaults for new options can arrive in minor releases.

### Playwright

Callbacks, custom steps and authenticators receive playwright-php's `Playwright\Page\PageInterface`. Focus requires playwright-php `^1.5`, so a new major version of playwright-php means a new major version of Focus.

Focus doesn't pin the Chromium version yet. Chromium comes from `vendor/bin/playwright-install`, and an update can change pixels without any change in Focus. The same is true of your operating system's fonts. Generate a repository's images on one machine, and regenerate them all after a browser or OS update. See [Cross-platform differences](screenshots/themes-and-stability.md#cross-platform-differences).

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
