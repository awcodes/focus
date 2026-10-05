<?php

declare(strict_types=1);

namespace Awcodes\Focus\Runtime;

/**
 * A local stand-in for ui-avatars.com, Filament's default avatar provider. It draws the same initials on the same
 * background the URL asks for, so top-bar captures keep a realistic avatar without reaching the network.
 */
final class UiAvatars
{
    public const URL = 'https://ui-avatars.com/**';

    private const SIZE = 64;

    public static function svg(string $url): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $size = self::int($query['size'] ?? null, self::SIZE, 16, 512);
        $length = self::int($query['length'] ?? null, 2, 1, 4);
        $fontSize = self::float($query['font-size'] ?? null, 0.5);
        $rounded = in_array(strtolower(self::string($query['rounded'] ?? '')), ['1', 'true', 'yes'], true);
        $uppercase = ! in_array(strtolower(self::string($query['uppercase'] ?? '')), ['0', 'false', 'no'], true);
        $bold = in_array(strtolower(self::string($query['bold'] ?? '')), ['1', 'true', 'yes'], true);

        $initials = self::initials(self::string($query['name'] ?? ''), $length);
        $initials = $uppercase ? mb_strtoupper($initials) : $initials;

        $background = self::color($query['background'] ?? null, 'DDDDDD');
        $color = self::color($query['color'] ?? null, '222222');
        $half = $size / 2;
        $text = htmlspecialchars($initials, ENT_QUOTES | ENT_XML1);
        $shape = $rounded
            ? "<circle cx=\"{$half}\" cy=\"{$half}\" r=\"{$half}\" fill=\"#{$background}\"/>"
            : "<rect width=\"{$size}\" height=\"{$size}\" fill=\"#{$background}\"/>";
        $weight = $bold ? 700 : 400;
        $pixels = round($size * $fontSize, 2);

        // Optical sizing is pinned because Chromium on macOS varies it for system fonts between loads.
        return <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" width="{$size}" height="{$size}" viewBox="0 0 {$size} {$size}">{$shape}<text x="50%" y="50%" dy=".1em" fill="#{$color}" font-size="{$pixels}" font-weight="{$weight}" text-anchor="middle" dominant-baseline="middle" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif; font-optical-sizing: none">{$text}</text></svg>
            SVG;
    }

    /**
     * The first letter of each word, as ui-avatars.com does: Filament already sends initials separated by spaces.
     */
    private static function initials(string $name, int $length): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($words) === 1) {
            return mb_substr($words[0], 0, $length);
        }

        return implode('', array_map(fn (string $word): string => mb_substr($word, 0, 1), array_slice($words, 0, $length)));
    }

    private static function color(mixed $value, string $default): string
    {
        $value = ltrim(self::string($value), '#');

        if (preg_match('/^[0-9a-f]{3}$/i', $value) === 1 || preg_match('/^[0-9a-f]{6}$/i', $value) === 1) {
            return strtoupper($value);
        }

        return $default;
    }

    private static function int(mixed $value, int $default, int $min, int $max): int
    {
        $value = self::string($value);

        return ctype_digit($value) ? max($min, min($max, (int) $value)) : $default;
    }

    private static function float(mixed $value, float $default): float
    {
        $value = self::string($value);

        return is_numeric($value) && (float) $value > 0 && (float) $value <= 1 ? (float) $value : $default;
    }

    private static function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
