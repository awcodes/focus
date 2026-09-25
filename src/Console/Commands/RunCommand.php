<?php

declare(strict_types=1);

namespace Awcodes\Focus\Console\Commands;

use Awcodes\Focus\Authentication\FormLogin;
use Awcodes\Focus\Authentication\SessionCache;
use Awcodes\Focus\Capture;
use Awcodes\Focus\Console\ConsoleObserver;
use Awcodes\Focus\Enums\Theme;
use Awcodes\Focus\Exceptions\CaptureException;
use Awcodes\Focus\Exceptions\FocusException;
use Awcodes\Focus\Manifest\ManifestLoader;
use Awcodes\Focus\Runtime\CaptureResult;
use Awcodes\Focus\Runtime\Orphans;
use Awcodes\Focus\Runtime\Runner;
use Awcodes\Focus\Runtime\WorkbenchServer;
use Awcodes\Focus\ScreenshotSuite;
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
            ->addOption('list', null, InputOption::VALUE_NONE, 'List the planned captures without opening a browser')
            ->addOption('fresh-login', null, InputOption::VALUE_NONE, 'Sign in again instead of reusing the previous run\'s session');
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

        $filtered = $this->only($input) !== [] || $input->getOption('theme') !== null;

        if ($input->getOption('prune') && $filtered) {
            $io->error('--prune cannot be combined with --only or --theme: orphans can only be identified after an unfiltered run.');

            return self::FAILURE;
        }

        $observer = new ConsoleObserver($output, $this->relative(...));

        try {
            $server = $this->server($input, $suite);
        } catch (CaptureException $e) {
            $io->error("{$e->reason->value}: {$e->getMessage()}");

            return self::FAILURE;
        }

        $output->writeln(sprintf(
            '<info>Focus</info> capturing %d screenshot(s) from %s%s',
            count($captures),
            $server->url,
            $server->started() ? ' <fg=gray>(Workbench started by Focus)</>' : '',
        ));

        try {
            $results = (new Runner($observer, $this->sessions($input, $suite)))->run($suite, $captures, $server->url, (bool) $input->getOption('headed'));
        } catch (CaptureException $e) {
            $io->error("{$e->reason->value}: {$e->getMessage()}");

            return self::FAILURE;
        } finally {
            $server->stop();
        }

        $failed = array_filter($results, fn (CaptureResult $result): bool => ! $result->succeeded());

        $this->warnAboutIdenticalThemes($output, $results);

        $output->writeln('');
        $output->writeln(sprintf(
            '%d captured, <%s>%d failed</>.',
            count($results) - count($failed),
            $failed === [] ? 'fg=gray' : 'fg=red',
            count($failed),
        ));

        if (! $filtered && ! $this->handleOrphans($input, $io, $suite, $captures)) {
            return self::FAILURE;
        }

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * A page without dark mode captures the same pixels in every theme, which doubles assets for nothing.
     *
     * @param  list<CaptureResult>  $results
     */
    private function warnAboutIdenticalThemes(OutputInterface $output, array $results): void
    {
        $hashes = [];

        foreach ($results as $result) {
            if ($result->succeeded() && is_file($result->capture->path)) {
                $hashes[$result->capture->name()][] = md5_file($result->capture->path);
            }
        }

        foreach ($hashes as $name => $screenshotHashes) {
            if (count($screenshotHashes) > 1 && count(array_unique($screenshotHashes)) === 1) {
                $output->writeln("    <comment>!</comment> {$name}: every theme produced an identical image, so the page may not support dark mode. Consider ->themes([Theme::Light]).");
            }
        }
    }

    private function sessions(InputInterface $input, ScreenshotSuite $suite): ?SessionCache
    {
        $login = $suite->getAuthenticator();

        if (! $login instanceof FormLogin || ! $suite->getReuseSession()) {
            return null;
        }

        $sessions = SessionCache::for($this->workingDirectory, $login);

        if ($input->getOption('fresh-login')) {
            $sessions->forget();
        }

        return $sessions;
    }

    private function server(InputInterface $input, ScreenshotSuite $suite): WorkbenchServer
    {
        $baseUrl = $input->getOption('base-url') ?? $suite->getBaseUrl();

        return $baseUrl === null
            ? WorkbenchServer::start($this->workingDirectory)
            : WorkbenchServer::connect((string) $baseUrl);
    }

    /**
     * @param  list<Capture>  $captures
     */
    private function handleOrphans(InputInterface $input, SymfonyStyle $io, ScreenshotSuite $suite, array $captures): bool
    {
        $orphans = Orphans::find($suite->resolveOutputDirectory($this->workingDirectory), $captures);

        if ($orphans === []) {
            return true;
        }

        $io->newLine();
        $io->writeln(sprintf('<comment>%d orphaned asset(s)</comment> match the Focus filename scheme but are not in the manifest:', count($orphans)));
        $io->listing(array_map($this->relative(...), $orphans));

        if (! $input->getOption('prune')) {
            $io->writeln('<fg=gray>Run with --prune to delete them.</>');

            return true;
        }

        if (! $input->getOption('force') && ! $io->confirm('Delete these files?', false)) {
            $io->writeln('Kept orphaned assets.');

            return true;
        }

        $ok = true;

        foreach ($orphans as $orphan) {
            if (! @unlink($orphan)) {
                $io->error("Could not delete {$this->relative($orphan)}.");
                $ok = false;
            }
        }

        if ($ok) {
            $io->writeln(sprintf('<info>Deleted</info> %d orphaned asset(s).', count($orphans)));
        }

        return $ok;
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
