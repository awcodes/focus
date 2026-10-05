<?php

declare(strict_types=1);

namespace Awcodes\Focus\Templates;

use Awcodes\Focus\Exceptions\FocusException;

/**
 * @internal
 */
interface ArchiveDownloader
{
    /**
     * Download a repository's `.tar.gz` archive at a commit to the given file.
     *
     * @throws FocusException when the archive cannot be downloaded
     */
    public function download(GitHubReference $reference, string $commit, ?string $token, string $destination): void;
}
