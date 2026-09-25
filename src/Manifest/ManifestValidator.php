<?php

declare(strict_types=1);

namespace Awcodes\Focus\Manifest;

use Awcodes\Focus\Enums\CaptureMode;
use Awcodes\Focus\Screenshot;
use Awcodes\Focus\ScreenshotSuite;

final class ManifestValidator
{
    public const NAME_PATTERN = '/^[a-z0-9]+(-[a-z0-9]+)*$/';

    /**
     * @return list<string>
     */
    public function validate(ScreenshotSuite $suite): array
    {
        $errors = [];

        if ($suite->getScreenshots() === []) {
            $errors[] = 'The suite does not define any screenshots.';
        }

        if (($baseUrl = $suite->getBaseUrl()) !== null && preg_match('#^https?://#i', $baseUrl) !== 1) {
            $errors[] = "baseUrl() must be an absolute http(s) URL, [{$baseUrl}] given.";
        }

        if (trim($suite->getOutputPath()) === '') {
            $errors[] = 'outputPath() must not be empty.';
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
