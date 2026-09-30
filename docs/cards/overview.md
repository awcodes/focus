---
title: Cards
description: Render share images, such as Open Graph cards and GitHub social previews, from HTML templates and the screenshots Focus captures.
---

# Cards

A card is a share image: an Open Graph card, a GitHub social preview, a README banner, or a video thumbnail. Focus renders cards from HTML templates, fills them with your package's name, description, and install command, and places screenshots from the same manifest into them.

Because cards use the screenshots captured in the same run, they stay current: when the UI changes, one `composer focus` refreshes the documentation screenshots and the share images together.

Cards are optional. A manifest without `cards()` works exactly as before.

## Quick start

Cards need a directory of templates: HTML pages with `data-focus` attributes where the values go. See [Templates](templates.md) for the format and [Building templates with Astro](astro.md) for a template repository; [awcodes/focus-templates](https://github.com/awcodes/focus-templates) is a complete example.

```php
<?php

use Awcodes\Focus\Card;
use Awcodes\Focus\Enums\Size;
use Awcodes\Focus\Screenshot;
use Awcodes\Focus\ScreenshotSuite;

return ScreenshotSuite::make()
    ->screenshots([
        Screenshot::make('editor')
            ->visit('/admin/pages/1/edit')
            ->focus('[data-focus="editor"]'),
    ])
    ->cardTemplates('../card-templates/dist')
    ->cards([
        Card::make('social')
            ->template('one-up')
            ->screenshots(['editor'])
            ->sizes([Size::OpenGraph, Size::GitHubSocial]),
    ]);
```

```bash
composer focus
```

Focus captures `editor`, then renders the `one-up` template twice and writes:

```text
art/social-open-graph-dark.png
art/social-github-social-dark.png
```

## How a run works

1. Focus captures every screenshot, as usual.
2. It renders every card in the same browser. Each card loads its template, fills in the values and screenshots, and is captured at each requested size.

A card whose screenshot failed in this run is not rendered, so it is never built from an out-of-date image and reported as new. Its previous file is left in place.

Cards do not need Workbench. `--cards-only` renders them from the screenshot files already on disk without starting a server, and a manifest with cards but no screenshots works in any repository, including packages without a Workbench (see [CLI](../cli.md#cards)).

## Values

Every card provides these values to its template:

| Value | Default |
|---|---|
| `title` | The package name from `composer.json`, title-cased: `awcodes/filament-curator` becomes `Filament Curator` |
| `description` | The `description` from `composer.json` |
| `package` | The `name` from `composer.json` |
| `install` | `composer require {package}` |
| `screenshot.1`, `screenshot.2`, … | The card's screenshots, in the order given to `screenshots()` |

Override them per card:

```php
Card::make('social')
    ->title('Curator')
    ->description('Media management for Filament.')
    ->with([
        'install' => 'composer require --dev awcodes/focus',
        'tagline' => 'Upload, crop, and organise media.',
    ]);
```

`with()` adds custom values for a template's own `data-focus` keys, such as `tagline` above, and replaces `install`. Keys are lowercase kebab-case. `title`, `description`, `package`, and `screenshot` are set with their own methods and cannot be passed to `with()`.

Values come from `composer.json`, not from GitHub, so cards render offline and the same way every time. A card needs a title: without a package name in `composer.json`, set one with `title()`.

## Sizes

`sizes()` takes presets or `[width, height]` pairs, in CSS pixels:

```php
Card::make('social')->sizes([Size::OpenGraph, Size::GitHubSocial, [1920, 1080]]);
```

| Preset | Size |
|---|---|
| `Size::OpenGraph` | 1200×630 |
| `Size::Twitter` | 1200×675 |
| `Size::YouTube` | 1280×720 |
| `Size::GitHubSocial` | 1280×640 |

The default is `[Size::OpenGraph]`. The image is `size × scale` pixels, and `scale()` defaults to `2`, so an Open Graph card is 2400×1260.

A template designed for one aspect ratio is cropped to fit another. Keep one template per aspect ratio you publish; see [Fixed canvases](templates.md#fixed-canvases).

## Themes

Cards render dark only by default, because sharing sites show one image whatever the reader's colour scheme. Add light where it is useful, such as a README banner:

```php
Card::make('banner')->themes([Theme::Light, Theme::Dark]);
```

Each theme is a separate image, and `screenshot.N` uses the screenshot's variant for that theme. A screenshot a card uses must be captured in every theme the card renders; Focus reports it otherwise.

A template can also show both variants in one image with `screenshot.1.light` and `screenshot.1.dark`; see [Templates](templates.md#screenshots).

## Screenshots for cards

Documentation screenshots are framed around their subject, so their shapes vary: a popover is tall, an editor is wide. A template's screenshot slot has a fixed shape, and a screenshot of a very different shape is cropped heavily to fill it.

For the best cards, capture screenshots for the card itself. Use the same subject, set `minSize()` to the slot's size so the crop grows around it to that shape, and capture dark only:

```php
Screenshot::make('card-editor')
    ->visit('/admin/pages/1/edit')
    ->focus('[data-focus="editor"]')
    ->minSize(1400, 816)
    ->themes([Theme::Dark]),
```

With a template whose slot is 1400×816, this capture fills it exactly.

## Card settings

| Method | Purpose | Default |
|---|---|---|
| `template(string)` | Template name in the template directory | `default` |
| `screenshots([...])` | Screenshot names for `screenshot.1`, `screenshot.2`, … | none |
| `sizes([...])` | Sizes to render | `[Size::OpenGraph]` |
| `themes([...])` | Themes to render | `[Theme::Dark]` |
| `scale(int\|float)` | Output pixel density | `2` |
| `title(string)` | The `title` value | from `composer.json` |
| `description(string)` | The `description` value | from `composer.json` |
| `with([...])` | Custom values, and `install` | none |

Suite settings:

| Method | Purpose | Default |
|---|---|---|
| `cards([...])` | The cards to render | none |
| `cardTemplates(string)` | The template directory: a path, or a GitHub reference (see [Template sources](sources.md)) | required with `cards()` |
| `cardOutputPath(string)` | Where cards are written, relative to the repository root unless absolute | `art` |

Card settings do not inherit the suite's `themes()` or `scale()`, which configure screenshots. Cards use the suite's `locale()`, `timezone()`, and `freezeTime()`.

## Output

Each card is written as:

```text
{name}-{size}-{theme}.png
```

`{size}` is the preset's name (`open-graph`, `github-social`) or `{width}x{height}`. The theme is always part of the name, so adding a theme later never renames existing files. Cards are written atomically and reported as orphans like screenshots; see [CLI](../cli.md#output-files).

Cards go to `art/` rather than `docs/assets` because they are repository art, not documentation images. Keep `art/` out of your Composer package with `/art export-ignore` in `.gitattributes`.
