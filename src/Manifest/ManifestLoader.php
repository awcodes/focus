<?php

declare(strict_types=1);

namespace Awcodes\Focus\Manifest;

use Awcodes\Focus\Exceptions\InvalidManifestException;
use Awcodes\Focus\ScreenshotSuite;
use Throwable;

/**
 * @internal
 */
final readonly class ManifestLoader
{
    public const string DEFAULT_PATH = 'focus.php';

    public function __construct(
        private ManifestValidator $validator = new ManifestValidator,
    ) {}

    /**
     * Load and validate a manifest. All problems are reported before any browser work begins.
     */
    public function load(string $path): ScreenshotSuite
    {
        if (! is_file($path)) {
            throw new InvalidManifestException($path, ['File not found. Run `vendor/bin/focus init` to create one, or pass --config.']);
        }

        try {
            $suite = (static fn (string $__path): mixed => require $__path)($path);
        } catch (Throwable $e) {
            throw new InvalidManifestException($path, [$e->getMessage() . $this->location($e, $path)]);
        }

        if (! $suite instanceof ScreenshotSuite) {
            throw new InvalidManifestException($path, ['The manifest must return an instance of ' . ScreenshotSuite::class . ', ' . get_debug_type($suite) . ' returned.']);
        }

        if ($errors = $this->validator->validate($suite)) {
            throw new InvalidManifestException($path, $errors);
        }

        return $suite;
    }

    private function location(Throwable $e, string $path): string
    {
        if (realpath($e->getFile()) === realpath($path)) {
            return " (line {$e->getLine()})";
        }

        // Most manifest mistakes throw from inside Focus; point at the manifest line instead.
        foreach ($e->getTrace() as $frame) {
            if (isset($frame['file'], $frame['line']) && realpath($frame['file']) === realpath($path)) {
                return " (line {$frame['line']})";
            }
        }

        return '';
    }
}
