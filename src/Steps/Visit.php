<?php

declare(strict_types=1);

namespace Awcodes\Focus\Steps;

use Playwright\Page\PageInterface;

final readonly class Visit implements Step
{
    public function __construct(
        public string $url,
    ) {}

    public static function resolve(string $url, string $baseUrl): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) === 1) {
            return $url;
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
    }

    public function run(PageInterface $page, string $baseUrl): void
    {
        $page->goto(self::resolve($this->url, $baseUrl));
    }

    public function describe(): string
    {
        return "visit({$this->url})";
    }
}
