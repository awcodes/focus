<?php

declare(strict_types=1);

namespace Awcodes\Focus\Console\Commands;

use Awcodes\Focus\Authentication\FormLogin;
use Awcodes\Focus\Authentication\SessionCache;
use Awcodes\Focus\Capture;
use Awcodes\Focus\CardRender;
use Awcodes\Focus\Console\ConsoleObserver;
use Awcodes\Focus\Enums\Theme;
use Awcodes\Focus\Exceptions\CaptureException;
use Awcodes\Focus\Exceptions\FocusException;
use Awcodes\Focus\Manifest\ManifestLoader;
use Awcodes\Focus\Runtime\CaptureResult;
use Awcodes\Focus\Runtime\CardResult;
use Awcodes\Focus\Runtime\Orphans;
use Awcodes\Focus\Runtime\Runner;
use Awcodes\Focus\Runtime\WorkbenchServer;
use Awcodes\Focus\ScreenshotSuite;
use Awcodes\Focus\Support\TemplateDirectory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'run', description: 'Generate the screenshots and cards defined in focus.php')]
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
            ->addOption('only', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Only generate the named screenshot(s) or card(s); comma-separated or repeated')
            ->addOption('theme', null, InputOption::VALUE_REQUIRED, 'Only generate one theme (' . implode(', ', array_column(Theme::cases(), 'value')) . ')')
            ->addOption('no-cards', null, InputOption::VALUE_NONE, 'Capture screenshots only')
            ->addOption('cards-only', null, InputOption::VALUE_NONE, 'Render cards only, without starting Workbench')
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

        if ($input->getOption('no-cards') && $input->getOption('cards-only')) {
            $io->error('--no-cards and --cards-only cannot be combined.');

            return self::FAILURE;
        }

        try {
            $suite = (new ManifestLoader)->load($this->path((string) $input->getOption('config')));
            $only = $this->only($input);
            $theme = $this->theme($input);
            $captures = $input->getOption('cards-only') ? [] : $suite->plan($this->workingDirectory, $only, $theme);
            $renders = $input->getOption('no-cards') ? [] : $suite->planCards($this->workingDirectory, $only, $theme);
            $templates = $renders === [] ? null : $this->templates($suite, $renders);
        } catch (FocusException $e) {
            $io->error($e->getMessage());

            return self::FAILURE;
        }

        if ($captures === [] && $renders === []) {
            $io->warning('The filters matched no screenshots or cards.');

            return self::SUCCESS;
        }

        if ($input->getOption('list')) {
            $this->list($io, $captures, $renders);

            return self::SUCCESS;
        }

        $filtered = $only !== [] || $theme instanceof Theme;

        if ($input->getOption('prune') && $filtered) {
            $io->error('--prune cannot be combined with --only or --theme: orphans can only be identified after an unfiltered run.');

            return self::FAILURE;
        }

        $observer = new ConsoleObserver($output, $this->relative(...));

        try {
            $server = $captures === [] ? null : $this->server($input, $suite);
        } catch (CaptureException $e) {
            $io->error("{$e->reason->value}: {$e->getMessage()}");

            return self::FAILURE;
        }

        $output->writeln('<info>Focus</info> ' . implode(' and ', array_filter([
            $server instanceof WorkbenchServer
                ? sprintf('capturing %d screenshot(s) from %s%s', count($captures), $server->url, $server->started() ? ' <fg=gray>(Workbench started by Focus)</>' : '')
                : null,
            $templates instanceof TemplateDirectory
                ? sprintf('rendering %d card(s) from %s', count($renders), $this->relative($templates->path))
                : null,
        ])));

        $reused = $this->reusedScreenshots($captures, $renders);

        if ($reused !== []) {
            $output->writeln('<fg=gray>Cards use the existing ' . implode(', ', $reused) . ' screenshot file(s), which this run does not capture.</>');
        }

        try {
            $results = (new Runner($observer, $this->sessions($input, $suite)))
                ->runAll($suite, $captures, $server?->url, $renders, $templates, (bool) $input->getOption('headed'));
        } catch (CaptureException $e) {
            $io->error("{$e->reason->value}: {$e->getMessage()}");

            return self::FAILURE;
        } finally {
            $server?->stop();
        }

        $this->warnAboutIdenticalThemes($output, $results->captures, $results->cards);

        $output->writeln('');
        $output->writeln(implode(', ', array_filter([
            $captures === [] ? null : sprintf('%d captured', count(array_filter($results->captures, fn (CaptureResult $result): bool => $result->succeeded()))),
            $renders === [] ? null : sprintf('%d rendered', count(array_filter($results->cards, fn (CardResult $result): bool => $result->succeeded()))),
            sprintf('<%s>%d failed</>.', $results->failed() === 0 ? 'fg=gray' : 'fg=red', $results->failed()),
        ])));

        if (! $filtered && ! $this->handleOrphans($input, $io, $suite, $captures, $renders)) {
            return self::FAILURE;
        }

        return $results->failed() === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Screenshots that cards use but this run does not capture, so their files on disk are used as they are.
     *
     * @param  list<Capture>  $captures
     * @param  list<CardRender>  $renders
     * @return list<string>
     */
    private function reusedScreenshots(array $captures, array $renders): array
    {
        $used = array_merge(...array_map(fn (CardRender $render): array => $render->card->getScreenshots(), $renders));
        $captured = array_map(fn (Capture $capture): string => $capture->name(), $captures);

        return array_values(array_unique(array_diff($used, $captured)));
    }

    /**
     * The card template directory, with every planned template found before a browser starts.
     *
     * @param  list<CardRender>  $renders
     */
    private function templates(ScreenshotSuite $suite, array $renders): TemplateDirectory
    {
        $templates = new TemplateDirectory((string) $suite->resolveCardTemplatesDirectory($this->workingDirectory));

        foreach (array_unique(array_map(fn (CardRender $render): string => $render->template, $renders)) as $name) {
            $templates->find($name);
        }

        return $templates;
    }

    /**
     * @param  list<Capture>  $captures
     * @param  list<CardRender>  $renders
     */
    private function list(SymfonyStyle $io, array $captures, array $renders): void
    {
        if ($captures !== []) {
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
        }

        if ($renders !== []) {
            $io->table(
                ['Card', 'Template', 'Theme', 'Size', 'Pixels', 'Output'],
                array_map(fn (CardRender $render): array => [
                    $render->name(),
                    $render->template,
                    $render->theme->value,
                    CardRender::sizeSegment($render->size),
                    sprintf('%dx%d', (int) round($render->size->width() * $render->scale), (int) round($render->size->height() * $render->scale)),
                    $this->relative($render->path),
                ], $renders),
            );
        }
    }

    /**
     * A page without dark mode captures the same pixels in every theme, which doubles assets for nothing.
     *
     * @param  list<CaptureResult>  $captures
     * @param  list<CardResult>  $cards
     */
    private function warnAboutIdenticalThemes(OutputInterface $output, array $captures, array $cards): void
    {
        $hashes = [];

        foreach ($captures as $result) {
            if ($result->succeeded() && is_file($result->capture->path)) {
                $hashes[$result->capture->name()][] = md5_file($result->capture->path);
            }
        }

        foreach ($cards as $result) {
            if ($result->succeeded() && is_file($result->render->path)) {
                $hashes[$result->render->name() . ' (' . CardRender::sizeSegment($result->render->size) . ')'][] = md5_file($result->render->path);
            }
        }

        foreach ($hashes as $name => $themeHashes) {
            if (count($themeHashes) > 1 && count(array_unique($themeHashes)) === 1) {
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
     * @param  list<CardRender>  $renders
     */
    private function handleOrphans(InputInterface $input, SymfonyStyle $io, ScreenshotSuite $suite, array $captures, array $renders): bool
    {
        $orphans = $this->orphans($input, $suite, $captures, $renders);

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
     * Orphans in each output directory that this run fully planned. A directory shared with a side skipped by
     * --cards-only or --no-cards is not scanned, since that side's files cannot be told apart from orphans.
     *
     * @param  list<Capture>  $captures
     * @param  list<CardRender>  $renders
     * @return list<string>
     */
    private function orphans(InputInterface $input, ScreenshotSuite $suite, array $captures, array $renders): array
    {
        $planned = [];
        $skipped = [];

        foreach ([
            [$suite->getScreenshots() !== [], (bool) $input->getOption('cards-only'), $suite->resolveOutputDirectory($this->workingDirectory), $captures],
            [$suite->getCards() !== [], (bool) $input->getOption('no-cards'), $suite->resolveCardOutputDirectory($this->workingDirectory), $renders],
        ] as [$defined, $excluded, $directory, $items]) {
            if (! $defined) {
                continue;
            }

            if ($excluded) {
                $skipped[$directory] = true;

                continue;
            }

            $planned[$directory] = [...$planned[$directory] ?? [], ...$items];
        }

        $orphans = [];

        foreach (array_diff_key($planned, $skipped) as $directory => $items) {
            array_push($orphans, ...Orphans::find($directory, $items));
        }

        sort($orphans);

        return $orphans;
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
