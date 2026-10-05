<?php

declare(strict_types=1);

namespace Awcodes\Focus\Support;

use Awcodes\Focus\Exceptions\FocusException;

/**
 * Reads a template's fixed design size from `<meta name="focus:canvas" content="2560x1440">`.
 *
 * @internal
 */
final class TemplateCanvas
{
    public static function read(string $html): ?Dimensions
    {
        if (preg_match_all('/<meta\b[^>]*>/i', $html, $tags) === 0) {
            return null;
        }

        foreach ($tags[0] as $tag) {
            if (preg_match('/\bname\s*=\s*["\']?focus:canvas["\'\s>\/]/i', $tag) !== 1) {
                continue;
            }

            $content = preg_match('/\bcontent\s*=\s*["\']?([^"\'\s>]*)/i', $tag, $match) === 1 ? $match[1] : '';

            if (preg_match('/^(\d+)x(\d+)$/', $content, $dimensions) !== 1 || (int) $dimensions[1] < 1 || (int) $dimensions[2] < 1) {
                throw new FocusException("The focus:canvas meta tag must look like content=\"2560x1440\", [{$content}] given.");
            }

            return new Dimensions((int) $dimensions[1], (int) $dimensions[2]);
        }

        return null;
    }
}
