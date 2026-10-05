<?php

declare(strict_types=1);

use Awcodes\Focus\Fixture;
use Awcodes\Focus\Manifest\ManifestValidator;
use Awcodes\Focus\Runtime\NetworkFixtures;
use Awcodes\Focus\Runtime\UiAvatars;
use Awcodes\Focus\Screenshot;
use Awcodes\Focus\ScreenshotSuite;

it('matches the whole URL, query string included', function (): void {
    $fixture = new Fixture('https://www.gravatar.com/avatar/**', 'avatar.png');

    expect($fixture->matches('https://www.gravatar.com/avatar/abc?s=80&d=mp'))->toBeTrue()
        ->and($fixture->matches('https://gravatar.com/avatar/abc'))->toBeFalse()
        ->and((new Fixture('https://example.com/*?d=retro*', 'retro.png'))->matches('https://example.com/abc?d=retro&s=80'))->toBeTrue();
});

it('resolves fixture files against the repository root', function (): void {
    $fixtures = ScreenshotSuite::make()
        ->fixture('https://a.test/**', 'workbench/fixtures/a.png')
        ->fixture('https://b.test/**', '/abs/b.png')
        ->fixture('https://c.test/**', fn (string $url): string => str_contains($url, 'retro') ? 'retro.png' : 'mp.png')
        ->screenshots([Screenshot::make('page')->visit('/admin')])
        ->plan('/repo')[0]
        ->fixtures;

    expect($fixtures[0]->path('https://a.test/x'))->toBe('/repo/workbench/fixtures/a.png')
        ->and($fixtures[1]->path('https://b.test/x'))->toBe('/abs/b.png')
        ->and($fixtures[2]->path('https://c.test/x?d=retro'))->toBe('/repo/retro.png')
        ->and($fixtures[2]->path('https://c.test/x?d=mp'))->toBe('/repo/mp.png');
});

it('carries allowed remote patterns into every capture', function (): void {
    $capture = ScreenshotSuite::make()
        ->allowRemote('https://fonts.bunny.net/**')
        ->screenshots([Screenshot::make('page')->visit('/admin')])
        ->plan('/repo')[0];

    expect($capture->allowedRemote)->toBe(['https://fonts.bunny.net/**']);
});

it('validates fixture and allowRemote patterns', function (): void {
    $suite = ScreenshotSuite::make()
        ->screenshots([Screenshot::make('page')->visit('/admin')])
        ->fixture('**/avatar/*', 'avatar.png')
        ->fixture('https://ok.test/**', ' ')
        ->allowRemote('fonts.bunny.net/**');

    expect((new ManifestValidator)->validate($suite))->toBe([
        'fixture() needs an absolute http(s) URL pattern with a host, such as https://example.com/**, [**/avatar/*] given.',
        'fixture(https://ok.test/**) needs a file.',
        'allowRemote() needs absolute http(s) URL patterns, [fonts.bunny.net/**] given.',
    ]);
});

it('draws ui-avatars.com initials on the requested background', function (): void {
    $svg = UiAvatars::svg('https://ui-avatars.com/api/?name=T+U&format=svg&color=FFFFFF&background=%2309090b');

    expect($svg)->toContain('width="64" height="64"')
        ->toContain('<rect width="64" height="64" fill="#09090B"/>')
        ->toContain('fill="#FFFFFF"')
        ->toContain('>TU</text>')
        ->and(UiAvatars::svg('https://ui-avatars.com/api/?name=Ada+Lovelace+Byron&rounded=true&size=128'))
        ->toContain('<circle cx="64" cy="64" r="64" fill="#DDDDDD"/>')
        ->toContain('>AL</text>')
        ->and(UiAvatars::svg('https://ui-avatars.com/api/?name=%3Cscript%3E'))
        ->toContain('>&lt;S</text>');
});

it('is byte-identical for the same URL', function (): void {
    $url = 'https://ui-avatars.com/api/?name=T+U&format=svg';

    expect(UiAvatars::svg($url))->toBe(UiAvatars::svg($url));
});

it('warns once per origin about remote URLs nothing answered', function (): void {
    $fixtures = new NetworkFixtures(
        [new Fixture('https://www.gravatar.com/avatar/**', 'avatar.png')],
        ['https://fonts.bunny.net/**'],
    );

    expect($fixtures->warnings([
        'https://www.gravatar.com/avatar/abc',
        'https://ui-avatars.com/api/?name=T',
        'https://fonts.bunny.net/css?family=inter',
        'https://cdn.example.com/a.js',
        'https://cdn.example.com/b.js',
        'https://cdn.example.com/b.js',
        'http://localhost:8000/x.png',
    ]))->toBe([
        'Loaded https://cdn.example.com/a.js and 1 more from https://cdn.example.com over the network, so this capture can change between runs. Serve it with fixture() or accept it with allowRemote().',
        'Loaded http://localhost:8000/x.png from http://localhost:8000 over the network, so this capture can change between runs. Serve it with fixture() or accept it with allowRemote().',
    ]);
});
