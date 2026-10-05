<?php

declare(strict_types=1);

namespace Awcodes\Focus\Authentication;

use Awcodes\Focus\Enums\FailureReason;
use Awcodes\Focus\Exceptions\CaptureException;
use Awcodes\Focus\Runtime\Timeouts;
use Awcodes\Focus\Steps\Visit;
use Playwright\Exception\PlaywrightExceptionInterface;
use Playwright\Locator\LocatorInterface;
use Playwright\Page\PageInterface;

/**
 * Signs in through the application's normal login form. Defaults follow the awcodes Workbench standard.
 *
 * @internal
 */
final readonly class FormLogin implements Authenticator
{
    public const string EMAIL = 'test@example.com';

    public const string PASSWORD = 'password';

    public const string PATH = '/admin/login';

    public function __construct(
        public string $email = self::EMAIL,
        public string $password = self::PASSWORD,
        public string $path = self::PATH,
        public int $timeout = 15_000,
    ) {}

    public function authenticate(PageInterface $page, string $baseUrl): void
    {
        $url = Visit::resolve($this->path, $baseUrl);
        $response = $page->goto($url);

        if ($response instanceof \Playwright\Network\ResponseInterface && $response->status() >= 400) {
            throw new CaptureException(
                FailureReason::Authentication,
                "The login page returned HTTP {$response->status()}. If the application has no login at [{$this->path}], use ->withoutLogin() or ->login(path: '...') in focus.php. If it does, check that the Workbench is built (`composer build`).",
                url: $url,
            );
        }

        $path = (string) parse_url($url, PHP_URL_PATH);

        // Already signed in, or the app redirected away from the login page.
        if (rtrim((string) parse_url($page->url(), PHP_URL_PATH), '/') !== rtrim($path, '/')) {
            return;
        }

        try {
            $this->field($page, 'input[type="email"], input[name="email"]')->fill($this->email);
            $this->field($page, 'input[type="password"]')->fill($this->password);
            $this->field($page, 'button[type="submit"], input[type="submit"]')->click();

            $page->waitForFunction(
                '(path) => window.location.pathname.replace(/\/$/, "") !== path.replace(/\/$/, "")',
                $path,
                ['timeout' => $this->timeout],
            );
        } catch (PlaywrightExceptionInterface $e) {
            if (! Timeouts::is($e)) {
                throw $e;
            }

            if ($this->throttled($page)) {
                throw new CaptureException(
                    FailureReason::Authentication,
                    'The application is rate-limiting sign-in attempts. Wait a minute and run Focus again.',
                    url: $url,
                    previous: $e,
                );
            }

            throw new CaptureException(
                FailureReason::Authentication,
                "Signing in as [{$this->email}] did not leave the login page. Check that the Workbench is seeded with this account (`composer build`), or configure ->login(...) in focus.php.",
                url: $url,
                previous: $e,
            );
        }
    }

    private function field(PageInterface $page, string $selector): LocatorInterface
    {
        $locator = $page->locator($selector)->first();

        try {
            $locator->waitFor(['state' => 'visible']);
        } catch (PlaywrightExceptionInterface $e) {
            throw new CaptureException(
                FailureReason::Authentication,
                "The login page at [{$this->path}] has no [{$selector}] field. Configure ->login(...) or ->authenticateUsing(...) in focus.php.",
                $selector,
                previous: $e,
            );
        }

        return $locator;
    }

    /**
     * Filament (and Laravel's own throttling) reports too many attempts on the login page itself.
     */
    private function throttled(PageInterface $page): bool
    {
        try {
            return preg_match('/too many (login )?attempts/i', $page->locator('body')->innerText()) === 1;
        } catch (PlaywrightExceptionInterface) {
            return false;
        }
    }
}
