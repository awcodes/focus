<?php

declare(strict_types=1);

namespace Awcodes\Focus\Runtime;

use Awcodes\Focus\Capture;
use Awcodes\Focus\Enums\Theme;

/**
 * Finds files in the output directory that follow Focus's filename scheme but were not produced by the manifest.
 * Matching is pattern-based, so files that do not end in `-{theme}.png` are never considered.
 */
final class Orphans
{
    /**
     * @param  list<Capture>  $captures  every capture in the unfiltered plan
     * @return list<string>
     */
    public static function find(string $outputDirectory, array $captures): array
    {
        if (! is_dir($outputDirectory)) {
            return [];
        }

        $expected = array_flip(array_map(fn (Capture $capture): string => basename($capture->path), $captures));
        $suffixes = array_map(fn (Theme $theme): string => "-{$theme->value}.png", Theme::cases());

        $orphans = [];

        foreach (scandir($outputDirectory) ?: [] as $file) {
            if (str_starts_with($file, '.') || isset($expected[$file])) {
                continue;
            }

            if (! is_file($outputDirectory . DIRECTORY_SEPARATOR . $file)) {
                continue;
            }

            foreach ($suffixes as $suffix) {
                if (str_ends_with($file, $suffix) && strlen($file) > strlen($suffix)) {
                    $orphans[] = $outputDirectory . DIRECTORY_SEPARATOR . $file;

                    break;
                }
            }
        }

        sort($orphans);

        return $orphans;
    }
}
