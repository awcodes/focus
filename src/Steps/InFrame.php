<?php

declare(strict_types=1);

namespace Awcodes\Focus\Steps;

/**
 * @internal
 */
final class InFrame
{
    /**
     * Suffix a step description with the frame it runs in, so diagnostics say where a selector was searched.
     */
    public static function describe(string $description, ?string $frame): string
    {
        return $frame === null ? $description : "{$description} in {$frame}";
    }
}
