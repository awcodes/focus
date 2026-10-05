<?php

declare(strict_types=1);

namespace Awcodes\Focus\Templates;

use Awcodes\Focus\Exceptions\FocusException;

/**
 * @internal
 */
interface GitRemote
{
    /**
     * `git ls-remote` output lines (`{sha}\t{ref}`) for the given patterns.
     *
     * @param  list<string>  $patterns
     * @return list<string>
     *
     * @throws FocusException when the remote cannot be reached
     */
    public function lsRemote(string $url, array $patterns, ?string $token): array;
}
