<?php

declare(strict_types=1);

namespace Awcodes\Focus\Templates;

use Awcodes\Focus\Exceptions\FocusException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

final class ProcessGitRemote implements GitRemote
{
    public function lsRemote(string $url, array $patterns, ?string $token): array
    {
        $git = (new ExecutableFinder)->find('git')
            ?? throw new FocusException('Resolving a GitHub template ref needs git on PATH. Install git, or pin the ref to a full commit SHA.');

        $command = [$git];

        if ($token !== null) {
            // GitHub accepts a token as the password for the `x-access-token` user.
            $command = [...$command, '-c', 'http.extraHeader=Authorization: Basic ' . base64_encode("x-access-token:{$token}")];
        }

        $process = new Process([...$command, 'ls-remote', $url, ...$patterns], env: ['GIT_TERMINAL_PROMPT' => '0']);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput());

            throw new FocusException(str_contains($error, 'could not read Username') || str_contains($error, 'not found')
                ? "{$url} was not found. If it is private, set GITHUB_TOKEN."
                : "Could not reach {$url}: " . ($error === '' ? 'git ls-remote failed' : strtok($error, "\n")));
        }

        return array_values(array_filter(explode("\n", trim($process->getOutput())), fn (string $line): bool => $line !== ''));
    }
}
