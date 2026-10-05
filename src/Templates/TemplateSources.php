<?php

declare(strict_types=1);

namespace Awcodes\Focus\Templates;

use Awcodes\Focus\Exceptions\FocusException;
use Awcodes\Focus\Support\TemplateDirectory;
use FilesystemIterator;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

/**
 * Turns the configured card template source into a local directory: a path is used as-is, and a GitHub reference is
 * downloaded once per commit into a cache, so tags and SHAs cost nothing after the first run.
 *
 * @internal
 */
final readonly class TemplateSources
{
    public function __construct(
        private string $rootPath,
        private string $cacheDirectory,
        private ?string $token = null,
        private GitRemote $git = new ProcessGitRemote,
        private ArchiveDownloader $downloader = new HttpArchiveDownloader,
    ) {}

    public static function fromEnvironment(string $rootPath): self
    {
        return new self($rootPath, self::defaultCacheDirectory(), self::environment('FOCUS_GITHUB_TOKEN') ?? self::environment('GITHUB_TOKEN'));
    }

    public static function defaultCacheDirectory(): string
    {
        if (($path = self::environment('FOCUS_CACHE_DIR')) !== null) {
            return rtrim($path, '/\\');
        }

        if (($path = self::environment('XDG_CACHE_HOME')) !== null) {
            return rtrim($path, '/\\') . DIRECTORY_SEPARATOR . 'focus';
        }

        if (($home = self::environment('HOME') ?? self::environment('USERPROFILE')) !== null) {
            return rtrim($home, '/\\') . DIRECTORY_SEPARATOR . '.cache' . DIRECTORY_SEPARATOR . 'focus';
        }

        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'focus-cache';
    }

    public function resolve(string $source, bool $refresh = false): ResolvedTemplates
    {
        if (! GitHubReference::isGitHub($source)) {
            return new ResolvedTemplates(new TemplateDirectory($this->localPath($source)), $source);
        }

        $reference = GitHubReference::parse($source);
        $warnings = [];
        [$commit, $branch] = $this->commit($reference, $warnings);

        if ($branch) {
            $warnings[] = "Card templates follow the {$reference->ref} branch of {$reference->owner}/{$reference->repo}, so cards can change without any change in this repository. Pin a tag or commit for reproducible output.";
        }

        return new ResolvedTemplates(
            new TemplateDirectory($this->download($reference, $commit, $refresh)),
            $reference->label($commit),
            $warnings,
        );
    }

    private static function environment(string $name): ?string
    {
        $value = getenv($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  list<string>  $warnings
     * @return array{string, bool} the commit, and whether the ref is a branch
     */
    private function commit(GitHubReference $reference, array &$warnings): array
    {
        if ($reference->isCommit()) {
            return [$reference->ref, false];
        }

        if (preg_match('/^[0-9a-f]{7,39}$/', $reference->ref) === 1) {
            throw new FocusException("[{$reference->ref}] looks like a short commit SHA. Use the full 40-character SHA, or a tag.");
        }

        try {
            $lines = $this->git->lsRemote($reference->remoteUrl(), [$reference->ref, "{$reference->ref}^{}"], $this->token);
        } catch (FocusException $e) {
            $known = $this->knownRefs($reference)[$reference->ref] ?? null;

            if ($known === null) {
                throw new FocusException("Could not resolve {$reference->label()}: {$e->getMessage()} No cached copy is available.", $e->getCode(), previous: $e);
            }

            $warnings[] = "Could not reach GitHub ({$e->getMessage()}), so the cached " . substr($known['commit'], 0, 7) . " of {$reference->label()} is used.";

            return [$known['commit'], $known['branch']];
        }

        $refs = [];

        foreach ($lines as $line) {
            [$sha, $name] = array_pad(preg_split('/\s+/', trim($line), 2) ?: [], 2, '');
            $refs[$name] = $sha;
        }

        // An annotated tag's own SHA is the tag object; the peeled `^{}` entry is the commit it points to.
        $candidates = [
            "refs/tags/{$reference->ref}^{}" => false,
            "refs/tags/{$reference->ref}" => false,
            "refs/heads/{$reference->ref}" => true,
        ];

        foreach ($candidates as $name => $branch) {
            if (isset($refs[$name]) && preg_match('/^[0-9a-f]{40}$/', $refs[$name]) === 1) {
                $this->rememberRef($reference, $refs[$name], $branch);

                return [$refs[$name], $branch];
            }
        }

        throw new FocusException("{$reference->owner}/{$reference->repo} has no tag or branch named [{$reference->ref}].");
    }

    private function download(GitHubReference $reference, string $commit, bool $refresh): string
    {
        $base = $this->repositoryCache($reference) . DIRECTORY_SEPARATOR . $commit;
        $target = $base . DIRECTORY_SEPARATOR . ($reference->path === '' ? '_root' : str_replace('/', '~', $reference->path));

        if (is_dir($target) && ! $refresh) {
            return $target;
        }

        if (! is_dir($base) && ! @mkdir($base, 0o755, true) && ! is_dir($base)) {
            throw new FocusException("Could not create the template cache directory [{$base}].");
        }

        $id = bin2hex(random_bytes(4));
        $archive = "{$base}/.download-{$id}.tar.gz";
        $extracted = "{$base}/.extract-{$id}";
        $previous = "{$base}/.previous-{$id}";

        try {
            $this->downloader->download($reference, $commit, $this->token, $archive);

            try {
                (new PharData($archive))->extractTo($extracted, null, true);
            } catch (Throwable $e) {
                throw new FocusException("Could not extract the {$reference->owner}/{$reference->repo} archive: {$e->getMessage()}", $e->getCode(), previous: $e);
            }

            $source = $this->archiveRoot($extracted) . ($reference->path === '' ? '' : DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $reference->path));

            if (! is_dir($source)) {
                throw new FocusException(
                    "[{$reference->path}] is not in the {$reference->owner}/{$reference->repo} archive at " . substr($commit, 0, 7) . '. '
                    . 'Check that the path exists at that ref and that .gitattributes does not mark it export-ignore: GitHub leaves export-ignored paths out of archives.',
                );
            }

            if (is_dir($target)) {
                rename($target, $previous);
            }

            if (! @rename($source, $target) && ! is_dir($target)) {
                throw new FocusException("Could not move the templates into the cache at [{$target}].");
            }
        } finally {
            $this->remove($archive);
            $this->remove($extracted);
            $this->remove($previous);
        }

        return $target;
    }

    /**
     * GitHub archives contain one top-level directory, `{repo}-{sha}/`.
     */
    private function archiveRoot(string $extracted): string
    {
        $directories = array_values(array_filter(
            glob($extracted . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [],
            fn (string $path): bool => ! is_link($path),
        ));

        if (count($directories) !== 1) {
            throw new FocusException('The downloaded archive does not have the single top-level directory GitHub archives have.');
        }

        return $directories[0];
    }

    /**
     * @return array<string, array{commit: string, branch: bool}>
     */
    private function knownRefs(GitHubReference $reference): array
    {
        $file = $this->repositoryCache($reference) . DIRECTORY_SEPARATOR . 'refs.json';

        if (! is_file($file)) {
            return [];
        }

        $refs = json_decode((string) file_get_contents($file), true);

        return is_array($refs) ? array_filter(
            $refs,
            fn (mixed $entry): bool => is_array($entry) && is_string($entry['commit'] ?? null) && is_bool($entry['branch'] ?? null),
        ) : [];
    }

    /**
     * Record which commit a ref pointed to, for use when GitHub cannot be reached.
     */
    private function rememberRef(GitHubReference $reference, string $commit, bool $branch): void
    {
        $directory = $this->repositoryCache($reference);

        if (! is_dir($directory) && ! @mkdir($directory, 0o755, true) && ! is_dir($directory)) {
            return;
        }

        $refs = $this->knownRefs($reference);
        $refs[$reference->ref] = ['commit' => $commit, 'branch' => $branch];

        $temporary = $directory . DIRECTORY_SEPARATOR . '.refs-' . bin2hex(random_bytes(4)) . '.json';

        if (file_put_contents($temporary, json_encode($refs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false) {
            @rename($temporary, $directory . DIRECTORY_SEPARATOR . 'refs.json');
        }

        $this->remove($temporary);
    }

    private function repositoryCache(GitHubReference $reference): string
    {
        return implode(DIRECTORY_SEPARATOR, [$this->cacheDirectory, 'github', strtolower($reference->owner), strtolower($reference->repo)]);
    }

    private function localPath(string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1) {
            return rtrim($path, '/\\');
        }

        return rtrim($this->rootPath, '/\\') . DIRECTORY_SEPARATOR . trim($path, '/\\');
    }

    private function remove(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);

            return;
        }

        if (! is_dir($path)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $item */
        foreach ($items as $item) {
            $item->isDir() && ! $item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($path);
    }
}
