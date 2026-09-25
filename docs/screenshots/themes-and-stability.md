---
title: Themes and rendering stability
description: Capture light and dark themes and keep screenshots pixel-stable between runs.
---

# Themes and rendering stability

## Light and dark

Every screenshot is captured in both light and dark themes by default. Restrict a screenshot, or the whole suite, with the `Theme` enum:

```php
use Awcodes\Focus\Enums\Theme;

->themes([Theme::Light])
->themes([Theme::Light, Theme::Dark])
```

Focus switches themes at the browser level rather than clicking a theme switcher. Each capture's browser reports the matching `prefers-color-scheme`, and Focus sets Filament's stored `theme` preference to the same value before any page script runs. Filament panels therefore render in the requested theme whatever their theme switcher last saved.

`Theme` values (`light`, `dark`) are used in filenames and by the `--theme` option.

## Why stability matters

Screenshots are committed to git. Identical inputs should produce identical pixels, so a diff means the UI really changed. By default Focus:

- disables CSS animations and transitions, jumping them to their end state;
- hides the text caret and blinking cursors;
- asks the page for reduced motion;
- uses the `en-US` locale and `UTC` timezone;
- freezes the browser clock at `2026-01-01 09:00:00`.

## Animations

```php
->allowAnimations()
```

Keeps animations and transitions for a screenshot or a suite. Use it only when the animated state is what you want to show.

## Locale, timezone, and frozen time

```php
->locale('de-DE')
->timezone('Europe/Berlin')
->freezeTime('2026-06-01 14:30:00')
```

A `freezeTime()` string is interpreted in the resolved timezone; a `DateTimeInterface` is used as-is. Pass `false` to use the real clock:

```php
->freezeTime(false)
```

Freezing fixes the value of `Date` in the browser; timers keep running, so pages still load normally.

> [!NOTE]
> The browser clock does not affect dates rendered by the server, such as a record's `created_at`. Those belong to deterministic Workbench fixtures; see [Workbench integration](../workbench.md#deterministic-fixtures).

## Cross-platform differences

Font rendering and anti-aliasing differ between operating systems, so the same manifest produces slightly different pixels on macOS and Linux. That is expected noise, not a Focus bug. Regenerate a repository's assets from a consistent environment, typically one maintainer's machine, to keep diffs meaningful.
