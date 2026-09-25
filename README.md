# Focus

![Focus: documentation screenshots generated from your Workbench](https://raw.githubusercontent.com/awcodes/focus/main/art/focus-social.png)

Generate consistent, deterministic documentation screenshots from a package's Laravel Workbench application.

Describe the UI states worth showing in a `focus.php` manifest, and Focus drives the Workbench with Playwright to produce light and dark PNGs for your documentation. `focus()` frames a subject intelligently: pass a selector for what matters, and Focus adds padding, grows small subjects to a useful size, and keeps the capture inside the page.

## Quick start

```bash
composer require --dev awcodes/focus
vendor/bin/focus init
```

```php
<?php

use Awcodes\Focus\Screenshot;
use Awcodes\Focus\ScreenshotSuite;

return ScreenshotSuite::make()
    ->screenshots([
        Screenshot::make('editor')
            ->visit('/admin/pages/1/edit')
            ->focus('[data-focus="editor"]')
            ->padding(32)
            ->minSize(500, 300),
    ]);
```

```bash
composer focus
```

This writes `docs/assets/editor-light.png` and `docs/assets/editor-dark.png`.

## Documentation

Full documentation lives in [`docs/`](docs/index.md): installation, configuration, capture modes, interactions, Workbench integration, the CLI, and troubleshooting.

## Development

Install dependencies:

    composer install
    vendor/bin/playwright-install chromium

Run the test suite:

    composer test

The browser tests start a small fixture application with PHP's built-in server and drive it with Chromium. They are skipped when the Playwright server is not installed.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
