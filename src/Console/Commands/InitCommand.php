<?php

declare(strict_types=1);

namespace Awcodes\Focus\Console\Commands;

use Awcodes\Focus\Manifest\ManifestLoader;
use Composer\InstalledVersions;
use JsonException;
use stdClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(name: 'init', description: 'Scaffold focus.php, the composer script, and Playwright browsers')]
final class InitCommand extends Command
{
    public const COMPOSER_SCRIPT = ['Composer\\Config::disableProcessTimeout', 'focus'];

    public function __construct(
        private readonly string $workingDirectory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('skip-browsers', null, InputOption::VALUE_NONE, 'Do not install Playwright browser binaries');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->createManifest($io);
        $scriptAdded = $this->addComposerScript($io);
        if ($input->getOption('skip-browsers')) {
            $io->writeln('<comment>Skipped</comment> Playwright browser installation.');

            return $scriptAdded ? self::SUCCESS : self::FAILURE;
        }

        return $scriptAdded && $this->installBrowsers($io) ? self::SUCCESS : self::FAILURE;
    }

    private function createManifest(SymfonyStyle $io): void
    {
        $path = $this->workingDirectory . DIRECTORY_SEPARATOR . ManifestLoader::DEFAULT_PATH;

        if (file_exists($path)) {
            $io->writeln('<comment>Kept</comment> existing ' . ManifestLoader::DEFAULT_PATH . '.');

            return;
        }

        copy(dirname(__DIR__, 3) . '/stubs/focus.php', $path);

        $io->writeln('<info>Created</info> ' . ManifestLoader::DEFAULT_PATH . '.');
    }

    private function addComposerScript(SymfonyStyle $io): bool
    {
        $path = $this->workingDirectory . DIRECTORY_SEPARATOR . 'composer.json';

        if (! is_file($path)) {
            $io->warning('No composer.json found; skipped adding the `focus` script.');

            return true;
        }

        try {
            $composer = json_decode((string) file_get_contents($path), flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $io->error("composer.json could not be parsed: {$e->getMessage()}");

            return false;
        }

        if (! $composer instanceof stdClass) {
            $io->error('composer.json must contain a JSON object.');

            return false;
        }

        $composer->scripts ??= new stdClass;

        if (isset($composer->scripts->focus)) {
            $io->writeln('<comment>Kept</comment> existing `focus` composer script.');

            return true;
        }

        if (! $io->confirm('Add a `focus` script to composer.json?', true)) {
            $io->writeln('<comment>Skipped</comment> the `focus` composer script.');

            return true;
        }

        $composer->scripts->focus = self::COMPOSER_SCRIPT;

        file_put_contents($path, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL);

        $io->writeln('<info>Added</info> the `focus` composer script.');

        return true;
    }

    private function installBrowsers(SymfonyStyle $io): bool
    {
        $installer = $this->installer();
        $instructions = 'Install them manually with: vendor/bin/playwright-install chromium';

        if ($installer === null) {
            $io->error("The Playwright installer could not be located. {$instructions}");

            return false;
        }

        $io->writeln('Installing the Playwright server and Chromium. This can take a minute…');

        $process = new Process([PHP_BINARY, $installer, 'chromium'], $this->workingDirectory, timeout: null);
        $process->run(fn (string $type, string $buffer) => $io->write($buffer));

        if (! $process->isSuccessful()) {
            $io->error("Installing Playwright browsers failed. {$instructions}");

            return false;
        }

        return true;
    }

    private function installer(): ?string
    {
        if (! InstalledVersions::isInstalled('playwright-php/playwright')) {
            return null;
        }

        $path = InstalledVersions::getInstallPath('playwright-php/playwright') . '/bin/playwright-install';

        return is_file($path) ? $path : null;
    }
}
