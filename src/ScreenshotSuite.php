<?php

declare(strict_types=1);

namespace Awcodes\Focus;

use Awcodes\Focus\Authentication\Authenticator;
use Awcodes\Focus\Authentication\CallbackAuthenticator;
use Awcodes\Focus\Authentication\FormLogin;
use Awcodes\Focus\Concerns\HasCaptureSettings;
use Awcodes\Focus\Enums\Theme;
use Awcodes\Focus\Exceptions\FocusException;
use Awcodes\Focus\Support\PackageMetadata;
use Closure;
use InvalidArgumentException;
use Playwright\Page\PageInterface;

class ScreenshotSuite
{
    use HasCaptureSettings;

    /** @var list<Screenshot> */
    protected array $screenshots = [];

    protected ?string $outputPath = null;

    /** @var list<Card> */
    protected array $cards = [];

    protected ?string $cardTemplates = null;

    protected ?string $cardOutputPath = null;

    protected ?string $baseUrl = null;

    /** @var list<string> */
    protected array $masks = [];

    /** @var list<string> */
    protected array $hidden = [];

    protected ?Authenticator $authenticator;

    protected int $timeout = Defaults::TIMEOUT;

    protected bool $reuseSession = true;

    /** @var list<Closure(PageInterface): mixed> */
    protected array $beforeEach = [];

    final public function __construct()
    {
        $this->authenticator = new FormLogin;
    }

    public static function make(): static
    {
        return new static;
    }

    /**
     * @param  array<Screenshot>  $screenshots
     */
    public function screenshots(array $screenshots): static
    {
        foreach ($screenshots as $screenshot) {
            if (! $screenshot instanceof Screenshot) {
                throw new InvalidArgumentException('screenshots() only accepts ' . Screenshot::class . ' instances.');
            }

            $this->screenshots[] = $screenshot;
        }

        return $this;
    }

    /**
     * Where captures are written, relative to the repository root unless absolute.
     */
    public function outputPath(string $path): static
    {
        $this->outputPath = $path;

        return $this;
    }

    /**
     * @param  array<Card>  $cards
     */
    public function cards(array $cards): static
    {
        foreach ($cards as $card) {
            if (! $card instanceof Card) {
                throw new InvalidArgumentException('cards() only accepts ' . Card::class . ' instances.');
            }

            $this->cards[] = $card;
        }

        return $this;
    }

    /**
     * The directory of built card templates: a path relative to the repository root, an absolute path, or a GitHub
     * reference such as `https://github.com/{owner}/{repo}/tree/{ref}/{path}` or `github:{owner}/{repo}/{path}@{ref}`.
     */
    public function cardTemplates(string $path): static
    {
        $this->cardTemplates = $path;

        return $this;
    }

    /**
     * Where cards are written, relative to the repository root unless absolute.
     */
    public function cardOutputPath(string $path): static
    {
        $this->cardOutputPath = $path;

        return $this;
    }

    /**
     * Connect to an already-running application instead of starting Workbench.
     */
    public function baseUrl(string $url): static
    {
        $this->baseUrl = $url;

        return $this;
    }

    /**
     * Sign in through the application's login form once, before any captures. Enabled by default with Workbench credentials.
     */
    public function login(string $email = FormLogin::EMAIL, string $password = FormLogin::PASSWORD, string $path = FormLogin::PATH): static
    {
        $this->authenticator = new FormLogin($email, $password, $path);

        return $this;
    }

    /**
     * Reuse the signed-in session from the previous run instead of signing in every time. Applies to `login()` only;
     * a session that is no longer valid is detected and replaced with a normal sign-in.
     */
    public function reuseSession(bool $condition = true): static
    {
        $this->reuseSession = $condition;

        return $this;
    }

    public function withoutLogin(): static
    {
        $this->authenticator = null;

        return $this;
    }

    /**
     * Establish authenticated state with the Playwright page directly. Cookies and storage are reused by every capture.
     *
     * @param  (Closure(PageInterface, string): mixed)|Authenticator  $authenticator  a closure receives the page and the base URL
     */
    public function authenticateUsing(Closure | Authenticator $authenticator): static
    {
        $this->authenticator = $authenticator instanceof Closure ? new CallbackAuthenticator($authenticator) : $authenticator;

        return $this;
    }

    /**
     * The default timeout, in milliseconds, for navigation, interactions, and selectors.
     */
    public function timeout(int $milliseconds): static
    {
        if ($milliseconds < 1) {
            throw new InvalidArgumentException("timeout() must be greater than zero, [{$milliseconds}] given.");
        }

        $this->timeout = $milliseconds;

        return $this;
    }

    /**
     * Mask matching elements in every screenshot, in addition to each screenshot's own masks.
     */
    public function mask(string ...$selectors): static
    {
        array_push($this->masks, ...$selectors);

        return $this;
    }

    /**
     * Hide matching elements in every screenshot, in addition to each screenshot's own `hide()` selectors.
     */
    public function hide(string ...$selectors): static
    {
        array_push($this->hidden, ...$selectors);

        return $this;
    }

    /**
     * Run before every screenshot, after authentication and before the screenshot's own `before()`.
     *
     * @param  Closure(PageInterface): mixed  $callback
     */
    public function beforeEach(Closure $callback): static
    {
        $this->beforeEach[] = $callback;

        return $this;
    }

    /**
     * @return list<Screenshot>
     */
    public function getScreenshots(): array
    {
        return $this->screenshots;
    }

    public function getOutputPath(): string
    {
        return $this->outputPath ?? Defaults::OUTPUT_PATH;
    }

    /**
     * @return list<Card>
     */
    public function getCards(): array
    {
        return $this->cards;
    }

    public function getCardTemplates(): ?string
    {
        return $this->cardTemplates;
    }

    public function getCardOutputPath(): string
    {
        return $this->cardOutputPath ?? Defaults::CARD_OUTPUT_PATH;
    }

    public function findScreenshot(string $name): ?Screenshot
    {
        foreach ($this->screenshots as $screenshot) {
            if ($screenshot->getName() === $name) {
                return $screenshot;
            }
        }

        return null;
    }

    /**
     * @return list<Theme>
     */
    public function themesFor(Screenshot $screenshot): array
    {
        return $screenshot->getThemes() ?? $this->getThemes() ?? Defaults::THEMES;
    }

    public function getBaseUrl(): ?string
    {
        return $this->baseUrl;
    }

    public function getAuthenticator(): ?Authenticator
    {
        return $this->authenticator;
    }

    public function getReuseSession(): bool
    {
        return $this->reuseSession;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * @return list<string>
     */
    public function getMasks(): array
    {
        return $this->masks;
    }

    /**
     * @return list<string>
     */
    public function getHidden(): array
    {
        return $this->hidden;
    }

    /**
     * @return list<Closure(PageInterface): mixed>
     */
    public function getBeforeEachCallbacks(): array
    {
        return $this->beforeEach;
    }

    /**
     * Resolve every screenshot × theme into a capture, applying CLI filters. Filters only narrow what the manifest defines.
     *
     * @param  list<string>  $only
     * @return list<Capture>
     */
    public function plan(string $rootPath, array $only = [], ?Theme $theme = null): array
    {
        $this->ensureKnownNames($only);

        $captures = [];

        foreach ($this->screenshots as $screenshot) {
            if ($only !== [] && ! in_array($screenshot->getName(), $only, true)) {
                continue;
            }

            foreach ($this->themesFor($screenshot) as $screenshotTheme) {
                if ($theme instanceof Theme && $screenshotTheme !== $theme) {
                    continue;
                }

                $captures[] = Capture::resolve($this, $screenshot, $screenshotTheme, $this->resolveOutputDirectory($rootPath));
            }
        }

        return $captures;
    }

    /**
     * Resolve every card × theme × size into a render, applying CLI filters. Filters only narrow what the manifest defines.
     *
     * @param  list<string>  $only
     * @return list<CardRender>
     */
    public function planCards(string $rootPath, array $only = [], ?Theme $theme = null): array
    {
        $this->ensureKnownNames($only);

        $cards = array_values(array_filter(
            $this->cards,
            fn (Card $card): bool => $only === [] || in_array($card->getName(), $only, true),
        ));

        if ($cards === []) {
            return [];
        }

        $metadata = PackageMetadata::fromComposer($rootPath);
        $outputDirectory = $this->resolveCardOutputDirectory($rootPath);
        $screenshotDirectory = $this->resolveOutputDirectory($rootPath);

        $renders = [];

        foreach ($cards as $card) {
            foreach ($card->getThemes() ?? Defaults::CARD_THEMES as $cardTheme) {
                if ($theme instanceof Theme && $cardTheme !== $theme) {
                    continue;
                }

                foreach ($card->getSizes() ?? Defaults::CARD_SIZES as $size) {
                    $renders[] = CardRender::resolve($this, $card, $cardTheme, $size, $outputDirectory, $screenshotDirectory, $metadata);
                }
            }
        }

        return $renders;
    }

    public function resolveOutputDirectory(string $rootPath): string
    {
        return $this->resolvePath($rootPath, $this->getOutputPath());
    }

    public function resolveCardOutputDirectory(string $rootPath): string
    {
        return $this->resolvePath($rootPath, $this->getCardOutputPath());
    }

    /**
     * @param  list<string>  $only
     */
    private function ensureKnownNames(array $only): void
    {
        $names = [
            ...array_map(fn (Screenshot $screenshot): string => $screenshot->getName(), $this->screenshots),
            ...array_map(fn (Card $card): string => $card->getName(), $this->cards),
        ];

        if ($unknown = array_values(array_diff($only, $names))) {
            throw new FocusException('Unknown screenshot(s) or card(s) passed to --only: ' . implode(', ', $unknown) . '.');
        }
    }

    private function resolvePath(string $rootPath, string $path): string
    {
        if (str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1) {
            return rtrim($path, '/\\');
        }

        return rtrim($rootPath, '/\\') . DIRECTORY_SEPARATOR . trim($path, '/\\');
    }
}
