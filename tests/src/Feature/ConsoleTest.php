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
