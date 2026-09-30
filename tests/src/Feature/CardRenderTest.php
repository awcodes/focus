<?php

declare(strict_types=1);

use Awcodes\Focus\Card;
use Awcodes\Focus\Enums\FailureReason;
use Awcodes\Focus\Enums\Theme;
use Awcodes\Focus\Runtime\CardResult;
use Awcodes\Focus\Runtime\Runner;
use Awcodes\Focus\Screenshot;
use Awcodes\Focus\ScreenshotSuite;
use Awcodes\Focus\Support\TemplateDirectory;

beforeEach(function (): void {
    if (! browserAvailable()) {
        $this->markTestSkipped('Playwright is not installed. Run vendor/bin/playwright-install chromium.');
    }
});

const CARD_CSS = <<<'CSS'
    html, body { margin: 0; height: 100%; background: rgb(0, 128, 0); }
    html[data-focus-theme="light"] body { background: rgb(255, 255, 0); }
    img { display: block; width: 100%; height: 100%; object-fit: cover; }
    CSS;

/**
 * A template directory shaped like Astro build output, in a directory whose name contains a space.
 *
 * @param  array<string, string>  $files
 */
function cardTemplates(array $files): string
{
    $root = tempDirectory() . '/card templates';

    foreach (['_astro/card.css' => CARD_CSS, ...$files] as $path => $contents) {
        if (! is_dir(dirname("{$root}/{$path}"))) {
            mkdir(dirname("{$root}/{$path}"), recursive: true);
        }

        file_put_contents("{$root}/{$path}", $contents);
    }

    return $root;
}

function cardPage(string $body, string $head = ''): string
{
    return <<<HTML
        <!doctype html>
        <html>
        <head><meta charset="utf-8"><link rel="stylesheet" href="/_astro/card.css">{$head}</head>
        <body>{$body}</body>
        </html>
        HTML;
}

/**
 * A solid-color PNG, standing in for a screenshot.
 *
 * @param  array{int, int, int}  $rgb
 */
function solidPng(string $path, array $rgb): void
{
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), recursive: true);
    }

    $image = imagecreatetruecolor(40, 20);
    imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));
    imagepng($image, $path);
}

/**
 * @return array{list<CardResult>, string}
 */
function renderCards(string $templates, Card $card, array $screenshots = []): array
{
    $root = tempDirectory();

    $suite = ScreenshotSuite::make()
        ->timeout(5_000)
        ->cardTemplates($templates)
        ->screenshots(array_map(fn (string $name): Screenshot => Screenshot::make($name)->visit('/'), $screenshots))
        ->cards([$card->title($card->getTitle() ?? 'Mason')]);

    foreach ($screenshots as $name) {
        solidPng("{$root}/docs/assets/{$name}-light.png", [255, 0, 0]);
        solidPng("{$root}/docs/assets/{$name}-dark.png", [0, 0, 255]);
    }

    return [(new Runner)->runCards($suite, $suite->planCards($root), new TemplateDirectory($templates)), $root];
}

it('renders Astro-shaped output with root-relative assets at the exact size', function (): void {
    $templates = cardTemplates([
        'two-up/index.html' => cardPage('<img data-focus="screenshot.1" src="/samples/primary.png" alt="">'),
        'samples/primary.png' => '',
    ]);

    [[$result], $root] = renderCards($templates, Card::make('social')->template('two-up')->screenshots(['editor']), ['editor']);

    $path = "{$root}/art/social-open-graph-dark.png";

    expect($result->error)->toBeNull()
        ->and($result->warnings)->toBe([])
        ->and(getimagesize($path))->toMatchArray([0 => 2400, 1 => 1260])
        ->and(pixel($path, 10, 10))->toBe([0, 0, 255]);
});

it('uses the screenshot variant and render state for each theme', function (): void {
    $templates = cardTemplates([
        'default.html' => cardPage('<img data-focus="screenshot.1" alt="" style="width: 50%">'),
    ]);

    [$results, $root] = renderCards(
        $templates,
        Card::make('social')->screenshots(['editor'])->themes([Theme::Light, Theme::Dark])->scale(1),
        ['editor'],
    );

    $light = "{$root}/art/social-open-graph-light.png";
    $dark = "{$root}/art/social-open-graph-dark.png";

    expect(array_map(fn (CardResult $result) => $result->error, $results))->toBe([null, null])
        ->and(pixel($light, 10, 10))->toBe([255, 0, 0])
        ->and(pixel($light, 1100, 10))->toBe([255, 255, 0])
        ->and(pixel($dark, 10, 10))->toBe([0, 0, 255])
        ->and(pixel($dark, 1100, 10))->toBe([0, 128, 0]);
});

it('shows both theme variants in one image with explicit keys', function (): void {
    $templates = cardTemplates([
        'default.html' => cardPage(
            '<img data-focus="screenshot.1.light" alt="" style="height: 50%"><img data-focus="screenshot.1.dark" alt="" style="height: 50%">',
        ),
    ]);

    [[$result], $root] = renderCards($templates, Card::make('social')->screenshots(['editor'])->scale(1), ['editor']);

    $path = "{$root}/art/social-open-graph-dark.png";

    expect($result->error)->toBeNull()
        ->and(pixel($path, 10, 10))->toBe([255, 0, 0])
        ->and(pixel($path, 10, 600))->toBe([0, 0, 255]);
});

it('exposes screenshots as CSS variables and counts them as used', function (): void {
    $templates = cardTemplates([
        'default.html' => cardPage('', '<style>body { background: var(--focus-screenshot-1-light) center / cover; }</style>'),
    ]);

    [[$result], $root] = renderCards($templates, Card::make('social')->screenshots(['editor'])->scale(1), ['editor']);

    expect($result->error)->toBeNull()
        ->and($result->warnings)->toBe([])
        ->and(pixel("{$root}/art/social-open-graph-dark.png", 10, 10))->toBe([255, 0, 0]);
});

it('inserts values as text, never as HTML', function (): void {
    $templates = cardTemplates([
        'default.html' => cardPage('<h1 data-focus="title">Sample</h1>', '<style>b { position: fixed; inset: 0; background: rgb(255, 0, 255); }</style>'),
    ]);

    [[$result], $root] = renderCards($templates, Card::make('social')->title('<b>bold</b>')->scale(1));

    expect($result->error)->toBeNull()
        ->and(pixel("{$root}/art/social-open-graph-dark.png", 600, 400))->toBe([0, 128, 0]);
});

it('replaces the sources of a picture element', function (): void {
    $templates = cardTemplates([
        'default.html' => cardPage('<picture><source srcset="/samples/wide.png" media="(min-width: 1px)"><img data-focus="screenshot.1" src="/samples/primary.png" alt=""></picture>'),
    ]);

    solidPng("{$templates}/samples/wide.png", [255, 255, 255]);

    [[$result], $root] = renderCards($templates, Card::make('social')->screenshots(['editor'])->scale(1), ['editor']);

    expect($result->error)->toBeNull()
        ->and(pixel("{$root}/art/social-open-graph-dark.png", 10, 10))->toBe([0, 0, 255]);
});

it('fails on unusable data-focus keys and keeps the previous file', function (): void {
    $templates = cardTemplates([
        'default.html' => cardPage(
            '<h1 data-focus="titel"></h1><div data-focus="screenshot.1"></div><img data-focus="screenshot.3" alt=""><p data-focus="tagline"></p>',
        ),
    ]);

    $root = tempDirectory();
    mkdir("{$root}/art");
    file_put_contents("{$root}/art/social-open-graph-dark.png", 'previous');

    $suite = ScreenshotSuite::make()
        ->timeout(5_000)
        ->cardTemplates($templates)
        ->screenshots([Screenshot::make('editor')->visit('/')])
        ->cards([Card::make('social')->title('Mason')->screenshots(['editor'])]);

    solidPng("{$root}/docs/assets/editor-dark.png", [0, 0, 255]);

    [$result] = (new Runner)->runCards($suite, $suite->planCards($root), new TemplateDirectory($templates));

    expect($result->error?->reason)->toBe(FailureReason::Template)
        ->and($result->error?->getMessage())->toBe(implode(PHP_EOL, [
            'Template [default] has unusable data-focus keys:',
            '  - [titel] is not a value this card provides (install, title, description, package).',
            '  - [screenshot.3]: the card passes 1 screenshot, so there is no screenshot 3.',
            '  - [tagline] is not a value this card provides (install, title, description, package).',
            '  - screenshot.1 on <div>: screenshot keys only work on <img>. Use the --focus-screenshot-N CSS variables for backgrounds.',
        ]))
        ->and(file_get_contents("{$root}/art/social-open-graph-dark.png"))->toBe('previous');
});

it('fails when a used screenshot variant is missing', function (): void {
    $templates = cardTemplates([
        'default.html' => cardPage('<img data-focus="screenshot.1" alt="">'),
    ]);

    $root = tempDirectory();

    $suite = ScreenshotSuite::make()
        ->timeout(5_000)
        ->cardTemplates($templates)
        ->screenshots([Screenshot::make('editor')->visit('/')])
        ->cards([Card::make('social')->title('Mason')->screenshots(['editor'])]);

    [$result] = (new Runner)->runCards($suite, $suite->planCards($root), new TemplateDirectory($templates));

    expect($result->error?->reason)->toBe(FailureReason::Template)
        ->and($result->error?->getMessage())->toContain("[screenshot.1]: screenshot [editor] has no dark capture at [{$root}/docs/assets/editor-dark.png]. Capture it first.");
});

it('blocks remote requests and answers missing files without hanging', function (): void {
    $templates = cardTemplates([
        'default.html' => cardPage(
            '<img src="https://example.com/remote.png" alt="" style="height: 10px"><img src="/missing.png" alt="" style="height: 10px">',
            '<link rel="stylesheet" href="https://fonts.bunny.net/css?family=fira-sans:400">',
        ),
    ]);

    [[$result]] = renderCards($templates, Card::make('social')->scale(1));

    expect($result->error)->toBeNull()
        ->and($result->warnings)->toBe([
            'Blocked a request outside the template directory: https://fonts.bunny.net/css?family=fira-sans:400. Bundle remote assets such as fonts with the template.',
            'Blocked a request outside the template directory: https://example.com/remote.png. Bundle remote assets such as fonts with the template.',
            'The template requested /missing.png, which is not in the template directory.',
        ]);
});

it('does not serve files outside the template directory', function (): void {
    $templates = cardTemplates([
        'default.html' => cardPage('<img src="/../secret.png" alt="" style="height: 10px"><img src="/%2e%2e/secret.png" alt="" style="height: 10px">'),
    ]);

    solidPng(dirname($templates) . '/secret.png', [255, 255, 255]);

    [[$result]] = renderCards($templates, Card::make('social')->scale(1));

    expect($result->error)->toBeNull()
        ->and(implode(PHP_EOL, $result->warnings))->not->toContain('Blocked')
        ->and($result->warnings)->each->toContain('which is not in the template directory');
});

it('warns about unused values, unused screenshots, and overflow', function (): void {
    $templates = cardTemplates([
        'default.html' => cardPage('<div style="height: 2000px"></div>'),
    ]);

    [[$result]] = renderCards($templates, Card::make('social')->scale(1)->screenshots(['editor'])->with(['tagline' => 'Bricks']), ['editor']);

    expect($result->error)->toBeNull()
        ->and($result->warnings)->toBe([
            'with() value [tagline] is not used by template [default].',
            'Screenshot [editor] (screenshot.1) is not used by template [default].',
            'The template is 1200x2000, larger than its 1200x630 card, so content overflows. Long text is the usual cause.',
        ]);
});

it('serves a file-format template at its own path', function (): void {
    $templates = cardTemplates([
        'social/wide.html' => cardPage('<img src="art.png" alt="">'),
    ]);

    solidPng("{$templates}/social/art.png", [255, 0, 0]);

    [[$result], $root] = renderCards($templates, Card::make('wide')->template('social/wide')->scale(1));

    expect($result->error)->toBeNull()
        ->and($result->warnings)->toBe([])
        ->and(pixel("{$root}/art/wide-open-graph-dark.png", 10, 10))->toBe([255, 0, 0]);
});

it('fails a card whose template is missing', function (): void {
    [[$result]] = renderCards(cardTemplates([]), Card::make('social')->template('nope'));

    expect($result->error?->reason)->toBe(FailureReason::Template)
        ->and($result->error?->getMessage())->toContain('Card template [nope] not found');
});

it('shrinks a fixed canvas to each size, exactly', function (): void {
    // A 2560x1440 canvas with a 100px magenta band at the top. At 2:1 the crop removes 80px from the top and bottom,
    // and GitHub social at scale 2 is drawn at 1x, so 20px of the band remains.
    $templates = cardTemplates([
        'fixed.html' => cardPage(
            '<div style="width: 2560px; height: 1440px; position: relative"><div style="position: absolute; inset: 0 0 auto 0; height: 100px; background: rgb(255, 0, 255)"></div></div>',
            '<meta name="focus:canvas" content="2560x1440">',
        ),
    ]);

    [$results, $root] = renderCards($templates, Card::make('fixed')->template('fixed')->sizes([
        Awcodes\Focus\Enums\Size::OpenGraph,
        Awcodes\Focus\Enums\Size::GitHubSocial,
        Awcodes\Focus\Enums\Size::Twitter,
        [1920, 1080],
        [450, 253],
    ]));

    $dimensions = array_map(fn (CardResult $result): string => implode('x', array_slice(getimagesize($result->render->path), 0, 2)), $results);

    expect(array_map(fn (CardResult $result) => $result->error, $results))->each->toBeNull()
        ->and($dimensions)->toBe(['2400x1260', '2560x1280', '2400x1350', '3840x2160', '900x506'])
        // At scale 2, 1920x1080 is drawn at 1.5x, so the 100px band covers 150px.
        ->and(pixel("{$root}/art/fixed-1920x1080-dark.png", 10, 140))->toBe([255, 0, 255])
        ->and(pixel("{$root}/art/fixed-1920x1080-dark.png", 10, 160))->toBe([0, 128, 0])
        ->and(pixel("{$root}/art/fixed-github-social-dark.png", 10, 10))->toBe([255, 0, 255])
        ->and(pixel("{$root}/art/fixed-github-social-dark.png", 10, 30))->toBe([0, 128, 0])
        ->and($results[1]->warnings)->toBe(['The 2560x1440 template was cropped to fit 1280x640: 80px from the top and bottom. Keep important content away from those edges.'])
        ->and($results[3]->warnings)->toBe([]);
});
