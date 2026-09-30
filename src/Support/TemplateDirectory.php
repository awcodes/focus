<?php

declare(strict_types=1);

namespace Awcodes\Focus\Support;

use Awcodes\Focus\Exceptions\FocusException;

/**
 * A local directory of built card templates, such as an Astro `dist/`.
 */
final readonly class TemplateDirectory
{
    public function __construct(
        public string $path,
    ) {}

    /**
     * Find a template's entry file: `{name}.html` (Astro `build.format: 'file'`) or `{name}/index.html` (the default).
     */
    public function find(string $name): string
    {
        if (! is_dir($this->path)) {
            throw new FocusException("The card template directory [{$this->path}] does not exist.");
        }

        $candidates = array_values(array_filter(
            [
                $this->path . DIRECTORY_SEPARATOR . $name . '.html',
                $this->path . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'index.html',
            ],
            is_file(...),
        ));

        return match (count($candidates)) {
            1 => $candidates[0],
            0 => throw new FocusException("Card template [{$name}] not found: expected [{$name}.html] or [{$name}/index.html] in [{$this->path}]."),
            default => throw new FocusException("Card template [{$name}] is ambiguous: both [{$name}.html] and [{$name}/index.html] exist in [{$this->path}]."),
        };
    }
}
