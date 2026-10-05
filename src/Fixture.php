<?php

declare(strict_types=1);

namespace Awcodes\Focus;

use Closure;

/**
 * A local file served in place of a remote request, so captures never depend on the network.
 */
final readonly class Fixture
{
    /**
     * @param  string|Closure(string): string  $file  a path, or a closure that receives the request URL and returns one
     */
    public function __construct(
        public string $url,
        public string | Closure $file,
        public ?string $rootPath = null,
    ) {}

    /**
     * Glob matching on the whole URL, query string included: `*` and `**` both match any characters.
     */
    public function matches(string $url): bool
    {
        return fnmatch($this->url, $url);
    }

    /**
     * The file for a request, relative to the repository root unless absolute.
     */
    public function path(string $url): string
    {
        $path = $this->file instanceof Closure ? ($this->file)($url) : $this->file;

        if ($this->rootPath === null || str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1) {
            return $path;
        }

        return rtrim($this->rootPath, '/\\') . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }

    public function withRootPath(string $rootPath): self
    {
        return new self($this->url, $this->file, $rootPath);
    }
}
