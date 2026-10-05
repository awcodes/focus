<?php

declare(strict_types=1);

namespace Awcodes\Focus\Authentication;

use JsonException;

/**
 * Keeps the signed-in browser state between runs, so repeated runs do not sign in every time and trip login
 * rate limits. Only form logins use it; a stale session is harmless because FormLogin verifies it first.
 *
 * Stored in the system temp directory, never in the repository.
 *
 * @internal
 */
final readonly class SessionCache
{
    public function __construct(
        public string $path,
    ) {}

    public static function for(string $workingDirectory, FormLogin $login, ?string $directory = null): self
    {
        $key = sha1(implode('|', [realpath($workingDirectory) ?: $workingDirectory, $login->path, $login->email]));

        return new self(($directory ?? sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'focus-sessions') . DIRECTORY_SEPARATOR . "{$key}.json");
    }

    /**
     * @return array<string, mixed>|null
     */
    public function load(): ?array
    {
        if (! is_file($this->path)) {
            return null;
        }

        try {
            $state = json_decode((string) file_get_contents($this->path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($state) && isset($state['cookies']) ? $state : null;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public function save(array $state): void
    {
        $directory = dirname($this->path);

        if (! is_dir($directory) && ! @mkdir($directory, 0o700, true) && ! is_dir($directory)) {
            return;
        }

        $temporary = "{$this->path}." . bin2hex(random_bytes(4));

        if (@file_put_contents($temporary, json_encode($state, JSON_UNESCAPED_SLASHES)) === false) {
            return;
        }

        @chmod($temporary, 0o600);
        @rename($temporary, $this->path);
    }

    public function forget(): void
    {
        if (is_file($this->path)) {
            @unlink($this->path);
        }
    }
}
