<?php

declare(strict_types=1);

use Awcodes\Focus\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;

function focus(string $cwd, array $input, array $inputs = []): ApplicationTester
{
    $application = new Application($cwd);
    $application->setAutoExit(false);

    $tester = new ApplicationTester($application);
    $tester->setInputs($inputs);
    $tester->run($input, ['interactive' => $inputs !== []]);

    return $tester;
}

it('lists planned captures', function (): void {
    $tester = focus(dirname(manifestFixture('valid.php')), ['--config' => 'valid.php', '--list' => true]);

    expect($tester->getStatusCode())->toBe(0)
        ->and($tester->getDisplay())
        ->toContain('editor', 'full-editor-page', 'docs/assets/editor-dark.png', 'docs/assets/full-editor-page-light.png')
        ->not->toContain('full-editor-page-dark');
});

it('applies --only and --theme filters', function (): void {
    $tester = focus(dirname(manifestFixture('valid.php')), [
        '--config' => 'valid.php',
        '--list' => true,
        '--only' => ['editor'],
        '--theme' => 'dark',
    ]);

    expect($tester->getDisplay())
        ->toContain('editor-dark.png')
        ->not->toContain('editor-light.png', 'full-editor-page');
});

it('rejects an unknown theme', function (): void {
    $tester = focus(dirname(manifestFixture('valid.php')), ['--config' => 'valid.php', '--theme' => 'sepia']);

    expect($tester->getStatusCode())->toBe(1)
        ->and($tester->getDisplay())->toContain('Unknown theme [sepia]');
});

it('reports manifest errors', function (): void {
    $tester = focus(dirname(manifestFixture('invalid.php')), ['--config' => 'invalid.php']);

    expect($tester->getStatusCode())->toBe(1)
        ->and($tester->getDisplay())->toContain('Duplicate screenshot name [twice]');
});

it('scaffolds a repository and is safe to re-run', function (): void {
    $cwd = tempDirectory();
    file_put_contents("{$cwd}/composer.json", json_encode([
        'name' => 'acme/plugin',
        'scripts' => ['test' => 'pest'],
        'extra' => new stdClass,
    ], JSON_PRETTY_PRINT));

    $first = focus($cwd, ['command' => 'init', '--skip-browsers' => true], ['yes']);

    $composer = json_decode(file_get_contents("{$cwd}/composer.json"), true);

    expect($first->getStatusCode())->toBe(0)
        ->and(file_exists("{$cwd}/focus.php"))->toBeTrue()
        ->and($composer['scripts']['focus'])->toBe(['Composer\\Config::disableProcessTimeout', 'focus'])
        ->and($composer['scripts']['test'])->toBe('pest')
        ->and(file_get_contents("{$cwd}/composer.json"))->toContain('"extra": {}');

    file_put_contents("{$cwd}/focus.php", '<?php // customised');
    $composer['scripts']['focus'] = 'custom';
    file_put_contents("{$cwd}/composer.json", json_encode($composer));

    $second = focus($cwd, ['command' => 'init', '--skip-browsers' => true], ['yes']);

    expect($second->getStatusCode())->toBe(0)
        ->and(file_get_contents("{$cwd}/focus.php"))->toBe('<?php // customised')
        ->and(json_decode(file_get_contents("{$cwd}/composer.json"), true)['scripts']['focus'])->toBe('custom')
        ->and($second->getDisplay())->toContain('Kept existing focus.php', 'Kept existing `focus` composer script');
});

it('produces a valid starter manifest', function (): void {
    $cwd = tempDirectory();

    focus($cwd, ['command' => 'init', '--skip-browsers' => true]);

    expect((new Awcodes\Focus\Manifest\ManifestLoader)->load("{$cwd}/focus.php")->getScreenshots())->toHaveCount(1);
});

function browserManifest(string $root, string $screenshots): void
{
    file_put_contents("{$root}/focus.php", <<<PHP
        <?php

        use Awcodes\\Focus\\Enums\\Theme;
        use Awcodes\\Focus\\Screenshot;
        use Awcodes\\Focus\\ScreenshotSuite;

        return ScreenshotSuite::make()->timeout(2_000)->screenshots([{$screenshots}]);
        PHP);
}

it('captures, reports failures, and flags orphans', function (): void {
    if (! browserAvailable()) {
        $this->markTestSkipped('Playwright is not installed.');
    }

    $root = tempDirectory();
    mkdir("{$root}/docs/assets", recursive: true);
    file_put_contents("{$root}/docs/assets/broken-light.png", 'previous');
    touch("{$root}/docs/assets/removed-dark.png");
    touch("{$root}/docs/assets/logo.png");

    browserManifest($root, <<<'PHP'
        Screenshot::make('card')->visit('/admin/page')->focus('[data-focus="card"]')->themes([Theme::Light]),
        Screenshot::make('broken')->visit('/admin/page')->focus('[data-focus="nope"]')->themes([Theme::Light]),
        PHP);

    $tester = focus($root, ['--base-url' => fixtureServer()]);
    $display = $tester->getDisplay();

    expect($tester->getStatusCode())->toBe(1)
        ->and($display)->toContain(
            'card (light)',
            'docs/assets/card-light.png',
            'broken (light)',
            'Selector not found',
            '[data-focus="nope"]',
            '/admin/page',
            'Not updated: docs/assets/broken-light.png is from a previous run.',
            '1 captured, 1 failed',
            '1 orphaned asset(s)',
            'docs/assets/removed-dark.png',
            '--prune',
        )
        ->and(file_get_contents("{$root}/docs/assets/broken-light.png"))->toBe('previous')
        ->and(file_exists("{$root}/docs/assets/removed-dark.png"))->toBeTrue();
});

it('prunes orphans only when asked, and never unrelated files', function (): void {
    if (! browserAvailable()) {
        $this->markTestSkipped('Playwright is not installed.');
    }

    $root = tempDirectory();
    mkdir("{$root}/docs/assets", recursive: true);
    touch("{$root}/docs/assets/removed-dark.png");
    touch("{$root}/docs/assets/logo.png");

    browserManifest($root, "Screenshot::make('card')->visit('/public')->focus('[data-focus=\"card\"]')->themes([Theme::Light])");
    file_put_contents("{$root}/focus.php", str_replace('->timeout(2_000)', '->timeout(2_000)->withoutLogin()', file_get_contents("{$root}/focus.php")));

    $declined = focus($root, ['--base-url' => fixtureServer(), '--prune' => true], ['no']);

    expect($declined->getStatusCode())->toBe(0)
        ->and(file_exists("{$root}/docs/assets/removed-dark.png"))->toBeTrue();

    $forced = focus($root, ['--base-url' => fixtureServer(), '--prune' => true, '--force' => true]);

    expect($forced->getStatusCode())->toBe(0)
        ->and($forced->getDisplay())->toContain('Deleted 1 orphaned asset(s)')
        ->and(file_exists("{$root}/docs/assets/removed-dark.png"))->toBeFalse()
        ->and(file_exists("{$root}/docs/assets/logo.png"))->toBeTrue()
        ->and(file_exists("{$root}/docs/assets/card-light.png"))->toBeTrue();
});

it('refuses to prune a filtered run', function (): void {
    $tester = focus(dirname(manifestFixture('valid.php')), ['--config' => 'valid.php', '--only' => ['editor'], '--prune' => true]);

    expect($tester->getStatusCode())->toBe(1)
        ->and($tester->getDisplay())->toContain('--prune cannot be combined');
});

it('explains an unreachable base url', function (): void {
    $tester = focus(dirname(manifestFixture('valid.php')), ['--config' => 'valid.php', '--base-url' => 'http://127.0.0.1:1']);

    expect($tester->getStatusCode())->toBe(1)
        ->and($tester->getDisplay())->toContain('Workbench unavailable', 'Nothing responded');
});

it('warns when every theme produces an identical image', function (): void {
    if (! browserAvailable()) {
        $this->markTestSkipped('Playwright is not installed.');
    }

    $root = tempDirectory();

    browserManifest($root, "Screenshot::make('plain')->visit('/plain'), Screenshot::make('themed')->visit('/public')");
    file_put_contents("{$root}/focus.php", str_replace('->timeout(2_000)', '->timeout(2_000)->withoutLogin()', file_get_contents("{$root}/focus.php")));

    $display = focus($root, ['--base-url' => fixtureServer()])->getDisplay();

    expect($display)->toContain('plain: every theme produced an identical image')
        ->not->toContain('themed: every theme');
});

/**
 * A repository with composer.json, a card template directory, and a manifest defining the given screenshots and cards.
 */
function cardRepositoryFor(string $screenshots, string $cards, string $template = '<img data-focus="screenshot.1" alt="" style="display: block; width: 100%">'): string
{
    $root = tempDirectory();

    file_put_contents("{$root}/composer.json", json_encode(['name' => 'acme/plugin', 'description' => 'A plugin.']));
    mkdir("{$root}/templates");
    file_put_contents("{$root}/templates/default.html", "<!doctype html><html><head><meta charset=\"utf-8\"><style>body { margin: 0; background: #080 }</style></head><body>{$template}</body></html>");
    file_put_contents("{$root}/focus.php", <<<PHP
        <?php

        use Awcodes\\Focus\\Card;
        use Awcodes\\Focus\\Enums\\Theme;
        use Awcodes\\Focus\\Screenshot;
        use Awcodes\\Focus\\ScreenshotSuite;

        return ScreenshotSuite::make()
            ->timeout(2_000)
            ->cardTemplates('templates')
            ->screenshots([{$screenshots}])
            ->cards([{$cards}]);
        PHP);

    return $root;
}

it('lists planned cards', function (): void {
    $root = cardRepositoryFor(
        "Screenshot::make('editor')->visit('/admin')",
        "Card::make('social')->screenshots(['editor'])->themes([Theme::Light, Theme::Dark])->sizes([[1920, 1080]])",
    );

    $display = focus($root, ['--list' => true, '--cards-only' => true])->getDisplay();

    expect($display)
        ->toContain('social', 'default', '1920x1080', '3840x2160', 'art/social-1920x1080-light.png', 'art/social-1920x1080-dark.png')
        ->not->toContain('docs/assets/editor');
});

it('rejects --no-cards with --cards-only', function (): void {
    $tester = focus(dirname(manifestFixture('valid.php')), ['--config' => 'valid.php', '--no-cards' => true, '--cards-only' => true]);

    expect($tester->getStatusCode())->toBe(1)
        ->and($tester->getDisplay())->toContain('--no-cards and --cards-only cannot be combined.');
});

it('reports a missing card template before starting a browser', function (): void {
    $root = cardRepositoryFor("Screenshot::make('editor')->visit('/admin')", "Card::make('social')->template('two-up')");

    $tester = focus($root, ['--base-url' => 'http://127.0.0.1:1']);

    expect($tester->getStatusCode())->toBe(1)
        ->and($tester->getDisplay())->toContain('Card template [two-up] not found')
        ->not->toContain('Workbench unavailable');
});

it('renders cards without Workbench', function (): void {
    if (! browserAvailable()) {
        $this->markTestSkipped('Playwright is not installed.');
    }

    $root = cardRepositoryFor("Screenshot::make('editor')->visit('/admin')", "Card::make('social')->scale(1)", '<h1 data-focus="title"></h1>');

    $tester = focus($root, ['--cards-only' => true]);

    expect($tester->getStatusCode())->toBe(0)
        ->and($tester->getDisplay())
        ->toContain('rendering 1 card(s) from templates', 'social (open-graph, dark)', 'art/social-open-graph-dark.png', '1 rendered, 0 failed.')
        ->not->toContain('capturing', 'Workbench')
        ->and(getimagesize("{$root}/art/social-open-graph-dark.png"))->toMatchArray([0 => 1200, 1 => 630]);
});

it('renders cards from the screenshots captured in the same run', function (): void {
    if (! browserAvailable()) {
        $this->markTestSkipped('Playwright is not installed.');
    }

    $root = cardRepositoryFor(
        <<<'PHP'
            Screenshot::make('page')->visit('/admin/page')->themes([Theme::Light]),
            Screenshot::make('broken')->visit('/admin/page')->focus('[data-focus="nope"]')->themes([Theme::Light]),
            PHP,
        <<<'PHP'
            Card::make('fresh')->screenshots(['page'])->themes([Theme::Light])->scale(1),
            Card::make('stale')->screenshots(['broken'])->themes([Theme::Light])->scale(1),
            PHP,
    );

    mkdir("{$root}/art");
    file_put_contents("{$root}/art/stale-open-graph-light.png", 'previous');

    $tester = focus($root, ['--base-url' => fixtureServer()]);

    expect($tester->getStatusCode())->toBe(1)
        ->and($tester->getDisplay())->toContain(
            'capturing 2 screenshot(s)',
            'and rendering 2 card(s) from templates',
            'fresh (open-graph, light)',
            'stale (open-graph, light)',
            'Screenshot failed',
            'Not rendered: screenshot(s) broken failed in this run.',
            'Not updated: art/stale-open-graph-light.png is from a previous run.',
            '1 captured, 1 rendered, 2 failed.',
        )
        ->and(pixel("{$root}/art/fresh-open-graph-light.png", 5, 5))->toBe([255, 255, 255])
        ->and(file_get_contents("{$root}/art/stale-open-graph-light.png"))->toBe('previous');

    $filtered = focus($root, ['--only' => ['fresh']]);

    expect($filtered->getStatusCode())->toBe(0)
        ->and($filtered->getDisplay())
        ->toContain('Cards use the existing page screenshot file(s), which this run does not capture.')
        ->not->toContain('capturing');
});

it('reports card orphans, and skips the card directory with --no-cards', function (): void {
    if (! browserAvailable()) {
        $this->markTestSkipped('Playwright is not installed.');
    }

    $root = cardRepositoryFor("Screenshot::make('editor')->visit('/admin')", "Card::make('social')->scale(1)", '<h1 data-focus="title"></h1>');

    mkdir("{$root}/art");
    touch("{$root}/art/old-open-graph-dark.png");
    touch("{$root}/art/banner.png");

    $cardsOnly = focus($root, ['--cards-only' => true])->getDisplay();

    expect($cardsOnly)->toContain('1 orphaned asset(s)', 'art/old-open-graph-dark.png')
        ->not->toContain('art/banner.png', 'art/social-open-graph-dark.png');

    file_put_contents("{$root}/focus.php", str_replace("->cardTemplates('templates')", "->cardTemplates('templates')->withoutLogin()", file_get_contents("{$root}/focus.php")));
    file_put_contents("{$root}/focus.php", str_replace("visit('/admin')", "visit('/public')", file_get_contents("{$root}/focus.php")));

    $noCards = focus($root, ['--base-url' => fixtureServer(), '--no-cards' => true])->getDisplay();

    expect($noCards)->not->toContain('orphaned', 'rendering');
});
