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
