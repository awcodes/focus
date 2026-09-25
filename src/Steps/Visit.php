<?php

declare(strict_types=1);

namespace Awcodes\Focus\Steps;

use Awcodes\Focus\Enums\FailureReason;
use Awcodes\Focus\Exceptions\CaptureException;
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
        $url = self::resolve($this->url, $baseUrl);
        $response = $page->goto($url);

        if ($response instanceof \Playwright\Network\ResponseInterface && $response->status() >= 400) {
            throw new CaptureException(FailureReason::Navigation, "visit({$this->url}) returned HTTP {$response->status()}.", url: $url);
        }
    }

    public function describe(): string
    {
        return "visit({$this->url})";
    }
}
