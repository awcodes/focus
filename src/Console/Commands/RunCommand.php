<?php

declare(strict_types=1);

namespace Awcodes\Focus\Console\Commands;

use Awcodes\Focus\Capture;
use Awcodes\Focus\Enums\Theme;
use Awcodes\Focus\Exceptions\FocusException;
use Awcodes\Focus\Manifest\ManifestLoader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'run', description: 'Generate the screenshots defined in focus.php')]
final class RunCommand extends Command
{
    public function __construct(
        private readonly string $workingDirectory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to the manifest', ManifestLoader::DEFAULT_PATH)
            ->addOption('only', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only capture the named screenshot(s); comma-separated or repeated')
            ->addOption('theme', null, InputOption::VALUE_REQUIRED, 'Only capture one theme (' . implode(', ', array_column(Theme::cases(), 'value')) . ')')
            ->addOption('headed', null, InputOption::VALUE_NONE, 'Show the browser while capturing')
            ->addOption('base-url', null, InputOption::VALUE_REQUIRED, 'Connect to a running application instead of starting Workbench')
            ->addOption('prune', null, InputOption::VALUE_NONE, 'Delete orphaned assets after an unfiltered run')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Prune without asking for confirmation')
            ->addOption('list', null, InputOption::VALUE_NONE, 'List the planned captures without opening a browser');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $suite = (new ManifestLoader)->load($this->path((string) $input->getOption('config')));
            $captures = $suite->plan($this->workingDirectory, $this->only($input), $this->theme($input));
        } catch (FocusException $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }

        if ($captures === []) {
            $io->warning('The filters matched no captures.');

            return self::SUCCESS;
        }

        if ($input->getOption('list')) {
            $io->table(
                ['Screenshot', 'Theme', 'Mode', 'Viewport', 'Scale', 'Output'],
                array_map(fn (Capture $capture): array => [
                    $capture->name(),
                    $capture->theme->value,
                    $capture->mode->value,
                    "{$capture->viewport->width()}x{$capture->viewport->height()}",
                    $capture->scale,
                    $this->relative($capture->path),
                ], $captures),
            );

            return self::SUCCESS;
        }

        $io->error('The browser runtime is not implemented yet. Use --list to inspect the planned captures.');

        return self::FAILURE;
    }

    /**
     * @return list<string>
     */
    private function only(InputInterface $input): array
    {
        /** @var list<string> $values */
        $values = $input->getOption('only');

        $names = array_map(trim(...), explode(',', implode(',', $values)));

        return array_values(array_unique(array_filter($names, fn (string $name): bool => $name !== '')));
    }

    private function theme(InputInterface $input): ?Theme
    {
        $value = $input->getOption('theme');

        if ($value === null) {
            return null;
        }

        return Theme::tryFrom((string) $value)
            ?? throw new FocusException("Unknown theme [{$value}]. Expected one of: " . implode(', ', array_column(Theme::cases(), 'value')) . '.');
    }

    private function path(string $path): string
    {
        return str_starts_with($path, '/') ? $path : $this->workingDirectory . DIRECTORY_SEPARATOR . $path;
    }

    private function relative(string $path): string
    {
        $prefix = rtrim($this->workingDirectory, '/\\') . DIRECTORY_SEPARATOR;

        return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
    }
}
