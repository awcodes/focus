<?php

declare(strict_types=1);

function manifestFixture(string $name): string
{
    return __DIR__ . '/src/Fixtures/' . $name;
}

function tempDirectory(): string
{
    $path = sys_get_temp_dir() . '/focus-tests-' . bin2hex(random_bytes(6));

    mkdir($path, recursive: true);

    return $path;
}

function browserAvailable(): bool
{
    return is_dir(dirname(__DIR__) . '/vendor/playwright-php/playwright/bin/node_modules/playwright');
}

/**
 * Serve the fixture application with PHP's built-in server and return its base URL.
 */
function fixtureServer(): string
{
    static $url = null;

    if ($url !== null) {
        return $url;
    }

    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $name = (string) stream_socket_get_name($socket, false);
    fclose($socket);
    $port = (int) substr($name, (int) strrpos($name, ':') + 1);

    $process = new Symfony\Component\Process\Process(
        [PHP_BINARY, '-S', "127.0.0.1:{$port}", __DIR__ . '/src/Fixtures/app/router.php'],
        __DIR__ . '/src/Fixtures/app',
    );
    $process->setTimeout(10);
    $process->start();
    $process->waitUntil(fn (string $type, string $output): bool => str_contains($output, 'started'));
    $process->setTimeout(null);

    register_shutdown_function(fn () => $process->stop(1));

    return $url = "http://127.0.0.1:{$port}";
}

/**
 * @return array{int, int, int}
 */
function pixel(string $path, int $x, int $y): array
{
    $image = imagecreatefrompng($path);
    $color = imagecolorat($image, $x, $y);

    return [($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF];
}
