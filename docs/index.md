---
title: Focus
description: Generate consistent documentation screenshots from a package's Laravel Workbench application.
---

# Focus

Focus is a development tool that generates documentation screenshots from the Laravel Workbench application inside a Filament plugin (or other Laravel package) repository. You describe the UI states worth showing in a `focus.php` manifest at the repository root, and Focus drives the Workbench application with Playwright to produce light and dark PNGs that you commit alongside your documentation.

Screenshots are durable documentation assets, not test artifacts. The same manifest produces the same files every time, so regenerating them after a UI change gives you a meaningful diff.

Focus can also render [cards](cards/overview.md): share images, such as Open Graph cards and GitHub social previews, built from HTML templates and the screenshots captured in the same run.

## Quick start

Install Focus as a development dependency and scaffold the repository:

```bash
composer require --dev awcodes/focus
vendor/bin/focus init
```

`init` creates a starter `focus.php`, adds a `focus` Composer script, and installs the Playwright browser. Describe your screenshots in `focus.php`:

```php
<?php

use Awcodes\Focus\Screenshot;
use Awcodes\Focus\ScreenshotSuite;

return ScreenshotSuite::make()
    ->screenshots([
        Screenshot::make('editor')
            ->visit('/admin/pages/1/edit')
            ->focus('[data-focus="editor"]'),
    ]);
```

Then generate them:

```bash
composer focus
```

Focus starts the Workbench server, signs in with the Workbench development account, and writes `docs/assets/editor-light.png` and `docs/assets/editor-dark.png`.

## The central idea: focus

`focus()` names the subject of a screenshot, not the exact crop. Focus locates the element, adds context around it, grows small subjects to a useful minimum size, and keeps the region inside the page:

```php
Screenshot::make('add-action')
    ->visit('/admin/pages/1/edit')
    ->focus('[data-focus="add-action"]')
    ->padding(32)
    ->minSize(500, 300);
```

A 32×32 button becomes a readable 500×300 capture instead of a 32×32 image. When you want the whole screen instead, use `viewport()` or `fullPage()`.

## Who owns what

- **Workbench** provides the reproducible UI: the Filament panel, models, seeders, and fixtures.
- **Focus** provides the browser and capture infrastructure: the Playwright lifecycle, authentication, themes, readiness, framing, and output.
- **Your repository** describes what is worth showing: `focus.php`, stable `data-focus` selectors, and deterministic fixtures.

## A complete manifest

```php
<?php

use Awcodes\Focus\Enums\Size;
use Awcodes\Focus\Enums\Theme;
use Awcodes\Focus\Enums\Viewport;
use Awcodes\Focus\Screenshot;
use Awcodes\Focus\ScreenshotSuite;

return ScreenshotSuite::make()
    ->outputPath('docs/assets')
    ->mask('[data-focus-mask]')
    ->screenshots([
        Screenshot::make('editor')
            ->visit('/admin/pages/1/edit')
            ->focus('[data-focus="editor"]'),

        Screenshot::make('brick-picker')
            ->visit('/admin/pages/1/edit')
            ->click('[data-focus-action="add-brick"]')
            ->waitFor('[data-focus="brick-picker"]')
            ->focus('[data-focus="brick-picker"]')
            ->padding(32)
            ->minSize(500, 300),

        Screenshot::make('mobile-editor')
            ->viewportSize(Viewport::Mobile)
            ->visit('/admin/pages/1/edit')
            ->viewport(),

        Screenshot::make('social-card')
            ->visit('/admin/pages/1/edit')
            ->focus('[data-focus="editor"]')
            ->minSize(Size::OpenGraph)
            ->themes([Theme::Dark]),

        Screenshot::make('full-editor-page')
            ->visit('/admin/pages/1/edit')
            ->fullPage(),
    ]);
```

## Next steps

- [Installation](installation.md): requirements, browser installation, and `focus init`.
- [Configuration](configuration.md): the manifest, suite settings, and how settings resolve.
- [Capture modes](screenshots/capture-modes.md): `focus()`, `viewport()`, and `fullPage()`.
- [Workbench integration](workbench.md): selectors, fixtures, authentication, and the server.
- [CLI](cli.md): options, filtering, and output files.
- [Cards](cards/overview.md): share images from templates and screenshots.
