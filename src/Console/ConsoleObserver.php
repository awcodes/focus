<?php

declare(strict_types=1);

namespace Awcodes\Focus\Console;

use Awcodes\Focus\Capture;
use Awcodes\Focus\CardRender;
use Awcodes\Focus\Exceptions\CaptureException;
use Awcodes\Focus\Runtime\CaptureResult;
use Awcodes\Focus\Runtime\CardResult;
use Awcodes\Focus\Runtime\RunObserver;
use Closure;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final readonly class ConsoleObserver implements RunObserver
{
    /**
     * @param  Closure(string): string  $relative
     */
    public function __construct(
        private OutputInterface $output,
        private Closure $relative,
    ) {}

    public function captureStarting(Capture $capture): void
    {
        if ($this->output->isVerbose()) {
            $this->output->writeln("  <fg=gray>…</> {$capture->label()}");
        }
    }

    public function captureFinished(CaptureResult $result): void
    {
        $capture = $result->capture;
        $time = number_format($result->seconds, 1) . 's';

        if ($result->succeeded()) {
            $this->output->writeln("  <info>✓</info> {$capture->label()} <fg=gray>→ {$this->relative($capture->path)} ({$time})</>");
        } else {
            $this->failure($capture, $result->error);
        }

        foreach ($result->warnings as $warning) {
            $this->output->writeln("    <comment>!</comment> {$warning}");
        }
    }

    public function cardStarting(CardRender $render): void
    {
        if ($this->output->isVerbose()) {
            $this->output->writeln("  <fg=gray>…</> {$render->label()}");
        }
    }

    public function cardFinished(CardResult $result): void
    {
        $render = $result->render;
        $time = number_format($result->seconds, 1) . 's';

        if ($result->succeeded()) {
            $this->output->writeln("  <info>✓</info> {$render->label()} <fg=gray>→ {$this->relative($render->path)} ({$time})</>");
        } elseif ($result->error instanceof CaptureException) {
            $this->output->writeln("  <error> ✗ </error> {$render->label()} <fg=red>{$result->error->reason->value}</>");
            $this->output->writeln("      {$result->error->getMessage()}");

            foreach (['Card' => $render->name(), 'Template' => $render->template] as $label => $value) {
                $this->output->writeln(sprintf('      <fg=gray>%-10s</> %s', $label, $value));
            }

            if (is_file($render->path)) {
                $this->output->writeln("      <fg=yellow>Not updated:</> {$this->relative($render->path)} is from a previous run.");
            }
        }

        foreach ($result->warnings as $warning) {
            $this->output->writeln("    <comment>!</comment> {$warning}");
        }
    }

    public function failure(Capture $capture, ?CaptureException $error): void
    {
        if (! $error instanceof CaptureException) {
            return;
        }

        $this->output->writeln("  <error> ✗ </error> {$capture->label()} <fg=red>{$error->reason->value}</>");
        $this->output->writeln("      {$error->getMessage()}");

        $details = array_filter([
            'Screenshot' => $capture->name(),
            'Theme' => $capture->theme->value,
            'Viewport' => "{$capture->viewport->width()}x{$capture->viewport->height()}",
            'URL' => $error->url ?? $capture->screenshot->getUrl(),
            'Selector' => $error->selector,
        ]);

        foreach ($details as $label => $value) {
            $this->output->writeln(sprintf('      <fg=gray>%-10s</> %s', $label, $value));
        }

        if (is_file($capture->path)) {
            $this->output->writeln("      <fg=yellow>Not updated:</> {$this->relative($capture->path)} is from a previous run.");
        }
    }

    private function relative(string $path): string
    {
        return ($this->relative)($path);
    }
}
