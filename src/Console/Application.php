<?php

declare(strict_types=1);

namespace Awcodes\Focus\Console;

use Awcodes\Focus\Console\Commands\InitCommand;
use Awcodes\Focus\Console\Commands\RunCommand;
use Composer\InstalledVersions;
use Symfony\Component\Console\Application as SymfonyApplication;

/**
 * @internal
 */
final class Application extends SymfonyApplication
{
    public function __construct(?string $workingDirectory = null)
    {
        parent::__construct('Focus', $this->version());

        $workingDirectory ??= (string) getcwd();

        $this->addCommands([
            new RunCommand($workingDirectory),
            new InitCommand($workingDirectory),
        ]);

        $this->setDefaultCommand('run');
    }

    private function version(): string
    {
        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('awcodes/focus')) {
            return InstalledVersions::getPrettyVersion('awcodes/focus') ?? 'dev';
        }

        return 'dev';
    }
}
