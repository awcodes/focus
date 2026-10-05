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

## Remote requests

Anything a page loads from another origin, such as an avatar from `ui-avatars.com` or `gravatar.com`, can change or fail between runs. Focus answers those requests from local files instead, so the page renders exactly as it would online but never reaches the network.

### Filament's default avatar

Filament's default avatar provider loads initials from `ui-avatars.com`. Focus answers those requests itself and draws the same initials on the same background, so top-bar captures keep a realistic avatar with no configuration and no network.

### Fixtures

Answer any other remote request with a file in your repository:

```php
ScreenshotSuite::make()
    ->fixture('https://www.gravatar.com/avatar/**', 'workbench/fixtures/avatar.png')
```

The first argument is a URL pattern matched against the whole URL, query string included; `*` and `**` both match any characters. The file is relative to the repository root unless absolute. Its extension sets the content type.

To choose a file per request, pass a closure that receives the URL and returns a path:

```php
->fixture('https://www.gravatar.com/avatar/**', function (string $url): string {
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    return 'workbench/fixtures/gravatar-' . ($query['d'] ?? 'mp') . '.png';
})
```

Fixtures are checked in the order they are defined, before the built-in `ui-avatars.com` stand-in, so a fixture for `https://ui-avatars.com/**` replaces it. A fixture whose file is missing fails the capture with **Fixture failed** rather than writing a broken image.

> [!TIP]
> Prefer a fixture to `hide()` or `mask()` for remote images. The page keeps its real markup and layout, and a package whose feature is the remote content, such as an avatar provider, can still show it.

### Reporting remote requests

After each capture, Focus warns about every remote URL that loaded over the network without a fixture, one warning per origin. Each one is a way for the image to change between runs. Serve it with `fixture()`, or accept it when the network is what you want:

```php
->allowRemote('https://fonts.bunny.net/**')
```

`allowRemote()` takes URL patterns in the same form as `fixture()`. Requests inside cross-origin iframes cannot be seen; Focus reports the iframe's own URL instead.

## Cross-platform differences

Font rendering and anti-aliasing differ between operating systems, so the same manifest produces slightly different pixels on macOS and Linux. That is expected noise, not a Focus bug. Regenerate a repository's assets from a consistent environment, typically one maintainer's machine, to keep diffs meaningful.

### System fonts

Pages styled with the operating system's font, such as Tailwind's default `-apple-system` stack, can differ from one run to the next on the same machine: Chromium on macOS picks the system font's optical size inconsistently for small text. Run Focus twice and compare the images to find out. Filament panels are not affected, because Filament serves its own Inter font.

If a page does differ, give it a bundled web font, such as the Inter files Filament already ships. That also removes the dependency on one machine's fonts. Pinning the optical size in the page's CSS works too:

```css
html {
    font-optical-sizing: none;
}
```
