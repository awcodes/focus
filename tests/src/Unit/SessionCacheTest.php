<?php

declare(strict_types=1);

use Awcodes\Focus\Authentication\FormLogin;
use Awcodes\Focus\Authentication\SessionCache;
use Awcodes\Focus\ScreenshotSuite;

it('round-trips a storage state privately', function (): void {
    $sessions = SessionCache::for('/repo', new FormLogin, tempDirectory());
    $state = ['cookies' => [['name' => 'session', 'value' => 'abc']], 'origins' => []];

    $sessions->save($state);

    expect($sessions->load())->toBe($state)
        ->and(fileperms($sessions->path) & 0o777)->toBe(0o600);

    $sessions->forget();

    expect($sessions->load())->toBeNull();
});

it('ignores a missing or corrupt cache', function (): void {
    $sessions = SessionCache::for('/repo', new FormLogin, tempDirectory());

    expect($sessions->load())->toBeNull();

    file_put_contents($sessions->path, '{not json');

    expect($sessions->load())->toBeNull();
});

it('keys sessions by project, login path, and account', function (): void {
    $directory = tempDirectory();
    $path = fn (string $root, FormLogin $login) => SessionCache::for($root, $login, $directory)->path;

    expect($path('/a', new FormLogin))->toBe($path('/a', new FormLogin))
        ->not->toBe($path('/b', new FormLogin))
        ->not->toBe($path('/a', new FormLogin(path: '/app/login')))
        ->not->toBe($path('/a', new FormLogin(email: 'other@example.com')));
});

it('reuses sessions by default', function (): void {
    expect(ScreenshotSuite::make()->getReuseSession())->toBeTrue()
        ->and(ScreenshotSuite::make()->reuseSession(false)->getReuseSession())->toBeFalse();
});
