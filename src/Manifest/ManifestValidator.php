<?php

declare(strict_types=1);

namespace Awcodes\Focus\Manifest;

use Awcodes\Focus\Card;
use Awcodes\Focus\Defaults;
use Awcodes\Focus\Enums\CaptureMode;
use Awcodes\Focus\Enums\Theme;
use Awcodes\Focus\Exceptions\FocusException;
use Awcodes\Focus\Screenshot;
use Awcodes\Focus\ScreenshotSuite;
use Awcodes\Focus\Templates\GitHubReference;

final class ManifestValidator
{
    public const NAME_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    /**
     * @return list<string>
     */
    public function validate(ScreenshotSuite $suite): array
    {
        $errors = [];

        if ($suite->getScreenshots() === [] && $suite->getCards() === []) {
            $errors[] = 'The suite does not define any screenshots or cards.';
        }

        if (($baseUrl = $suite->getBaseUrl()) !== null && preg_match('#^https?://#i', $baseUrl) !== 1) {
            $errors[] = "baseUrl() must be an absolute http(s) URL, [{$baseUrl}] given.";
        }

        if (trim($suite->getOutputPath()) === '') {
            $errors[] = 'outputPath() must not be empty.';
        }

        foreach ($suite->getFixtures() as $fixture) {
            if (preg_match('#^https?://[^/*]+#i', $fixture->url) !== 1) {
                $errors[] = "fixture() needs an absolute http(s) URL pattern with a host, such as https://example.com/**, [{$fixture->url}] given.";
            }

            if (is_string($fixture->file) && trim($fixture->file) === '') {
                $errors[] = "fixture({$fixture->url}) needs a file.";
            }
        }

        foreach ($suite->getAllowedRemote() as $pattern) {
            if (preg_match('#^https?://#i', $pattern) !== 1) {
                $errors[] = "allowRemote() needs absolute http(s) URL patterns, [{$pattern}] given.";
            }
        }

        $seen = [];

        foreach ($suite->getScreenshots() as $screenshot) {
            $name = $screenshot->getName();

            if (isset($seen[$name])) {
                $errors[] = "Duplicate screenshot name [{$name}].";

                continue;
            }

            $seen[$name] = true;

            array_push($errors, ...$this->validateScreenshot($screenshot));
        }

        if ($suite->getCards() !== []) {
            array_push($errors, ...$this->validateCardSettings($suite));
        }

        foreach ($suite->getCards() as $card) {
            $name = $card->getName();

            if (isset($seen[$name])) {
                $errors[] = "Duplicate name [{$name}]: screenshot and card names must be unique across the suite.";

                continue;
            }

            $seen[$name] = true;

            array_push($errors, ...$this->validateCard($suite, $card));
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function validateCardSettings(ScreenshotSuite $suite): array
    {
        $errors = [];
        $templates = $suite->getCardTemplates();

        if ($templates === null) {
            $errors[] = 'cards() requires cardTemplates(): the directory of built card templates.';
        } elseif (trim($templates) === '') {
            $errors[] = 'cardTemplates() must not be empty.';
        } elseif (GitHubReference::isGitHub($templates)) {
            try {
                GitHubReference::parse($templates);
            } catch (FocusException $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (trim($suite->getCardOutputPath()) === '') {
            $errors[] = 'cardOutputPath() must not be empty.';
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function validateCard(ScreenshotSuite $suite, Card $card): array
    {
        $name = $card->getName();
        $errors = [];

        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            $errors[] = "Card name [{$name}] must be lowercase kebab-case (e.g. [social]).";
        }

        $themes = $card->getThemes() ?? Defaults::CARD_THEMES;

        foreach (array_unique($card->getScreenshots()) as $reference) {
            $screenshot = $suite->findScreenshot($reference);

            if (! $screenshot instanceof Screenshot) {
                $errors[] = "Card [{$name}] uses unknown screenshot [{$reference}].";

                continue;
            }

            $missing = array_udiff($themes, $suite->themesFor($screenshot), fn (Theme $a, Theme $b): int => strcmp($a->value, $b->value));

            if ($missing !== []) {
                $list = implode(', ', array_map(fn (Theme $theme): string => $theme->value, $missing));
                $errors[] = "Card [{$name}] renders in [{$list}], but screenshot [{$reference}] is not captured in that theme. Add it to the screenshot's themes() or change the card's themes().";
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function validateScreenshot(Screenshot $screenshot): array
    {
        $name = $screenshot->getName();
        $errors = [];

        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            $errors[] = "Screenshot name [{$name}] must be lowercase kebab-case (e.g. [brick-picker]).";
        }

        if ($screenshot->getUrl() === null) {
            $errors[] = "Screenshot [{$name}] never calls visit().";
        }

        $modes = $screenshot->getCaptureModes();

        if (count($modes) > 1) {
            $calls = implode(', ', array_map(fn (CaptureMode $mode): string => "{$mode->value}()", $modes));
            $errors[] = "Screenshot [{$name}] has more than one capture mode ({$calls}); use exactly one of focus(), viewport(), or fullPage().";
        }

        $mode = $screenshot->getCaptureMode();

        if ($mode === CaptureMode::Focus && trim((string) $screenshot->getFocusSelector()) === '') {
            $errors[] = "Screenshot [{$name}] calls focus() with an empty selector.";
        }

        if ($mode !== CaptureMode::Focus) {
            if ($screenshot->getPadding() !== null) {
                $errors[] = "Screenshot [{$name}] sets padding(), which only applies to focus() captures.";
            }

            if ($screenshot->getMinSize() instanceof \Awcodes\Focus\Contracts\HasDimensions) {
                $errors[] = "Screenshot [{$name}] sets minSize(), which only applies to focus() captures.";
            }
        }

        return $errors;
    }
}
