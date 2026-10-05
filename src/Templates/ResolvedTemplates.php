<?php

declare(strict_types=1);

namespace Awcodes\Focus\Templates;

use Awcodes\Focus\Support\TemplateDirectory;

/**
 * @internal
 */
final readonly class ResolvedTemplates
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public TemplateDirectory $directory,
        public string $label,
        public array $warnings = [],
    ) {}
}
