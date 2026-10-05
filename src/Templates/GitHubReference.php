<?php

declare(strict_types=1);

namespace Awcodes\Focus\Templates;

use Awcodes\Focus\Exceptions\FocusException;

/**
 * A template directory in a GitHub repository at a ref. Accepted forms:
 *
 *   https://github.com/{owner}/{repo}/tree/{ref}/{path}
 *   https://github.com/{owner}/{repo}/tree/{ref}
 *   github:{owner}/{repo}/{path}@{ref}
 *   github:{owner}/{repo}@{ref}
 *
 * In the URL form the first segment after `tree/` is the ref, so a ref containing `/` needs the shorthand form,
 * where the ref comes last.
 *
 * @internal
 */
final readonly class GitHubReference
{
    private const string NAME = '[A-Za-z0-9_.-]+';

    public function __construct(
        public string $owner,
        public string $repo,
        public string $ref,
        public string $path,
    ) {}

    public static function isGitHub(string $source): bool
    {
        return preg_match('#^(https?://(www\.)?github\.com/|github:)#i', $source) === 1;
    }

    /**
     * @throws FocusException when the source is a GitHub reference that cannot be parsed
     */
    public static function parse(string $source): self
    {
        $source = trim($source);

        if (preg_match('#^github:(' . self::NAME . ')/(' . self::NAME . ')(?:/([^@]+))?@(.+)$#', $source, $m) === 1) {
            return self::make($source, $m[1], $m[2], $m[4], $m[3]);
        }

        if (preg_match('#^https?://(?:www\.)?github\.com/(' . self::NAME . ')/(' . self::NAME . ')/tree/([^/]+)(?:/(.*))?$#i', $source, $m) === 1) {
            return self::make($source, $m[1], $m[2], rawurldecode($m[3]), rawurldecode($m[4] ?? ''));
        }

        throw new FocusException(
            "cardTemplates() could not parse the GitHub reference [{$source}]. Use https://github.com/{owner}/{repo}/tree/{ref}/{path} or github:{owner}/{repo}/{path}@{ref}.",
        );
    }

    public function isCommit(): bool
    {
        return preg_match('/^[0-9a-f]{40}$/', $this->ref) === 1;
    }

    public function remoteUrl(): string
    {
        return "https://github.com/{$this->owner}/{$this->repo}.git";
    }

    public function label(?string $commit = null): string
    {
        $ref = $commit === null || $this->isCommit() ? $this->ref : "{$this->ref} (" . substr($commit, 0, 7) . ')';

        return "{$this->owner}/{$this->repo}@{$ref}" . ($this->path === '' ? '' : " {$this->path}");
    }

    private static function make(string $source, string $owner, string $repo, string $ref, string $path): self
    {
        $repo = preg_replace('/\.git$/', '', $repo) ?? $repo;
        $path = trim($path, '/');

        if ($ref === '' || str_contains($ref, '..') || preg_match('/[\s~^:?*\[\\\\]/', $ref) === 1) {
            throw new FocusException("cardTemplates() reference [{$source}] has an invalid ref [{$ref}].");
        }

        foreach ($path === '' ? [] : explode('/', $path) as $segment) {
            if (in_array($segment, ['', '.', '..'], true)) {
                throw new FocusException("cardTemplates() reference [{$source}] has an invalid path [{$path}].");
            }
        }

        return new self($owner, $repo, $ref, $path);
    }
}
