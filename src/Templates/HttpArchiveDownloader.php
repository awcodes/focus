<?php

declare(strict_types=1);

namespace Awcodes\Focus\Templates;

use Awcodes\Focus\Exceptions\FocusException;

/**
 * @internal
 */
final class HttpArchiveDownloader implements ArchiveDownloader
{
    public function download(GitHubReference $reference, string $commit, ?string $token, string $destination): void
    {
        // The API endpoint redirects to a signed codeload URL, which is what makes private repositories work.
        $url = $token === null
            ? "https://codeload.github.com/{$reference->owner}/{$reference->repo}/tar.gz/{$commit}"
            : "https://api.github.com/repos/{$reference->owner}/{$reference->repo}/tarball/{$commit}";

        $headers = ['User-Agent: awcodes-focus', 'Accept: application/vnd.github+json'];

        if ($token !== null) {
            $headers[] = "Authorization: Bearer {$token}";
        }

        $context = stream_context_create(['http' => [
            'header' => implode("\r\n", $headers),
            'follow_location' => 1,
            'timeout' => 60,
            'ignore_errors' => true,
        ]]);

        $body = @file_get_contents($url, false, $context);
        // `$http_response_header` is deprecated in newer PHP versions in favour of http_get_last_response_headers().
        $status = $this->status(function_exists('http_get_last_response_headers') ? http_get_last_response_headers() ?? [] : $http_response_header);

        if ($body === false || $status === null) {
            throw new FocusException("Could not download {$reference->owner}/{$reference->repo} at {$commit}: no response from GitHub.");
        }

        if ($status !== 200) {
            throw new FocusException(match ($status) {
                401, 403 => "GitHub refused the download of {$reference->owner}/{$reference->repo} (HTTP {$status}). Check GITHUB_TOKEN.",
                404 => "{$reference->owner}/{$reference->repo} at {$commit} was not found. If the repository is private, set GITHUB_TOKEN.",
                default => "Could not download {$reference->owner}/{$reference->repo} at {$commit} (HTTP {$status}).",
            });
        }

        if (file_put_contents($destination, $body) === false) {
            throw new FocusException("Could not write the template archive to [{$destination}].");
        }
    }

    /**
     * The status of the final response, after redirects.
     *
     * @param  list<string>  $headers
     */
    private function status(array $headers): ?int
    {
        $status = null;

        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $m) === 1) {
                $status = (int) $m[1];
            }
        }

        return $status;
    }
}
