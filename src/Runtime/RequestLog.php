<?php

declare(strict_types=1);

namespace Awcodes\Focus\Runtime;

/**
 * Requests a card template made that could not be served, reported as warnings.
 *
 * @internal
 */
final class RequestLog
{
    /** @var list<string> */
    private array $blocked = [];

    /** @var list<string> */
    private array $notFound = [];

    public function blocked(string $url): void
    {
        $this->blocked[] = $url;
    }

    public function notFound(string $path): void
    {
        $this->notFound[] = $path;
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        $warnings = [];

        foreach (array_values(array_unique($this->blocked)) as $url) {
            $warnings[] = "Blocked a request outside the template directory: {$url}. Bundle remote assets such as fonts with the template.";
        }

        foreach (array_values(array_unique($this->notFound)) as $path) {
            $warnings[] = "The template requested {$path}, which is not in the template directory.";
        }

        return $warnings;
    }
}
