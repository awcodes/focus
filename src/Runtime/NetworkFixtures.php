<?php

declare(strict_types=1);

namespace Awcodes\Focus\Runtime;

use Awcodes\Focus\Fixture;
use Playwright\Browser\BrowserContextInterface;
use Playwright\Network\RouteInterface;
use Throwable;

/**
 * Answers a capture's remote requests from local files, and reports the remote requests nothing answered.
 */
final class NetworkFixtures
{
    /** @var list<string> */
    private array $errors = [];

    /**
     * @param  list<Fixture>  $fixtures  checked in order, before the built-in ui-avatars.com stand-in
     * @param  list<string>  $allowed  remote URL patterns that may load from the network without a warning
     */
    public function __construct(
        private readonly array $fixtures,
        private readonly array $allowed = [],
    ) {}

    public static function origin(string $url): string
    {
        $parts = parse_url($url);
        $port = isset($parts['port']) ? ":{$parts['port']}" : '';

        return ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? '') . $port;
    }

    /**
     * Route matching requests to their fixtures. playwright-php hands a request to the first matching handler
     * in registration order, so manifest fixtures are registered before the built-in one and win over it.
     */
    public function install(BrowserContextInterface $context): void
    {
        foreach ($this->fixtures as $fixture) {
            $context->route($fixture->url, fn (RouteInterface $route) => $this->serve($route, $fixture));
        }

        $context->route(UiAvatars::URL, function (RouteInterface $route): void {
            $route->fulfill([
                'status' => 200,
                'contentType' => 'image/svg+xml',
                'body' => UiAvatars::svg($route->request()->url()),
            ]);
        });
    }

    /**
     * Problems serving fixtures. A route handler cannot fail the capture itself, so the runner checks these.
     *
     * @return list<string>
     */
    public function errors(): array
    {
        return array_values(array_unique($this->errors));
    }

    /**
     * One warning per origin for remote URLs that loaded from the network: no fixture answered them and they are not allowed.
     *
     * @param  list<string>  $urls
     * @return list<string>
     */
    public function warnings(array $urls): array
    {
        $byOrigin = [];

        foreach (array_values(array_unique($urls)) as $url) {
            if ($this->answered($url) || $this->isAllowed($url)) {
                continue;
            }

            $byOrigin[self::origin($url)][] = $url;
        }

        $warnings = [];

        foreach ($byOrigin as $origin => $loaded) {
            $more = count($loaded) > 1 ? ' and ' . (count($loaded) - 1) . ' more' : '';

            $warnings[] = "Loaded {$loaded[0]}{$more} from {$origin} over the network, so this capture can change between runs. Serve it with fixture() or accept it with allowRemote().";
        }

        return $warnings;
    }

    private function serve(RouteInterface $route, Fixture $fixture): void
    {
        $url = $route->request()->url();

        try {
            $path = $fixture->path($url);
        } catch (Throwable $e) {
            $this->errors[] = "The fixture for {$fixture->url} threw for {$url}: {$e->getMessage()}";
            $route->fulfill(['status' => 500, 'contentType' => 'text/plain; charset=utf-8', 'body' => 'Fixture failed']);

            return;
        }

        // Playwright never answers a fulfill() for a missing file, which would hang the page, so answer 404 here.
        if (! is_file($path)) {
            $this->errors[] = "The fixture for {$url} is missing: {$path}.";
            $route->fulfill(['status' => 404, 'contentType' => 'text/plain; charset=utf-8', 'body' => 'Fixture not found']);

            return;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $route->fulfill([
            'status' => 200,
            'path' => $path,
            'contentType' => CardRenderer::CONTENT_TYPES[$extension] ?? 'application/octet-stream',
        ]);
    }

    private function answered(string $url): bool
    {
        if (fnmatch(UiAvatars::URL, $url)) {
            return true;
        }

        foreach ($this->fixtures as $fixture) {
            if ($fixture->matches($url)) {
                return true;
            }
        }

        return false;
    }

    private function isAllowed(string $url): bool
    {
        foreach ($this->allowed as $pattern) {
            if (fnmatch($pattern, $url)) {
                return true;
            }
        }

        return false;
    }
}
