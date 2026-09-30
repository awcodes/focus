---
title: Viewport, padding, size, and scale
description: Control the browser viewport, focus padding, minimum crop size, output scale, and dimension presets.
---

# Viewport, padding, size, and scale

Five settings shape a screenshot, and they are deliberately independent:

- **Viewport** controls the browser window, and so the responsive layout.
- **Focus target** identifies the subject (see [Capture modes](capture-modes.md)).
- **Padding** controls how much context surrounds a focus subject.
- **Minimum size** prevents tiny focus captures.
- **Scale** controls output pixel density.

All sizes are logical (CSS) pixels. Scale is applied last.

## Viewport size

```php
->viewportSize(1440, 1000)
->viewportSize(Viewport::Mobile)
```

The default is 1440×1000. `viewportSize()` works at suite and screenshot level. It changes the layout the application renders: `Viewport::Mobile` shows your mobile layout.

Focus ships these presets in `Awcodes\Focus\Enums\Viewport`:

| Case | Dimensions |
|---|---|
| `Viewport::Desktop` | 1440×1000 |
| `Viewport::Tablet` | 768×1024 |
| `Viewport::Mobile` | 390×844 |

Presets describe dimensions only; they do not emulate touch input or a mobile user agent.

## Padding

```php
->padding(32)
```

Uniform padding around a `focus()` subject. The default is 32 pixels. Use `0` for a tight crop.

## Minimum size

```php
->minSize(400, 200)
->minSize(Size::OpenGraph)
```

When the padded subject is smaller than the minimum, Focus grows the region around it, keeping the subject centred. A small button becomes a useful capture with its surrounding context. There is no minimum by default.

A minimum is not an exact size or aspect ratio: a subject larger than the minimum produces a larger image.

Focus ships these presets in `Awcodes\Focus\Enums\Size`:

| Case | Dimensions |
|---|---|
| `Size::OpenGraph` | 1200×630 |
| `Size::Twitter` | 1200×675 |
| `Size::YouTube` | 1280×720 |
| `Size::GitHubSocial` | 1280×640 |
| `Size::Filament` | 2560×1440 |

## Scale

```php
->scale(2)
```

Scale sets the device pixel ratio, so a 400×200 region at `->scale(2)` produces an 800×400 PNG. It does not change the layout. The default is `2`, which gives sharp images on high-density displays. `Size::OpenGraph` at `->scale(2)` produces at least 2400×1260 pixels.

## Integer or preset arguments

`viewportSize()` and `minSize()` share the same signature:

```php
public function minSize(int | HasDimensions $width, ?int $height = null): static
```

An integer width requires a height; a preset forbids one. Dimensions must be positive integers. Mistakes are reported as manifest errors with the line number.

Passing a `Size` to `viewportSize()` works, but viewports and minimum sizes answer different questions, so keep `Viewport` presets for viewports and `Size` presets for crops.

## Custom presets

Presets are backed by the `Awcodes\Focus\Contracts\HasDimensions` contract, not a closed list. Define your own with any enum or class that implements it:

```php
use Awcodes\Focus\Contracts\HasDimensions;

enum DocsSize: string implements HasDimensions
{
    case Hero = 'hero';

    public function width(): int
    {
        return 1600;
    }

    public function height(): int
    {
        return 900;
    }
}
```

```php
->minSize(DocsSize::Hero)
```

The same contract works for custom viewport presets.
