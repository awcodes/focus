<?php

declare(strict_types=1);

namespace Awcodes\Focus\Runtime;

use Awcodes\Focus\Enums\FailureReason;
use Awcodes\Focus\Exceptions\CaptureException;
use Closure;

/**
 * Writes a capture to a temporary file beside its destination and renames it into place only on success,
 * so a failed capture never replaces (or appears to refresh) an existing asset.
 *
 * @internal
 */
final class AssetWriter
{
    /**
     * @param  Closure(string): mixed  $capture  writes a PNG to the given path
     */
    public static function write(string $path, Closure $capture): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0o755, true) && ! is_dir($directory)) {
            throw new CaptureException(FailureReason::Output, "Could not create the output directory [{$directory}].");
        }

        // A dotfile with a `.png` suffix: Playwright infers the image type from the extension,
        // and the name never matches the `*-{theme}.png` orphan pattern.
        $temporary = $directory . DIRECTORY_SEPARATOR . '.' . pathinfo($path, PATHINFO_FILENAME) . '.' . bin2hex(random_bytes(4)) . '.focus.png';

        try {
            $capture($temporary);

            if (! is_file($temporary) || filesize($temporary) === 0) {
                throw new CaptureException(FailureReason::Output, 'The browser did not produce an image.');
            }

            if (! @rename($temporary, $path)) {
                throw new CaptureException(FailureReason::Output, "Could not move the capture into place at [{$path}].");
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }
}
