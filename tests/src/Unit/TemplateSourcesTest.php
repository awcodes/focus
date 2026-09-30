<?php

declare(strict_types=1);

use Awcodes\Focus\Exceptions\FocusException;
use Awcodes\Focus\Templates\ArchiveDownloader;
use Awcodes\Focus\Templates\GitHubReference;
use Awcodes\Focus\Templates\GitRemote;
use Awcodes\Focus\Templates\HttpArchiveDownloader;
use Awcodes\Focus\Templates\TemplateSources;

const TAG_OBJECT = 'd67a2e9f98aa5e4fa538bb16d1c8495865e66028';
const COMMIT = '46538da9140cfd218bf723287aa5ea6f3dc6097c';
const OTHER_COMMIT = '1111111111111111111111111111111111111111';

final class FakeGitRemote implements GitRemote
{
    /** @var list<array{string, list<string>, ?string}> */
    public array $calls = [];

    /**
     * @param  list<string>|FocusException  $response
     */
    public function __construct(public array | FocusException $response = []) {}

    public function lsRemote(string $url, array $patterns, ?string $token): array
    {
        $this->calls[] = [$url, $patterns, $token];

        if ($this->response instanceof FocusException) {
            throw $this->response;
        }

        return $this->response;
    }
}

/**
 * Builds a `.tar.gz` shaped like a GitHub archive: one `{repo}-{sha}/` directory holding the given files.
 */
final class FakeArchiveDownloader implements ArchiveDownloader
{
    /** @var list<array{string, ?string}> */
    public array $calls = [];

    /**
     * @param  array<string, string>  $files
     */
    public function __construct(public array $files = ['dist/two-up/index.html' => '<html></html>', 'src/pages/two-up.astro' => '']) {}

    public function download(GitHubReference $reference, string $commit, ?string $token, string $destination): void
    {
        $this->calls[] = [$commit, $token];

        $tar = tempDirectory() . '/archive.tar';
        $archive = new PharData($tar);

        foreach ($this->files as $path => $contents) {
            $archive->addFromString("{$reference->repo}-{$commit}/{$path}", $contents);
        }

        $archive->compress(Phar::GZ);

        copy("{$tar}.gz", $destination);
    }
}

function sources(FakeGitRemote $git, FakeArchiveDownloader $downloader, ?string $cache = null, ?string $token = null): TemplateSources
{
    return new TemplateSources('/repo', $cache ?? tempDirectory(), $token, $git, $downloader);
}

it('recognizes GitHub references', function (string $source, bool $expected): void {
    expect(GitHubReference::isGitHub($source))->toBe($expected);
})->with([
    ['https://github.com/awcodes/focus-templates/tree/v1.0.0/dist', true],
    ['http://www.github.com/awcodes/focus-templates/tree/main', true],
    ['github:awcodes/focus-templates/dist@v1.0.0', true],
    ['../focus-templates/dist', false],
    ['/srv/github.com/templates', false],
    ['github.com/awcodes/focus-templates', false],
]);

it('parses GitHub references', function (string $source, array $expected): void {
    $reference = GitHubReference::parse($source);

    expect([$reference->owner, $reference->repo, $reference->ref, $reference->path])->toBe($expected);
})->with([
    'tree URL with path' => ['https://github.com/awcodes/focus-templates/tree/v1.0.0/dist', ['awcodes', 'focus-templates', 'v1.0.0', 'dist']],
    'tree URL, nested path' => ['https://github.com/awcodes/focus-templates/tree/main/build/cards/', ['awcodes', 'focus-templates', 'main', 'build/cards']],
    'tree URL, root' => ['https://github.com/awcodes/focus-templates/tree/v1.0.0', ['awcodes', 'focus-templates', 'v1.0.0', '']],
    'encoded path' => ['https://github.com/awcodes/focus-templates/tree/v1/card%20templates', ['awcodes', 'focus-templates', 'v1', 'card templates']],
    'shorthand' => ['github:awcodes/focus-templates/dist@v1.0.0', ['awcodes', 'focus-templates', 'v1.0.0', 'dist']],
    'shorthand, ref with slash' => ['github:awcodes/focus-templates/dist@release/v1', ['awcodes', 'focus-templates', 'release/v1', 'dist']],
    'shorthand, root' => ['github:awcodes/focus-templates@' . COMMIT, ['awcodes', 'focus-templates', COMMIT, '']],
    'shorthand, .git suffix' => ['github:awcodes/focus-templates.git/dist@v1', ['awcodes', 'focus-templates', 'v1', 'dist']],
]);

it('rejects malformed GitHub references', function (string $source): void {
    GitHubReference::parse($source);
})->with([
    'no ref in URL' => 'https://github.com/awcodes/focus-templates',
    'blob URL' => 'https://github.com/awcodes/focus-templates/blob/main/dist/index.html',
    'no ref in shorthand' => 'github:awcodes/focus-templates/dist',
    'traversal' => 'github:awcodes/focus-templates/dist/../..@v1',
    'invalid ref' => 'github:awcodes/focus-templates/dist@v1..v2',
])->throws(FocusException::class);

it('uses a local path as-is', function (): void {
    $resolved = sources(new FakeGitRemote, new FakeArchiveDownloader)->resolve('../templates/dist/');

    expect($resolved->directory->path)->toBe('/repo/../templates/dist')
        ->and($resolved->label)->toBe('../templates/dist/')
        ->and($resolved->warnings)->toBe([])
        ->and((new TemplateSources('/repo', tempDirectory()))->resolve('/srv/templates')->directory->path)->toBe('/srv/templates');
});

it('downloads a tag once and extracts only its path', function (): void {
    $git = new FakeGitRemote([TAG_OBJECT . "\trefs/tags/v1.0.0", COMMIT . "\trefs/tags/v1.0.0^{}"]);
    $downloader = new FakeArchiveDownloader;
    $cache = tempDirectory();

    $first = sources($git, $downloader, $cache, 'secret')->resolve('https://github.com/Awcodes/focus-templates/tree/v1.0.0/dist');
    $second = sources($git, $downloader, $cache, 'secret')->resolve('https://github.com/Awcodes/focus-templates/tree/v1.0.0/dist');

    expect($git->calls[0])->toBe(['https://github.com/Awcodes/focus-templates.git', ['v1.0.0', 'v1.0.0^{}'], 'secret'])
        ->and($downloader->calls)->toBe([[COMMIT, 'secret']])
        ->and($first->directory->path)->toBe("{$cache}/github/awcodes/focus-templates/" . COMMIT . '/dist')
        ->and($second->directory->path)->toBe($first->directory->path)
        ->and(file_get_contents("{$first->directory->path}/two-up/index.html"))->toBe('<html></html>')
        ->and(file_exists("{$first->directory->path}/../src"))->toBeFalse()
        ->and($first->directory->find('two-up'))->toEndWith('/dist/two-up/index.html')
        ->and($first->label)->toBe('Awcodes/focus-templates@v1.0.0 (46538da) dist')
        ->and($first->warnings)->toBe([])
        ->and(glob("{$cache}/github/awcodes/focus-templates/" . COMMIT . '/.*-*'))->toBe([]);
});

it('uses a lightweight tag and a full commit SHA directly', function (): void {
    $git = new FakeGitRemote([OTHER_COMMIT . "\trefs/tags/v2"]);
    $downloader = new FakeArchiveDownloader;

    sources($git, $downloader)->resolve('github:awcodes/focus-templates/dist@v2');
    sources($git, $downloader)->resolve('github:awcodes/focus-templates/dist@' . COMMIT);

    expect(count($git->calls))->toBe(1)
        ->and(array_column($downloader->calls, 0))->toBe([OTHER_COMMIT, COMMIT]);
});

it('warns that a branch is not reproducible and follows new commits', function (): void {
    $cache = tempDirectory();
    $downloader = new FakeArchiveDownloader;

    $first = sources(new FakeGitRemote([COMMIT . "\trefs/heads/main", OTHER_COMMIT . "\trefs/heads/feature/main"]), $downloader, $cache)
        ->resolve('github:awcodes/focus-templates/dist@main');
    $moved = sources(new FakeGitRemote([OTHER_COMMIT . "\trefs/heads/main"]), $downloader, $cache)
        ->resolve('github:awcodes/focus-templates/dist@main');

    expect($first->warnings)->toBe(['Card templates follow the main branch of awcodes/focus-templates, so cards can change without any change in this repository. Pin a tag or commit for reproducible output.'])
        ->and($first->directory->path)->toContain(COMMIT)
        ->and($moved->directory->path)->toContain(OTHER_COMMIT)
        ->and(array_column($downloader->calls, 0))->toBe([COMMIT, OTHER_COMMIT]);
});

it('prefers a tag over a branch with the same name', function (): void {
    $git = new FakeGitRemote([OTHER_COMMIT . "\trefs/heads/v1", COMMIT . "\trefs/tags/v1"]);

    $resolved = sources($git, new FakeArchiveDownloader)->resolve('github:awcodes/focus-templates/dist@v1');

    expect($resolved->directory->path)->toContain(COMMIT)
        ->and($resolved->warnings)->toBe([]);
});

it('downloads again with refresh', function (): void {
    $cache = tempDirectory();
    $downloader = new FakeArchiveDownloader;
    $source = 'github:awcodes/focus-templates/dist@' . COMMIT;

    sources(new FakeGitRemote, $downloader, $cache)->resolve($source);
    $downloader->files = ['dist/two-up/index.html' => 'updated'];
    $refreshed = sources(new FakeGitRemote, $downloader, $cache)->resolve($source, refresh: true);

    expect(count($downloader->calls))->toBe(2)
        ->and(file_get_contents("{$refreshed->directory->path}/two-up/index.html"))->toBe('updated');
});

it('falls back to the cached commit when GitHub cannot be reached', function (): void {
    $cache = tempDirectory();

    sources(new FakeGitRemote([COMMIT . "\trefs/tags/v1"]), new FakeArchiveDownloader, $cache)->resolve('github:awcodes/focus-templates/dist@v1');

    $offline = new FakeGitRemote(new FocusException('Could not reach https://github.com/awcodes/focus-templates.git: network down'));
    $downloader = new FakeArchiveDownloader;
    $resolved = sources($offline, $downloader, $cache)->resolve('github:awcodes/focus-templates/dist@v1');

    expect($resolved->directory->path)->toContain(COMMIT)
        ->and($downloader->calls)->toBe([])
        ->and($resolved->warnings)->toBe(['Could not reach GitHub (Could not reach https://github.com/awcodes/focus-templates.git: network down), so the cached 46538da of awcodes/focus-templates@v1 dist is used.']);
});

it('fails clearly without a cached copy when GitHub cannot be reached', function (): void {
    sources(new FakeGitRemote(new FocusException('network down')), new FakeArchiveDownloader)->resolve('github:awcodes/focus-templates/dist@v1');
})->throws(FocusException::class, 'Could not resolve awcodes/focus-templates@v1 dist: network down No cached copy is available.');

it('reports an unknown ref and a short SHA', function (): void {
    expect(fn () => sources(new FakeGitRemote([]), new FakeArchiveDownloader)->resolve('github:awcodes/focus-templates/dist@v9'))
        ->toThrow(FocusException::class, 'awcodes/focus-templates has no tag or branch named [v9].')
        ->and(fn () => sources(new FakeGitRemote, new FakeArchiveDownloader)->resolve('github:awcodes/focus-templates/dist@46538da'))
        ->toThrow(FocusException::class, 'looks like a short commit SHA');
});

it('explains a path missing from the archive', function (): void {
    $downloader = new FakeArchiveDownloader(['src/pages/two-up.astro' => '']);

    expect(fn () => sources(new FakeGitRemote, $downloader)->resolve('github:awcodes/focus-templates/dist@' . COMMIT))
        ->toThrow(FocusException::class, 'export-ignore');
});

it('resolves the default cache directory from the environment', function (): void {
    $previous = [getenv('FOCUS_CACHE_DIR'), getenv('XDG_CACHE_HOME')];

    try {
        putenv('FOCUS_CACHE_DIR=/tmp/focus-cache/');
        expect(TemplateSources::defaultCacheDirectory())->toBe('/tmp/focus-cache');

        putenv('FOCUS_CACHE_DIR');
        putenv('XDG_CACHE_HOME=/tmp/xdg');
        expect(TemplateSources::defaultCacheDirectory())->toBe('/tmp/xdg/focus');
    } finally {
        putenv($previous[0] === false ? 'FOCUS_CACHE_DIR' : "FOCUS_CACHE_DIR={$previous[0]}");
        putenv($previous[1] === false ? 'XDG_CACHE_HOME' : "XDG_CACHE_HOME={$previous[1]}");
    }
});

it('downloads from GitHub', function (): void {
    if (getenv('FOCUS_SKIP_NETWORK_TESTS') || @fsockopen('github.com', 443, timeout: 3) === false) {
        $this->markTestSkipped('GitHub is not reachable.');
    }

    $cache = tempDirectory();
    $sources = new TemplateSources('/repo', $cache);

    $resolved = $sources->resolve('https://github.com/awcodes/focus/tree/v0.1.1/src/Enums');

    expect($resolved->label)->toBe('awcodes/focus@v0.1.1 (46538da) src/Enums')
        ->and(file_exists("{$resolved->directory->path}/Size.php"))->toBeTrue()
        ->and(file_exists("{$resolved->directory->path}/../src"))->toBeFalse()
        ->and(json_decode((string) file_get_contents("{$cache}/github/awcodes/focus/refs.json"), true))
        ->toBe(['v0.1.1' => ['commit' => COMMIT, 'branch' => false]]);

    $offline = new TemplateSources('/repo', $cache, git: new FakeGitRemote(new FocusException('offline')), downloader: new HttpArchiveDownloader);

    expect($offline->resolve('https://github.com/awcodes/focus/tree/v0.1.1/src/Enums')->directory->path)->toBe($resolved->directory->path);
})->group('network');
