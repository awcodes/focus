<?php

declare(strict_types=1);

namespace Awcodes\Focus\Runtime;

use Awcodes\Focus\Enums\FailureReason;
use Awcodes\Focus\Exceptions\CaptureException;
use Symfony\Component\Process\Process;

/**
 * Starts `testbench serve` on a free local port, or verifies an existing server, and stops only what it started.
 */
final readonly class WorkbenchServer
{
    private function __construct(
        public string $url,
        private ?Process $process = null,
    ) {}

    public function __destruct()
    {
        $this->stop();
    }

    /**
     * Use an application that is already running.
     */
    public static function connect(string $url, int $timeoutSeconds = 5): self
    {
        if (! self::responds($url, $timeoutSeconds)) {
            throw new CaptureException(
                FailureReason::WorkbenchUnavailable,
                "Nothing responded at [{$url}]. Start the application (e.g. `composer serve`) or omit --base-url to let Focus start Workbench.",
                url: $url,
            );
        }

        return new self(rtrim($url, '/'));
    }

    public static function start(string $workingDirectory, int $timeoutSeconds = 30): self
    {
        $testbench = $workingDirectory . '/vendor/bin/testbench';

        if (! is_file($testbench)) {
            throw new CaptureException(
                FailureReason::WorkbenchUnavailable,
                'vendor/bin/testbench was not found, so Focus cannot start Workbench. Run `composer install`, or pass --base-url to use a running application.',
            );
        }

        $port = self::freePort();
        $url = "http://127.0.0.1:{$port}";

        $process = new Process(
            [PHP_BINARY, $testbench, 'serve', '--host=127.0.0.1', "--port={$port}", '--no-reload', '--no-ansi'],
            $workingDirectory,
            ['APP_URL' => $url],
            timeout: null,
        );

        $process->start();

        $deadline = microtime(true) + $timeoutSeconds;

        while (microtime(true) < $deadline) {
            if (! $process->isRunning()) {
                throw new CaptureException(
                    FailureReason::WorkbenchUnavailable,
                    'Workbench exited before it was ready. Try `composer serve` to see why.' . self::output($process),
                    url: $url,
                );
            }

            if (self::responds($url, 1)) {
                return new self($url, $process);
            }

            usleep(200_000);
        }

        $process->stop(2);

        throw new CaptureException(
            FailureReason::WorkbenchUnavailable,
            "Workbench did not respond at [{$url}] within {$timeoutSeconds} seconds." . self::output($process),
            url: $url,
        );
    }

    public function started(): bool
    {
        return $this->process instanceof Process;
    }

    public function stop(): void
    {
        if ($this->process?->isRunning()) {
            // SIGTERM lets `artisan serve` stop the `php -S` child it spawned.
            $this->process->stop(5);
        }
    }

    private static function responds(string $url, int $timeoutSeconds): bool
    {
        $context = stream_context_create(['http' => [
            'method' => 'GET',
            'timeout' => $timeoutSeconds,
            'ignore_errors' => true,
            'follow_location' => 0,
        ]]);

        // A scoped handler rather than `@`, so host error handlers never see the expected connection failure.
        set_error_handler(fn (): bool => true);

        try {
            $handle = fopen($url, 'r', context: $context);
        } finally {
            restore_error_handler();
        }

        if ($handle === false) {
            return false;
        }

        fclose($handle);

        return true;
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

        if ($socket === false) {
            throw new CaptureException(FailureReason::WorkbenchUnavailable, "Could not find a free local port: {$errorMessage}");
        }

        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }

    private static function output(Process $process): string
    {
        $output = trim($process->getErrorOutput() . PHP_EOL . $process->getOutput());

        return $output === '' ? '' : PHP_EOL . PHP_EOL . $output;
    }
}
