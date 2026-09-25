<?php

declare(strict_types=1);

namespace Awcodes\Focus\Exceptions;

final class InvalidManifestException extends FocusException
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public readonly string $path,
        public readonly array $errors,
    ) {
        parent::__construct(
            "Invalid Focus manifest [{$path}]:" . PHP_EOL . implode(PHP_EOL, array_map(fn (string $error): string => "  - {$error}", $errors)),
        );
    }
}
