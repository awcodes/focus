<?php

declare(strict_types=1);

namespace Awcodes\Focus\Support;

use Awcodes\Focus\Exceptions\FocusException;
use JsonException;

/**
 * Card defaults from the repository's composer.json. Read locally so cards render offline and identically every run.
 */
final readonly class PackageMetadata
{
    public function __construct(
        public ?string $name,
        public ?string $description,
    ) {}

    public static function fromComposer(string $rootPath): self
    {
        $path = rtrim($rootPath, '/\\') . DIRECTORY_SEPARATOR . 'composer.json';

        if (! is_file($path)) {
            return new self(null, null);
        }

        try {
            $composer = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new FocusException("Could not read [{$path}]: {$e->getMessage()}.", $e->getCode(), previous: $e);
        }

        if (! is_array($composer)) {
            return new self(null, null);
        }

        return new self(
            name: is_string($composer['name'] ?? null) && $composer['name'] !== '' ? $composer['name'] : null,
            description: is_string($composer['description'] ?? null) ? $composer['description'] : null,
        );
    }

    /**
     * The package segment, title-cased: `awcodes/filament-curator` → `Filament Curator`.
     */
    public function title(): ?string
    {
        if ($this->name === null) {
            return null;
        }

        $segment = str_contains($this->name, '/') ? substr($this->name, strrpos($this->name, '/') + 1) : $this->name;
        $words = array_filter(preg_split('/[-_.]+/', $segment) ?: [], fn (string $word): bool => $word !== '');

        return implode(' ', array_map(ucfirst(...), $words));
    }
}
